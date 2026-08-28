# Changelog

## [Unreleased]

### Reliability

- Reject malformed and scalar inbound JSON before persistence.
- Skip outbound HTTP work when an already delivered queue job is executed again.
- Return the existing outbound delivery for identical idempotent replays without enqueuing a second job.
- Raise an explicit conflict when a key is reused for changed request content.
- Make inbound duplicate handling deterministic through `firstOrCreate()` plus fingerprint comparison.
- Preserve only bounded response metadata instead of arbitrary partner response content.
- Record failed HTTP/connection state before allowing queue retry and dead-letter transition.

### Tests

- Add Testbench database/route integration setup.
- Cover outbound idempotency, inbound callbacks, delivery success/failure, dead-letter state, signature rejection, HTTPS enforcement, and retry policy.
- Expand CI to PHP 8.2, 8.3, and 8.4 with Composer validation.

### Maintenance

- Use the signature contract in HTTP and queue boundaries instead of depending on the concrete HMAC implementation.
- Publish Composer failure details in the workflow summary without pull-request write permission.
