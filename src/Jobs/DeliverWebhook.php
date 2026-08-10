<?php

namespace Dagemawi\RelayHub\Jobs;

use Dagemawi\RelayHub\Models\WebhookDelivery;
use Dagemawi\RelayHub\Services\HmacSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(public readonly int $deliveryId)
    {
        $this->tries = max(1, (int) config('relayhub.outbound.max_attempts', 5));
    }

    public function backoff(): array
    {
        return array_map('intval', (array) config('relayhub.outbound.backoff_seconds', [10, 30, 120, 300]));
    }

    public function handle(HmacSignature $signer): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($this->deliveryId);
        $url = (string) config('relayhub.outbound.url');
        $secret = (string) config('relayhub.outbound.secret');

        if ($url === '' || $secret === '') {
            throw new RuntimeException('RelayHub outbound URL and secret must be configured.');
        }

        $body = json_encode([
            'id' => $delivery->uuid,
            'event' => $delivery->event_name,
            'created_at' => $delivery->created_at?->toIso8601String(),
            'data' => $delivery->payload,
        ], JSON_THROW_ON_ERROR);

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
        ])->save();

        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('relayhub.outbound.timeout', 10))
            ->connectTimeout((int) config('relayhub.outbound.connect_timeout', 3))
            ->withHeaders([
                'X-RelayHub-Event' => $delivery->event_name,
                'X-RelayHub-Delivery' => $delivery->uuid,
                'X-RelayHub-Signature' => $signer->sign($body, $secret),
                'Idempotency-Key' => $delivery->idempotency_key,
            ])
            ->withBody($body, 'application/json')
            ->post($url);

        $delivery->forceFill([
            'response_code' => $response->status(),
            'response_body' => $this->safeResponseBody($response->body()),
            'status' => $response->successful() ? 'delivered' : 'failed',
            'delivered_at' => $response->successful() ? now() : null,
        ])->save();

        if (! $response->successful()) {
            throw new RuntimeException("Webhook delivery failed with HTTP {$response->status()}.");
        }
    }

    public function failed(Throwable $exception): void
    {
        WebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => 'dead_letter',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
    }

    private function safeResponseBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded)
            ? $decoded
            : ['raw' => mb_substr($body, 0, 5000)];
    }
}
