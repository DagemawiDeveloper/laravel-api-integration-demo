# Contributing

1. Open an issue describing the failure mode or behavior change.
2. Create a focused branch and keep unrelated cleanup out of the diff.
3. Add or update tests that fail before the change and pass afterward.
4. Run:

```bash
composer validate --strict --no-check-publish
composer lint
composer test
```

5. Update README, architecture, idempotency, or security documentation when the public contract changes.

Never include real API credentials, customer payloads, private keys, or partner production responses in issues, fixtures, or commits.
