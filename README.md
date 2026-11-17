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
```

A queue worker or application-owned delivery adapter can process `CampaignRecipient` records and call `recordDelivery`, `recordOpen`, and `recordClick` as provider events arrive.

## Consent and unsubscribe

`subscribe()` records a consent timestamp and optional consent source. `unsubscribe()` changes the subscriber status and records the unsubscribe timestamp. Campaign queuing snapshots only subscribers whose status is `subscribed` and whose `consented_at` is not null.

Do not call `subscribe()` merely because an email address was imported. Only call it after your application has established a valid consent basis. Keep evidence of consent and comply with applicable privacy and anti-spam laws.

## Campaign lifecycle

`draft` → `queued` → `sending`

The manager enforces the draft-to-queued and queued-to-sending transitions. Delivery execution is intentionally delegated to your application. The package stores recipient snapshots to preserve the audience selected at queue time.

## Metrics

```php
$metrics = $manager->metrics($campaign);

// recipients, delivered, opened, clicked, bounced
```

Open and click metrics depend on trustworthy provider callbacks or tracking endpoints implemented by your application. They are counts, not unique-recipient rates.

## Configuration

`config/email-marketing.php` supports table prefixes, table names, and model class overrides.

## Security and operational notes

- Add authorization, rate limiting, double opt-in workflows, bot protection, and unsubscribe HTTP endpoints in your application.
- Keep unsubscribe links functional and ensure suppression is honored across campaigns.
- Do not place sensitive personal data in subscriber attributes or campaign metadata without a clear need and retention policy.
- Escape/untrusted-template rendering and personalization must be handled safely by the rendering layer.
- Provider delivery, retries, bounce/complaint webhooks, open/click tracking endpoints, segmentation, scheduled dispatch workers, and suppression lists are not included in v1.0.0.
- Tenant isolation is not automatic; scope queries and authorization in your application.
- Audience membership is a single-audience relationship in this release. A subscriber may appear in more than one audience using separate records.

## Testing

```bash
composer install
composer test
```

## Scope boundaries

This package owns audiences, subscriber consent state, templates, campaigns, recipient snapshots, and basic campaign metrics. It does not replace `laravel-notifications`, implement SMTP/provider delivery, build a visual email editor, run automation journeys, or provide a marketing analytics dashboard.

## License

MIT.
