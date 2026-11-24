# Release guide — v1.0.1

Repository: `ITMOSOFT/laravel-email-marketing`

Composer package: `itmosoft/laravel-email-marketing`

## Repository description

Consent-aware email audiences, reusable templates, campaign orchestration, and campaign metrics for Laravel applications.

## User-run Git commands

Run locally from the package directory after reviewing changes and confirming tests:

```bash
git status
git add .
git diff --cached
git commit -m "fix: harden email marketing consent and campaign lifecycle"
git push origin main
```

After local tests and GitHub Actions pass:

```bash
git fetch origin --tags
git tag -a v1.0.1 -m "Release v1.0.1"
git push origin v1.0.1
```

## GitHub Release

Title: `v1.0.1 - Consent and Campaign Lifecycle Improvements`

Description:

- Serialize campaign queue/start transitions using database transactions and row locks.
- Re-check current consent and audience association immediately before delivery.
- Preserve delivery timestamps across repeated provider callbacks.
- Add explicit bounce recording for campaign metrics.
- Normalize subscriber email addresses before validation.
- Expand test coverage for lifecycle, unsubscribe suppression, audience isolation, and bounce metrics.

Compatibility: PHP 8.3+, Laravel 12 and 13.

## Packagist

1. Confirm the GitHub repository is connected to Packagist.
2. Confirm Packagist detects tag `v1.0.1`.
3. Verify installation with `composer require itmosoft/laravel-email-marketing:^1.0.1`.

Do not publish until local tests and the GitHub Actions matrix pass.
