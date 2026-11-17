# Release guide — v1.0.0

Repository: `ITMOSOFT/laravel-email-marketing`

Composer package: `itmosoft/laravel-email-marketing`

## Repository description

Consent-aware email audiences, reusable templates, campaign orchestration, and campaign metrics for Laravel applications.

## User-run Git commands

Run locally from the extracted package directory. Review the staged changes before committing.

```bash
git init
git branch -M main
git add .
git status
git commit -m "chore: initial Laravel email marketing package"
git remote add origin https://github.com/ITMOSOFT/laravel-email-marketing.git
git push -u origin main
```

After local tests and GitHub Actions pass:

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## GitHub Release

Title: `v1.0.0 - Initial Release`

Description:

Initial release of ITMOSOFT Laravel Email Marketing.

- Consent-aware audiences and subscriber lifecycle management.
- Subscriber opt-in and unsubscribe timestamps and consent source.
- Reusable HTML/plain-text email templates.
- Campaign lifecycle and recipient snapshots.
- Campaign-level delivered, opened, clicked, and bounced counts.
- Configurable tables and model classes.
- Laravel 12/13 and PHP 8.3+ support.

Delivery-provider integration and background dispatch remain application responsibilities in this release.

## Packagist

1. Open https://packagist.org/packages/submit
2. Submit `https://github.com/ITMOSOFT/laravel-email-marketing`
3. Configure the Packagist GitHub webhook if prompted.
4. Confirm Packagist detects tag `v1.0.0`.
5. Verify installation with `composer require itmosoft/laravel-email-marketing`.

Do not publish until local tests and the GitHub Actions matrix pass.
