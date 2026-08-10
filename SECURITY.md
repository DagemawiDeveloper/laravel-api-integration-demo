# Security

RelayHub demonstrates the following integration controls:

- HMAC-SHA256 signatures for inbound and outbound requests.
- Timing-safe signature comparison via `hash_equals`.
- Secrets sourced from environment configuration rather than committed files.
- Idempotency keys to reduce replay/duplicate side effects.
- Queue isolation for external calls.
- Bounded HTTP connection and request timeouts.
- Response body truncation/normalization before persistence.
- No production credentials, customer data or proprietary application code in this repository.

Production deployments should additionally enforce TLS, secret rotation, source allow-listing where practical, monitoring/alerting, and application-level authorization appropriate to the partner integration.
