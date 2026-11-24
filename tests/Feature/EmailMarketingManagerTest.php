<?php

namespace ItmoSoft\EmailMarketing\Tests\Feature;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use ItmoSoft\EmailMarketing\Enums\CampaignStatus;
use ItmoSoft\EmailMarketing\Enums\SubscriberStatus;
use ItmoSoft\EmailMarketing\Services\EmailMarketingManager;
use ItmoSoft\EmailMarketing\Tests\TestCase;

class EmailMarketingManagerTest extends TestCase
{
    public function test_it_creates_an_audience_and_normalizes_subscriber_email_with_consent(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('Product updates');
        $subscriber = $manager->subscribe($audience, ' TEST@example.com ', ['first_name' => 'Adeel'], 'website_form');

        $this->assertSame('test@example.com', $subscriber->email);
        $this->assertSame(SubscriberStatus::Subscribed, $subscriber->status);
        $this->assertNotNull($subscriber->consented_at);
        $this->assertSame('website_form', $subscriber->consent_source);
    }

    public function test_it_rejects_an_invalid_subscriber_email(): void
    {
        $this->expectException(ValidationException::class);

        $manager = app(EmailMarketingManager::class);
        $manager->subscribe($manager->createAudience('News'), 'not-an-email');
    }

    public function test_it_unsubscribes_a_subscriber(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $subscriber = $manager->subscribe($audience, 'person@example.com');
        $subscriber = $manager->unsubscribe($subscriber);

        $this->assertSame(SubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_it_requires_template_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(EmailMarketingManager::class)->createTemplate('Empty', 'Subject', '  ', "\n ");
    }

    public function test_it_creates_a_template_and_campaign(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $template = $manager->createTemplate('Welcome', 'Hello', '<h1>Hello</h1>', 'Hello');
        $campaign = $manager->createCampaign($audience, $template, 'Welcome campaign');

        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function test_it_queues_only_subscribers_with_recorded_consent(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $manager->subscribe($audience, 'yes@example.com');
        $unsubscribed = $manager->unsubscribe($manager->subscribe($audience, 'no@example.com'));
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');

        $manager->queueCampaign($campaign);

        $this->assertSame(CampaignStatus::Queued, $campaign->refresh()->status);
        $this->assertSame(1, $campaign->recipients()->count());
        $this->assertSame(SubscriberStatus::Unsubscribed, $unsubscribed->status);
    }

    public function test_it_does_not_allow_a_stale_campaign_instance_to_queue_twice(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $staleCampaign = $campaign->replicate();
        $staleCampaign->setAttribute($campaign->getKeyName(), $campaign->getKey());

        $manager->queueCampaign($campaign);

        $this->expectException(InvalidArgumentException::class);
        $manager->queueCampaign($staleCampaign);
    }

    public function test_it_does_not_deliver_to_a_subscriber_who_unsubscribes_after_queueing(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $subscriber = $manager->subscribe($audience, 'person@example.com');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $manager->queueCampaign($campaign);
        $recipient = $campaign->recipients()->firstOrFail();

        $recipient->load(['campaign', 'subscriber']);
        $this->assertTrue($manager->isRecipientEligibleForDelivery($recipient));

        $manager->unsubscribe($subscriber);

        // The same object has cached relationships; the manager must refresh them.
        $this->assertFalse($manager->isRecipientEligibleForDelivery($recipient));
    }

    public function test_it_rejects_cross_audience_recipient_delivery(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audienceA = $manager->createAudience('Audience A');
        $audienceB = $manager->createAudience('Audience B');
        $subscriber = $manager->subscribe($audienceB, 'person@example.com');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audienceA, $template, 'News campaign');
        $manager->queueCampaign($campaign);

        $recipient = $campaign->recipients()->create([
            'subscriber_id' => $subscriber->getKey(),
            'email' => $subscriber->email,
            'status' => 'pending',
        ]);

        $this->assertFalse($manager->isRecipientEligibleForDelivery($recipient));
    }

    public function test_it_moves_a_queued_campaign_to_sending_and_rejects_invalid_transition(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');

        $this->expectException(InvalidArgumentException::class);
        $manager->markSending($campaign);
    }

    public function test_it_allows_a_queued_campaign_to_start_sending(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $campaign = $manager->queueCampaign($campaign);

        $this->assertSame(CampaignStatus::Sending, $manager->markSending($campaign)->status);
    }

    public function test_a_late_delivery_callback_does_not_clear_a_bounce(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $manager->subscribe($audience, 'person@example.com');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $manager->queueCampaign($campaign);
        $recipient = $campaign->recipients()->firstOrFail();

        $manager->recordBounce($recipient);
        $manager->recordDelivery($recipient); // deliberately stale instance

        $this->assertSame('bounced', $recipient->fresh()->status);
    }

    public function test_it_records_delivery_bounce_and_engagement_metrics(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $manager->subscribe($audience, 'delivered@example.com');
        $manager->subscribe($audience, 'bounced@example.com');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $manager->queueCampaign($campaign);
        $recipients = $campaign->recipients()->orderBy('email')->get();

        $manager->recordDelivery($recipients[0]);
        $manager->recordOpen($recipients[0]->fresh());
        $manager->recordClick($recipients[0]->fresh());
        $manager->recordBounce($recipients[1]);

        $this->assertSame([
            'recipients' => 2,
            'delivered' => 1,
            'opened' => 1,
            'clicked' => 1,
            'bounced' => 1,
        ], $manager->metrics($campaign->fresh()));
    }
}
