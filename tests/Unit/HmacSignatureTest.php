<?php

namespace Dagemawi\RelayHub\Tests\Unit;

use Dagemawi\RelayHub\Services\HmacSignature;
use PHPUnit\Framework\TestCase;

class HmacSignatureTest extends TestCase
{
    public function test_it_signs_and_verifies_payloads(): void
    {
        $service = new HmacSignature();
        $payload = '{"order_id":123}';
        $secret = 'test-secret';

        $signature = $service->sign($payload, $secret);

        $this->assertTrue($service->verify($payload, $signature, $secret));
        $this->assertFalse($service->verify('{"order_id":999}', $signature, $secret));
    }

    public function test_empty_signatures_are_rejected(): void
    {
        $service = new HmacSignature();

        $this->assertFalse($service->verify('payload', '', 'secret'));
        $this->assertFalse($service->verify('payload', 'signature', ''));
    }
}
