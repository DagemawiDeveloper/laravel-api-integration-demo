<?php

use Dagemawi\RelayHub\Http\Controllers\InboundWebhookController;
use Dagemawi\RelayHub\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'relayhub',
        'time' => now()->toIso8601String(),
    ]);
});

Route::post('/webhooks/inbound', [InboundWebhookController::class, 'store'])
    ->middleware(VerifyWebhookSignature::class)
    ->name('relayhub.webhooks.inbound');
