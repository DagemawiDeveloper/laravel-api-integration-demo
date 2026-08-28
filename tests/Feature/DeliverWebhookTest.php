<?php

namespace Dagemawi\RelayHub\Tests\Feature;

use Dagemawi\RelayHub\Jobs\DeliverWebhook;
use Dagemawi\RelayHub\Models\WebhookDelivery;
use Dagemawi\RelayHub\Services\HmacSignature;
use Dagemawi\RelayHub\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeliverWebhookTest extends TestCase
{
    public function test_successful_delivery_updates_observable_state_and_sends_authenticated_headers(): void
    {
        Http::fake([
            'https://partner.example/*' => Http::response(
                ['accepted' => true, 'private_token' => 'must-not-be-persisted'],
                202,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $delivery = $this->delivery();
        $signer = $this->app->make(HmacSignature::class);

        (new DeliverWebhook($delivery->id))->handle($signer);

        $delivery->refresh();

        self::assertSame('delivered', $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertSame(202, $delivery->response_code);
        self::assertNotNull($delivery->delivered_at);
        self::assertNull($delivery->last_error);
        self::assertSame('application/json', $delivery->response_body['content_type']);
        self::assertArrayHasKey('bytes', $delivery->response_body);
        self::assertArrayHasKey('sha256', $delivery->response_body);
        self::assertArrayNotHasKey('private_token', $delivery->response_body);

        Http::assertSent(function (Request $request) use ($delivery, $signer): bool {
            $signature = $request->header('X-RelayHub-Signature')[0] ?? '';

            return $request->url() === 'https://partner.example/webhooks'
                && ($request->header('X-RelayHub-Event')[0] ?? '') === 'invoice.paid'
                && ($request->header('X-RelayHub-Delivery')[0] ?? '') === $delivery->uuid
                && ($request->header('Idempotency-Key')[0] ?? '') === $delivery->idempotency_key
                && $signer->verify($request->body(), $signature, 'outbound-test-secret');
        });
    }

    public function test_non_success_response_is_recorded_and_rethrown_for_queue_retry(): void
    {
        Http::fake([
            'https://partner.example/*' => Http::response('upstream unavailable', 503),
        ]);

        $delivery = $this->delivery();
        $job = new DeliverWebhook($delivery->id);

        try {
            $job->handle($this->app->make(HmacSignature::class));
            self::fail('Expected delivery failure exception.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('HTTP 503', $exception->getMessage());
        }

        $delivery->refresh();

        self::assertSame('failed', $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertSame(503, $delivery->response_code);
        self::assertStringContainsString('HTTP 503', (string) $delivery->last_error);
        self::assertArrayHasKey('sha256', $delivery->response_body);
        self::assertArrayNotHasKey('raw', $delivery->response_body);
    }

    public function test_already_delivered_job_does_not_send_the_webhook_again(): void
    {
        Http::fake();
        $delivery = $this->delivery();
        $delivery->forceFill([
            'status' => 'delivered',
            'attempts' => 1,
            'delivered_at' => now(),
        ])->save();

        (new DeliverWebhook($delivery->id))->handle($this->app->make(HmacSignature::class));

        $delivery->refresh();

        self::assertSame('delivered', $delivery->status);
        self::assertSame(1, $delivery->attempts);
        Http::assertNothingSent();
    }

    public function test_terminal_queue_failure_moves_delivery_to_dead_letter(): void
    {
        $delivery = $this->delivery();
        $job = new DeliverWebhook($delivery->id);

        $job->failed(new RuntimeException('All configured attempts were exhausted.'));

        $delivery->refresh();

        self::assertSame('dead_letter', $delivery->status);
        self::assertSame('All configured attempts were exhausted.', $delivery->last_error);
    }

    public function test_invalid_or_non_https_endpoint_is_rejected_before_http_request(): void
    {
        Http::fake();
        config()->set('relayhub.outbound.url', 'http://partner.example/webhooks');
        $delivery = $this->delivery();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTPS');

        try {
            (new DeliverWebhook($delivery->id))->handle($this->app->make(HmacSignature::class));
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_retry_policy_comes_from_configuration(): void
    {
        config()->set('relayhub.outbound.max_attempts', 4);
        config()->set('relayhub.outbound.backoff_seconds', [5, 20, 60]);

        $job = new DeliverWebhook(123);

        self::assertSame(4, $job->tries);
        self::assertSame([5, 20, 60], $job->backoff());
    }

    private function delivery(): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'uuid' => '00000000-0000-4000-8000-000000000042',
            'event_name' => 'invoice.paid',
            'idempotency_key' => 'invoice-paid-7842',
            'payload' => [
                'invoice_id' => 7842,
                'amount' => 129.50,
                'currency' => 'USD',
            ],
            'status' => 'queued',
            'attempts' => 0,
        ]);
    }
}
