<?php

namespace Dagemawi\RelayHub\Services;

use Dagemawi\RelayHub\Exceptions\IdempotencyConflict;
use Dagemawi\RelayHub\Jobs\DeliverWebhook;
use Dagemawi\RelayHub\Models\WebhookDelivery;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IntegrationClient
{
    public function __construct(private readonly IdempotencyFingerprint $fingerprints)
    {
    }

    public function dispatch(string $event, array $payload, ?string $idempotencyKey = null): WebhookDelivery
    {
        $event = strtolower(trim($event));
        $key = $idempotencyKey === null ? (string) Str::uuid() : trim($idempotencyKey);

        $this->assertEventIsValid($event);
        $this->assertIdempotencyKeyIsValid($key);

        $delivery = WebhookDelivery::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'uuid' => (string) Str::uuid(),
                'event_name' => $event,
                'payload' => $payload,
                'status' => 'queued',
                'attempts' => 0,
            ]
        );

        if (! $delivery->wasRecentlyCreated) {
            $requested = $this->fingerprints->make($event, $payload);
            $existing = $this->fingerprints->make($delivery->event_name, (array) $delivery->payload);

            if (! hash_equals($existing, $requested)) {
                throw IdempotencyConflict::forKey($key);
            }

            return $delivery;
        }

        DeliverWebhook::dispatch($delivery->id)
            ->onQueue((string) config('relayhub.queue', 'integrations'));

        return $delivery;
    }

    private function assertEventIsValid(string $event): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{0,189}$/', $event)) {
            throw new InvalidArgumentException('Event names must be lowercase tokens using letters, numbers, dots, underscores, or hyphens.');
        }
    }

    private function assertIdempotencyKeyIsValid(string $key): void
    {
        $length = strlen($key);

        if ($length < 1 || $length > 190) {
            throw new InvalidArgumentException('Idempotency keys must contain between 1 and 190 bytes.');
        }
    }
}
