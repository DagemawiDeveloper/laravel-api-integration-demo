<?php

namespace Dagemawi\RelayHub\Services;

use Dagemawi\RelayHub\Contracts\SignatureVerifier;

final class HmacSignature implements SignatureVerifier
{
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public function verify(string $payload, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload, $secret), $signature);
    }
}
