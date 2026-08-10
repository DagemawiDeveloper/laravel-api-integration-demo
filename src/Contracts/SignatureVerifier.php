<?php

namespace Dagemawi\RelayHub\Contracts;

interface SignatureVerifier
{
    public function sign(string $payload, string $secret): string;

    public function verify(string $payload, string $signature, string $secret): bool;
}
