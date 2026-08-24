<?php

namespace Dagemawi\RelayHub\Tests\Feature;

use Dagemawi\RelayHub\Exceptions\IdempotencyConflict;
use Dagemawi\RelayHub\Jobs\DeliverWebhook;
use Dagemawi\RelayHub\Models\WebhookDelivery;
use Dagemawi\RelayHub\Services\IntegrationClient;
use Dagemawi\RelayHub\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

final class IntegrationClientTest extends TestCase
{
    public function test_new_request_creates_one_delivery_and_enqueues_one_job(): void
    {
        Queue::fake();

        $delivery = $this->app->make(IntegrationClient::class)->dispatch(
            'invoice.paid',
            ['invoice_id' => 7842, 'amount' => 129.50],
            'invoice-paid-7842'
        );

        self::assertSame('queued', $delivery->status);
        self::assertSame('invoice-paid-7842', $delivery->idempotency_key);
        self::assertSame(1, WebhookDelivery::query()->count());

        Queue::assertPushedOn('integrations', DeliverWebhook::class, function (DeliverWebhook $job) use ($delivery): bool {
            return $job->deliveryId === $delivery->id;
        });
        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_identical_replay_returns_existing_delivery_without_enqueuing_again(): void
    {
        Queue::fake();
        $client = $this->app->make(IntegrationClient::class);

        $first = $client->dispatch(
            'invoice.paid',
            ['invoice_id' => 7842, 'meta' => ['currency' => 'USD', 'amount' => 129.50]],
            'invoice-paid-7842'
        );
        $second = $client->dispatch(
            'invoice.paid',
            ['meta' => ['amount' => 129.50, 'currency' => 'USD'], 'invoice_id' => 7842],
            'invoice-paid-7842'
        );

        self::assertSame($first->id, $second->id);
        self::assertSame(1, WebhookDelivery::query()->count());
        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_reusing_a_key_for_different_content_raises_a_domain_conflict(): void
    {
        Queue::fake();
        $client = $this->app->make(IntegrationClient::class);

        $client->dispatch('invoice.paid', ['invoice_id' => 7842], 'invoice-7842');

        $this->expectException(IdempotencyConflict::class);
        $this->expectExceptionMessage('invoice-7842');

        $client->dispatch('invoice.paid', ['invoice_id' => 9999], 'invoice-7842');
    }

    public function test_invalid_event_names_and_keys_are_rejected_before_persistence(): void
    {
        Queue::fake();
        $client = $this->app->make(IntegrationClient::class);

        try {
            $client->dispatch('Invoice Paid', [], 'valid-key');
            self::fail('Expected invalid event name exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Event names', $exception->getMessage());
        }

        try {
            $client->dispatch('invoice.paid', [], str_repeat('x', 191));
            self::fail('Expected invalid idempotency key exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Idempotency keys', $exception->getMessage());
        }

        self::assertSame(0, WebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }
}
