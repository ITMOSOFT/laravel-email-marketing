# Review notes — ITMOSOFT Laravel Email Marketing

Reviewed against the public `main` branch of `ITMOSOFT/laravel-email-marketing` on 2026-11-24. This ZIP is a locally prepared replacement package; no repository files, branches, commits, tags, releases, or Packagist records were changed.

## Findings addressed

1. **Unsubscribe after queueing:** queued recipient rows are snapshots. The original flow had no manager-level check to stop a delivery adapter from sending to someone who unsubscribed after the snapshot. Added `isRecipientEligibleForDelivery()` and documented that it must be called immediately before provider handoff. It refreshes cached relations and verifies current consent, subscriber status, and audience membership.
2. **Campaign transition races / stale models:** queue and start-sending transitions previously relied on the status in the caller's possibly stale model instance. They now reload and lock the current campaign row inside a transaction before checking and changing state.
3. **Provider callback idempotency:** delivery callbacks could overwrite timestamps and a late delivery callback could clear a bounce when given a stale model. Provider event writes now lock and reload the current recipient row; first-event timestamps are preserved and a bounce is not overwritten by a late delivery callback.
4. **Bounce metric path:** metrics counted bounced recipients but the manager had no public method to record a bounce. Added `recordBounce()`.
5. **Email normalization dependency:** email normalization used `mb_strtolower()` without declaring `ext-mbstring` as a runtime requirement. Switched to `strtolower()` after trimming to avoid an undeclared runtime extension dependency for ordinary ASCII email addresses.
6. **Coverage:** added tests for invalid email, empty template content, stale campaign queue attempts, unsubscribe-after-queue suppression (including cached relations), cross-audience recipient rejection, valid/invalid campaign transitions, late delivery callbacks after bounce, and bounce metrics.

## Remaining application responsibilities

- Provider delivery, retry/backoff policy, webhook signature verification, bounce/complaint ingestion, tracking endpoints, unsubscribe HTTP endpoints, authentication/authorization, rate limiting, double opt-in, and tenant scoping remain application responsibilities.
- `isRecipientEligibleForDelivery()` must be called immediately before each provider handoff. A check performed substantially earlier cannot prevent a later unsubscribe race by itself.
- The package stores consent timestamps and optional source strings; the host application remains responsible for collecting and retaining adequate consent evidence.
