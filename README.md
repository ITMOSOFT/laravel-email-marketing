# ITMOSOFT Laravel Email Marketing

Consent-aware email audiences, subscriber management, reusable templates, campaign orchestration, recipient snapshots, and campaign-level engagement metrics for Laravel 12 and 13.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require itmosoft/laravel-email-marketing
php artisan vendor:publish --tag=itmosoft-email-marketing-config
php artisan migrate
```

## Quick start

```php
use ItmoSoft\EmailMarketing\Services\EmailMarketingManager;

$manager = app(EmailMarketingManager::class);

$audience = $manager->createAudience('Product updates');

// Call subscribe only after your application has established a valid consent basis.
$subscriber = $manager->subscribe(
    $audience,
    'person@example.com',
    ['first_name' => 'Taylor'],
    'website_signup_form'
);

$template = $manager->createTemplate(
    'Product announcement',
    'We have news',
    '<h1>New product update</h1>',
    'New product update'
);

$campaign = $manager->createCampaign($audience, $template, 'November update');
$campaign = $manager->queueCampaign($campaign);
$campaign = $manager->markSending($campaign);

foreach ($campaign->recipients as $recipient) {
    // Re-check current consent immediately before sending. This catches
    // unsubscribes that happened after the campaign recipient snapshot.
    if (! $manager->isRecipientEligibleForDelivery($recipient)) {
        continue;
    }

    // Hand the recipient to your configured mail provider/queue adapter.
}
```

## Consent and unsubscribe

`subscribe()` normalizes the email address, records a consent timestamp, and stores an optional consent source. `unsubscribe()` changes the subscriber status and records the unsubscribe timestamp. Campaign queuing snapshots only subscribers whose status is `subscribed` and whose `consented_at` is not null.

A queued campaign is a snapshot, not a permanent grant of permission to send. **Always call `isRecipientEligibleForDelivery()` immediately before provider handoff.** This re-checks the subscriber's current status, consent timestamp, and audience association so a later unsubscribe is respected.

Do not call `subscribe()` merely because an email address was imported. Only call it after your application has established a valid consent basis. Keep evidence of consent and comply with applicable privacy and anti-spam laws.

## Campaign lifecycle

`draft` → `queued` → `sending`

The manager enforces these transitions inside database transactions with row locks. Recipient records are created once per campaign/subscriber pair. Delivery execution remains the responsibility of your application or provider adapter.

## Delivery and metrics

```php
$manager->recordDelivery($recipient);
$manager->recordOpen($recipient);
$manager->recordClick($recipient);
$manager->recordBounce($recipient);

$metrics = $manager->metrics($campaign);
// recipients, delivered, opened, clicked, bounced
```

Delivery timestamps are retained on repeated delivery callbacks. Open and click timestamps are first-event timestamps. Open/click metrics depend on trustworthy provider callbacks or tracking endpoints implemented by your application. They are counts, not unique-recipient rates.

## Configuration

`config/email-marketing.php` supports table prefixes, table names, and model class overrides. The published config key is `itmosoft-email-marketing`.

## Security and operational notes

- Add authorization, rate limiting, double opt-in workflows, bot protection, and unsubscribe HTTP endpoints in your application.
- Keep unsubscribe links functional and ensure suppression is honored across campaigns by re-checking recipient eligibility immediately before send.
- Do not place sensitive personal data in subscriber attributes or campaign metadata without a clear need and retention policy.
- Escape untrusted template content and handle personalization safely in the rendering layer.
- Provider delivery, retries, bounce/complaint webhooks, open/click tracking endpoints, segmentation, scheduled dispatch workers, and suppression lists are not included.
- Tenant isolation is not automatic; scope queries and authorization in your application.
- Audience membership is a single-audience relationship. A subscriber may appear in more than one audience using separate records.

## Testing

```bash
composer validate --strict
composer install
composer test
```

## Scope boundaries

This package owns audiences, subscriber consent state, templates, campaigns, recipient snapshots, and basic campaign metrics. It does not replace `laravel-notifications`, implement SMTP/provider delivery, build a visual email editor, run automation journeys, or provide a marketing analytics dashboard.

## License

MIT.
