<?php

namespace ItmoSoft\EmailMarketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->validate();

        $subscriber = $audience->subscribers()->firstOrNew(['email' => mb_strtolower(trim($email))]);
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
            'name' => $name, 'subject' => $subject, 'html' => $html, 'text' => $text,
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

    /**
     * Freeze the currently consented audience into a recipient snapshot.
     * A delivery adapter/queue worker should process these recipient records.
     */
    public function queueCampaign(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Draft) {
            throw new InvalidArgumentException('Only draft campaigns can be queued.');
        }

        return DB::transaction(function () use ($campaign): Campaign {
            $subscribers = $this->model('subscriber')::query()
                ->where('audience_id', $campaign->audience_id)
                ->where('status', SubscriberStatus::Subscribed->value)
                ->whereNotNull('consented_at')
                ->get(['id', 'email']);

            foreach ($subscribers as $subscriber) {
                $this->model('recipient')::query()->firstOrCreate(
                    ['campaign_id' => $campaign->getKey(), 'subscriber_id' => $subscriber->getKey()],
                    ['email' => $subscriber->email, 'status' => 'pending']
                );
            }

            $campaign->forceFill(['status' => CampaignStatus::Queued])->save();

            return $campaign->refresh();
        });
    }

    public function markSending(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::Queued) {
            throw new InvalidArgumentException('Only queued campaigns can start sending.');
        }

        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();

        return $campaign->refresh();
    }

    public function recordDelivery(CampaignRecipient $recipient): CampaignRecipient
    {
        $recipient->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();

        return $recipient->refresh();
    }

    public function recordOpen(CampaignRecipient $recipient): CampaignRecipient
    {
        $recipient->forceFill(['status' => $recipient->status, 'opened_at' => $recipient->opened_at ?? now()])->save();

        return $recipient->refresh();
    }

    public function recordClick(CampaignRecipient $recipient): CampaignRecipient
    {
        $recipient->forceFill(['status' => $recipient->status, 'clicked_at' => $recipient->clicked_at ?? now()])->save();

        return $recipient->refresh();
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
