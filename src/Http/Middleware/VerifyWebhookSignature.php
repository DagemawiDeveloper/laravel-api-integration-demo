<?php

namespace Dagemawi\RelayHub\Http\Middleware;

use Closure;
use Dagemawi\RelayHub\Contracts\SignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyWebhookSignature
{
    public function __construct(private readonly SignatureVerifier $signer)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('relayhub.inbound_secret');
        $signature = (string) $request->header('X-RelayHub-Signature', '');

        if (! $this->signer->verify($request->getContent(), $signature, $secret)) {
            return new JsonResponse([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
