<?php

namespace ItmoSoft\EmailMarketing\Tests\Feature;

use ItmoSoft\EmailMarketing\Enums\CampaignStatus;
use ItmoSoft\EmailMarketing\Enums\SubscriberStatus;
use ItmoSoft\EmailMarketing\Services\EmailMarketingManager;
use ItmoSoft\EmailMarketing\Tests\TestCase;

class EmailMarketingManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_it_creates_an_audience_and_subscriber_with_consent(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('Product updates');
        $subscriber = $manager->subscribe($audience, 'TEST@example.com', ['first_name' => 'Adeel'], 'website_form');

        $this->assertSame('test@example.com', $subscriber->email);
        $this->assertSame(SubscriberStatus::Subscribed, $subscriber->status);
        $this->assertNotNull($subscriber->consented_at);
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
        $notConsented = $manager->subscribe($audience, 'no@example.com');
        $manager->unsubscribe($notConsented);
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');

        $manager->queueCampaign($campaign);

        $this->assertSame(CampaignStatus::Queued, $campaign->refresh()->status);
        $this->assertSame(1, $campaign->recipients()->count());
    }

    public function test_it_records_delivery_and_engagement_metrics(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $subscriber = $manager->subscribe($audience, 'person@example.com');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $manager->queueCampaign($campaign);
        $recipient = $campaign->recipients()->first();

        $manager->recordDelivery($recipient);
        $manager->recordOpen($recipient->refresh());
        $manager->recordClick($recipient->refresh());

        $this->assertSame([
            'recipients' => 1, 'delivered' => 1, 'opened' => 1, 'clicked' => 1, 'bounced' => 0,
        ], $manager->metrics($campaign->refresh()));
    }

    public function test_it_moves_a_queued_campaign_to_sending(): void
    {
        $manager = app(EmailMarketingManager::class);
        $audience = $manager->createAudience('News');
        $template = $manager->createTemplate('News', 'News', '<p>News</p>');
        $campaign = $manager->createCampaign($audience, $template, 'News campaign');
        $manager->queueCampaign($campaign);
        $campaign = $manager->markSending($campaign->refresh());

        $this->assertSame(CampaignStatus::Sending, $campaign->status);
    }
}
