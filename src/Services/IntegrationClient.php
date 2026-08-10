<?php

namespace Dagemawi\RelayHub\Services;

use Dagemawi\RelayHub\Jobs\DeliverWebhook;
use Dagemawi\RelayHub\Models\WebhookDelivery;
use Illuminate\Support\Str;

class IntegrationClient
{
    public function dispatch(string $event, array $payload, ?string $idempotencyKey = null): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_name' => $event,
            'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
            'payload' => $payload,
            'status' => 'queued',
            'attempts' => 0,
        ]);

        DeliverWebhook::dispatch($delivery->id)
            ->onQueue((string) config('relayhub.queue', 'integrations'));

        return $delivery;
    }
}
