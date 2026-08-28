<?php

namespace Dagemawi\RelayHub\Tests\Feature;

use Dagemawi\RelayHub\Events\InboundWebhookReceived;
use Dagemawi\RelayHub\Models\InboundWebhook;
use Dagemawi\RelayHub\Services\HmacSignature;
use Dagemawi\RelayHub\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

final class InboundWebhookTest extends TestCase
{
    public function test_first_delivery_is_accepted_and_dispatched_once(): void
    {
        Event::fake([InboundWebhookReceived::class]);
        $body = json_encode(['customer_id' => 42, 'status' => 'active'], JSON_THROW_ON_ERROR);

        $response = $this->postWebhook($body, 'customer.updated', 'partner-customer-42');

        $response
            ->assertStatus(202)
            ->assertJson([
                'accepted' => true,
                'duplicate' => false,
            ]);

        self::assertSame(1, InboundWebhook::query()->count());
        Event::assertDispatchedTimes(InboundWebhookReceived::class, 1);
    }

    public function test_identical_duplicate_returns_existing_record_without_dispatching_twice(): void
    {
        Event::fake([InboundWebhookReceived::class]);
        $body = json_encode([
            'customer_id' => 42,
            'profile' => ['country' => 'ET', 'active' => true],
        ], JSON_THROW_ON_ERROR);

        $first = $this->postWebhook($body, 'customer.updated', 'partner-customer-42');
        $second = $this->postWebhook($body, 'customer.updated', 'partner-customer-42');

        $first->assertStatus(202);
        $second
            ->assertStatus(200)
            ->assertJson([
                'accepted' => true,
                'duplicate' => true,
                'id' => $first->json('id'),
            ]);

        self::assertSame(1, InboundWebhook::query()->count());
        Event::assertDispatchedTimes(InboundWebhookReceived::class, 1);
    }

    public function test_reused_key_with_changed_content_returns_conflict(): void
    {
        Event::fake([InboundWebhookReceived::class]);

        $firstBody = json_encode(['customer_id' => 42, 'status' => 'active'], JSON_THROW_ON_ERROR);
        $changedBody = json_encode(['customer_id' => 42, 'status' => 'suspended'], JSON_THROW_ON_ERROR);

        $this->postWebhook($firstBody, 'customer.updated', 'partner-customer-42')
            ->assertStatus(202);

        $this->postWebhook($changedBody, 'customer.updated', 'partner-customer-42')
            ->assertStatus(409)
            ->assertJson([
                'code' => 'idempotency_conflict',
            ]);

        self::assertSame(1, InboundWebhook::query()->count());
        Event::assertDispatchedTimes(InboundWebhookReceived::class, 1);
    }

    public function test_invalid_signature_is_rejected_before_persistence(): void
    {
        Event::fake([InboundWebhookReceived::class]);
        $body = json_encode(['customer_id' => 42], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/relayhub/webhooks/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RELAYHUB_EVENT' => 'customer.updated',
                'HTTP_X_RELAYHUB_SIGNATURE' => 'invalid',
                'HTTP_IDEMPOTENCY_KEY' => 'partner-customer-42',
            ],
            $body
        );

        $response->assertStatus(401);
        self::assertSame(0, InboundWebhook::query()->count());
        Event::assertNotDispatched(InboundWebhookReceived::class);
    }

    public function test_malformed_or_scalar_json_is_rejected_before_persistence(): void
    {
        Event::fake([InboundWebhookReceived::class]);

        foreach (['{"customer_id":', '"customer-42"'] as $body) {
            $this->postWebhook($body, 'customer.updated', 'partner-customer-42')
                ->assertStatus(422)
                ->assertJson([
                    'message' => 'Webhook body must be valid JSON containing an object or array.',
                ]);
        }

        self::assertSame(0, InboundWebhook::query()->count());
        Event::assertNotDispatched(InboundWebhookReceived::class);
    }

    public function test_missing_or_invalid_request_identity_is_rejected(): void
    {
        $body = json_encode(['customer_id' => 42], JSON_THROW_ON_ERROR);

        $this->postWebhook($body, 'customer.updated', '')
            ->assertStatus(422);
        $this->postWebhook($body, 'Customer Updated', 'partner-customer-42')
            ->assertStatus(422);

        self::assertSame(0, InboundWebhook::query()->count());
    }

    private function postWebhook(string $body, string $event, string $idempotencyKey): TestResponse
    {
        $signature = $this->app->make(HmacSignature::class)->sign(
            $body,
            (string) config('relayhub.inbound_secret')
        );

        return $this->call(
            'POST',
            '/api/relayhub/webhooks/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RELAYHUB_EVENT' => $event,
                'HTTP_X_RELAYHUB_SIGNATURE' => $signature,
                'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            ],
            $body
        );
    }
}
