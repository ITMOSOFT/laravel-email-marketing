# Changelog

## 1.0.1 - 2026-11-24

- Serialize campaign queue/start transitions with database transactions and row locks.
- Re-check current subscriber consent and audience membership immediately before provider delivery.
- Preserve the first delivery timestamp on repeated provider callbacks.
- Add explicit bounce recording so bounced metrics can be updated through the manager.
- Normalize email addresses before validation without introducing an undeclared mbstring runtime dependency.
- Expand tests for invalid email, empty templates, stale campaign instances, unsubscribe-after-queue suppression, audience isolation, and bounce metrics.

## 1.0.0 - 2026-11-17

- Initial release: audiences, consent-aware subscriber management, reusable email templates, campaign lifecycle, recipient snapshots, and delivery metrics.
