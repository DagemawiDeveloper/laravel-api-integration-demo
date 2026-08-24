# Changelog

## [Unreleased]

### Reliability

- Return the existing outbound delivery for identical idempotent replays without enqueuing a second job.
- Raise an explicit conflict when a key is reused for changed request content.
- Make inbound duplicate handling deterministic through `firstOrCreate()` plus fingerprint comparison.
- Preserve only bounded response metadata instead of arbitrary partner response content.
- Record failed HTTP/connection state before allowing queue retry and dead-letter transition.

### Tests

- Add Testbench database/route integration setup.
- Cover outbound idempotency, inbound callbacks, delivery success/failure, dead-letter state, signature rejection, HTTPS enforcement, and retry policy.
- Expand CI to PHP 8.1, 8.2, and 8.3 with Composer validation.
