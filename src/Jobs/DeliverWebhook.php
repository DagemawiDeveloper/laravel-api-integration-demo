<?php

namespace Dagemawi\RelayHub\Jobs;

use Dagemawi\RelayHub\Contracts\SignatureVerifier;
use Dagemawi\RelayHub\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
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

    public function handle(SignatureVerifier $signer): void
    {
        $delivery = WebhookDelivery::query()->findOrFail($this->deliveryId);

        if ($delivery->status === 'delivered') {
            return;
        }

        $url = trim((string) config('relayhub.outbound.url'));
        $secret = (string) config('relayhub.outbound.secret');

        if (! $this->isAllowedUrl($url) || $secret === '') {
            throw new RuntimeException('RelayHub requires a valid HTTPS outbound URL and signing secret.');
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
            'last_error' => null,
        ])->save();

        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('relayhub.outbound.timeout', 10)))
                ->connectTimeout(max(1, (int) config('relayhub.outbound.connect_timeout', 3)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-RelayHub-Event' => $delivery->event_name,
                    'X-RelayHub-Delivery' => $delivery->uuid,
                    'X-RelayHub-Signature' => $signer->sign($body, $secret),
                    'Idempotency-Key' => $delivery->idempotency_key,
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'response_code' => null,
                'response_body' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }

        $successful = $response->successful();
        $error = $successful ? null : "Webhook delivery failed with HTTP {$response->status()}.";

        $delivery->forceFill([
            'response_code' => $response->status(),
            'response_body' => $this->responseMetadata($response),
            'status' => $successful ? 'delivered' : 'failed',
            'delivered_at' => $successful ? now() : null,
            'last_error' => $error,
        ])->save();

        if (! $successful) {
            throw new RuntimeException($error);
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

    private function isAllowedUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function responseMetadata(Response $response): array
    {
        $body = $response->body();

        return [
            'bytes' => strlen($body),
            'sha256' => hash('sha256', $body),
            'content_type' => mb_substr((string) $response->header('Content-Type'), 0, 190),
        ];
    }
}
