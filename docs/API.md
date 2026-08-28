# API Reference

## Health

```http
GET /api/relayhub/health
```

## Inbound webhook

```http
POST /api/relayhub/webhooks/inbound
Content-Type: application/json
X-RelayHub-Event: order.updated
X-RelayHub-Signature: <hmac sha256>
Idempotency-Key: partner-event-123
```

The signature is generated against the exact raw JSON request body.

The body must decode to a JSON object or array. Malformed JSON and scalar JSON values receive HTTP `422` and are not persisted.

### Example sender

```php
$payload = json_encode(['order_id' => 123, 'status' => 'paid']);
$signature = hash_hmac('sha256', $payload, $secret);
```

### Successful first delivery

```json
{
  "accepted": true,
  "duplicate": false,
  "id": "51b1f074-4b88-46be-b66d-91653f6bfc41"
}
```

### Duplicate delivery

```json
{
  "accepted": true,
  "duplicate": true,
  "id": "51b1f074-4b88-46be-b66d-91653f6bfc41"
}
```
