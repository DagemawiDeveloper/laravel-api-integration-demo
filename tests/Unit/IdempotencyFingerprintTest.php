<?php

namespace Dagemawi\RelayHub\Tests\Unit;

use Dagemawi\RelayHub\Services\IdempotencyFingerprint;
use PHPUnit\Framework\TestCase;

final class IdempotencyFingerprintTest extends TestCase
{
    public function test_associative_key_order_does_not_change_the_fingerprint(): void
    {
        $service = new IdempotencyFingerprint();

        $first = $service->make('invoice.paid', [
            'invoice' => ['id' => 42, 'currency' => 'USD'],
            'amount' => 129.50,
        ]);
        $second = $service->make('invoice.paid', [
            'amount' => 129.50,
            'invoice' => ['currency' => 'USD', 'id' => 42],
        ]);

        self::assertSame($first, $second);
    }

    public function test_event_and_list_order_are_part_of_request_identity(): void
    {
        $service = new IdempotencyFingerprint();

        self::assertNotSame(
            $service->make('invoice.paid', ['items' => [1, 2]]),
            $service->make('invoice.refunded', ['items' => [1, 2]])
        );
        self::assertNotSame(
            $service->make('invoice.paid', ['items' => [1, 2]]),
            $service->make('invoice.paid', ['items' => [2, 1]])
        );
    }
}
