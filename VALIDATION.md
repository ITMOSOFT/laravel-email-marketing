# Validation record

Performed in the preparation environment:

- PHP syntax check (`php -l`) passed for every PHP file in this ZIP.
- `composer.json` parsed successfully as JSON.

Not performed in the preparation environment:

- `composer validate --strict` — Composer executable is not installed.
- PHPUnit — dependencies/vendor directory are not installed, so the test suite was not executed.
- Laravel 12/13 compatibility matrix — must be confirmed by GitHub Actions after the user applies the ZIP.

Run locally:

```bash
composer validate --strict
composer install
composer test
```

Then run/verify the GitHub Actions matrix for PHP 8.3 and 8.4 with Laravel 12 and 13. Do not release until all local tests and CI jobs pass.
