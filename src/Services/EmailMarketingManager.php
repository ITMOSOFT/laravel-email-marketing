<?php

namespace ItmoSoft\EmailMarketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ItmoSoft\EmailMarketing\Enums\CampaignStatus;
use ItmoSoft\EmailMarketing\Enums\SubscriberStatus;
use ItmoSoft\EmailMarketing\Models\Audience;
use ItmoSoft\EmailMarketing\Models\Campaign;
use ItmoSoft\EmailMarketing\Models\CampaignRecipient;
use ItmoSoft\EmailMarketing\Models\EmailTemplate;
use ItmoSoft\EmailMarketing\Models\Subscriber;
use InvalidArgumentException;

class EmailMarketingManager
{
    public function createAudience(string $name, ?string $description = null): Audience
    {
        return $this->model('audience')::query()->create(['name' => $name, 'description' => $description]);
    }

    public function subscribe(Audience $audience, string $email, array $attributes = [], ?string $consentSource = null): Subscriber
    {
        $email = strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->validate();

        $subscriber = $audience->subscribers()->firstOrNew(['email' => $email]);
        $subscriber->fill([
            'status' => SubscriberStatus::Subscribed,
            'attributes' => $attributes,
            'consented_at' => now(),
            'consent_source' => $consentSource,
            'unsubscribed_at' => null,
        ]);
        $subscriber->save();

        return $subscriber->refresh();
    }

    public function unsubscribe(Subscriber $subscriber): Subscriber
    {
        $subscriber->forceFill([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
        ])->save();

        return $subscriber->refresh();
    }

    public function createTemplate(string $name, string $subject, string $html, ?string $text = null): EmailTemplate
    {
        if (trim($html) === '' && trim((string) $text) === '') {
            throw new InvalidArgumentException('An email template must contain HTML or plain-text content.');
        }

        return $this->model('template')::query()->create([
            'name' => $name,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ]);
    }

    public function createCampaign(Audience $audience, EmailTemplate $template, string $name, ?\DateTimeInterface $scheduledAt = null): Campaign
    {
        return $this->model('campaign')::query()->create([
            'audience_id' => $audience->getKey(),
            'template_id' => $template->getKey(),
            'name' => $name,
            'status' => CampaignStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /** Freeze the currently consented audience into an immutable recipient snapshot. */
    public function queueCampaign(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $lockedCampaign = $this->model('campaign')::query()
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== CampaignStatus::Draft) {
                throw new InvalidArgumentException('Only draft campaigns can be queued.');
            }

            $subscribers = $this->model('subscriber')::query()
                ->where('audience_id', $lockedCampaign->audience_id)
                ->where('status', SubscriberStatus::Subscribed->value)
                ->whereNotNull('consented_at')
                ->get(['id', 'email']);

            foreach ($subscribers as $subscriber) {
                $this->model('recipient')::query()->firstOrCreate(
                    ['campaign_id' => $lockedCampaign->getKey(), 'subscriber_id' => $subscriber->getKey()],
                    ['email' => $subscriber->email, 'status' => 'pending']
                );
            }

            $lockedCampaign->forceFill(['status' => CampaignStatus::Queued])->save();

            return $lockedCampaign->refresh();
        });
    }

    public function markSending(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $lockedCampaign = $this->model('campaign')::query()
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== CampaignStatus::Queued) {
                throw new InvalidArgumentException('Only queued campaigns can start sending.');
            }

            $lockedCampaign->forceFill(['status' => CampaignStatus::Sending])->save();

            return $lockedCampaign->refresh();
        });
    }

    /**
     * Re-check current consent immediately before handing a recipient to a provider.
     * This catches an unsubscribe that happened after the campaign was queued.
     */
    public function isRecipientEligibleForDelivery(CampaignRecipient $recipient): bool
    {
        // Force a fresh relationship read: callers may hold a recipient instance
        // whose subscriber relation was loaded before an unsubscribe occurred.
        $recipient->load(['campaign', 'subscriber']);
        $campaign = $recipient->campaign;
        $subscriber = $recipient->subscriber;

        if ($campaign === null || $subscriber === null) {
            return false;
        }

        $campaignStatus = $campaign->status instanceof CampaignStatus
            ? $campaign->status->value
            : $campaign->status;
        $subscriberStatus = $subscriber->status instanceof SubscriberStatus
            ? $subscriber->status->value
            : $subscriber->status;

        return in_array($campaignStatus, [CampaignStatus::Queued->value, CampaignStatus::Sending->value], true)
            && (int) $campaign->audience_id === (int) $subscriber->audience_id
            && $subscriberStatus === SubscriberStatus::Subscribed->value
            && $subscriber->consented_at !== null;
    }

    public function recordDelivery(CampaignRecipient $recipient): CampaignRecipient
    {
        return $this->withLockedRecipient($recipient, function (CampaignRecipient $locked): void {
            $locked->forceFill([
                'status' => $locked->status === 'bounced' ? 'bounced' : 'delivered',
                'delivered_at' => $locked->delivered_at ?? now(),
            ])->save();
        });
    }

    public function recordBounce(CampaignRecipient $recipient): CampaignRecipient
    {
        return $this->withLockedRecipient($recipient, function (CampaignRecipient $locked): void {
            $locked->forceFill(['status' => 'bounced'])->save();
        });
    }

    public function recordOpen(CampaignRecipient $recipient): CampaignRecipient
    {
        return $this->withLockedRecipient($recipient, function (CampaignRecipient $locked): void {
            $locked->forceFill(['opened_at' => $locked->opened_at ?? now()])->save();
        });
    }

    public function recordClick(CampaignRecipient $recipient): CampaignRecipient
    {
        return $this->withLockedRecipient($recipient, function (CampaignRecipient $locked): void {
            $locked->forceFill(['clicked_at' => $locked->clicked_at ?? now()])->save();
        });
    }

    /** Apply provider events against a fresh, locked row so out-of-order or stale
     * webhook payloads cannot overwrite a previously recorded terminal state. */
    protected function withLockedRecipient(CampaignRecipient $recipient, callable $callback): CampaignRecipient
    {
        return DB::transaction(function () use ($recipient, $callback): CampaignRecipient {
            $locked = $this->model('recipient')::query()
                ->whereKey($recipient->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $callback($locked);

            return $locked->refresh();
        });
    }

    public function metrics(Campaign $campaign): array
    {
        $recipients = $campaign->recipients();

        return [
            'recipients' => (clone $recipients)->count(),
            'delivered' => (clone $recipients)->whereNotNull('delivered_at')->count(),
            'opened' => (clone $recipients)->whereNotNull('opened_at')->count(),
            'clicked' => (clone $recipients)->whereNotNull('clicked_at')->count(),
            'bounced' => (clone $recipients)->where('status', 'bounced')->count(),
        ];
    }

    protected function model(string $key): string
    {
        return config("itmosoft-email-marketing.models.{$key}");
    }
}
