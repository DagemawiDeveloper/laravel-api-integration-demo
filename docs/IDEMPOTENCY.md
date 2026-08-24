# Idempotency Contract

Idempotency is a business contract, not merely a unique database column.

## Request identity

RelayHub derives a SHA-256 fingerprint from:

```json
{
  "event": "<normalized event name>",
  "payload": "<recursively normalized payload>"
}
```

Associative keys are sorted recursively so semantically equivalent objects remain equivalent. List order is preserved because item order may be meaningful.

## Outbound behavior

```php
$first = $client->dispatch('invoice.paid', ['id' => 42], 'invoice-42');
$again = $client->dispatch('invoice.paid', ['id' => 42], 'invoice-42');
```

`$again` is the existing record and no second job is added to the queue.

This is rejected:

```php
$client->dispatch('invoice.refunded', ['id' => 42], 'invoice-42');
```

The key already identifies a different request, so RelayHub throws `IdempotencyConflict`.

## Inbound behavior

An authenticated first request is persisted and dispatches one domain event. An identical replay returns the original UUID with `duplicate: true`. A changed request with the same key receives HTTP `409` and does not overwrite or dispatch the original.

## Why not silently return the existing record?

If the same key is attached to changed content, silently returning the existing result makes the caller believe the changed operation succeeded. Explicit conflict surfaces the integration bug and protects downstream state.

## Concurrency

Database uniqueness remains the final arbiter under concurrent requests. Eloquent `firstOrCreate()` returns the winning row, after which RelayHub compares request fingerprints before any additional side effect occurs.
