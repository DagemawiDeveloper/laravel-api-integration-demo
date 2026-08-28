# Security

RelayHub demonstrates the following integration controls:

- HMAC-SHA256 signatures for inbound and outbound request bodies.
- Timing-safe signature comparison via `hash_equals()`.
- Secrets sourced from environment configuration rather than committed files.
- Deterministic idempotency with explicit changed-content conflicts.
- Queue isolation for external calls.
- HTTPS-only outbound destinations.
- Bounded HTTP connection and request timeouts.
- Remote response metadata storage without persisting arbitrary partner response bodies.
- No production credentials, customer data, or proprietary application code in this repository.

## Trust boundaries

- The hosting Laravel application, database, and queue are trusted.
- Partner endpoints and all inbound callers are untrusted.
- Inbound data is processed only after signature verification.
- A valid signature authenticates possession of the shared secret; it does not replace domain-specific authorization.

## Idempotency security

A key can identify only one normalized event/payload combination. Identical retries are safe. Changed content receives an explicit conflict rather than overwriting the original or creating a second side effect.

## Response-data policy

Partner responses can contain tokens, personal data, or large payloads. RelayHub stores only byte count, SHA-256, and bounded content type alongside status/error metadata.

## Production additions

Production deployments should additionally provide:

- secret rotation and dual-key transition procedures;
- timestamp/replay windows where the partner protocol supports them;
- source allow-listing or mutual TLS where practical;
- application-level authorization;
- rate limiting;
- monitoring, alerts, and dead-letter re-drive procedures;
- retention and privacy policy for request payloads;
- reconciliation for externally completed operations.
- a transactional outbox or database queue when broker handoff must survive process failure.
