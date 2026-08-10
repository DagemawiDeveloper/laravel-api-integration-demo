<?php

namespace Dagemawi\RelayHub\Http\Controllers;

use Dagemawi\RelayHub\Events\InboundWebhookReceived;
use Dagemawi\RelayHub\Models\InboundWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class InboundWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');

        if ($idempotencyKey === '') {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        $existing = InboundWebhook::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return response()->json([
                'accepted' => true,
                'duplicate' => true,
                'id' => $existing->uuid,
            ], 200);
        }

        $payload = $request->json()->all();
        $event = Str::of((string) $request->header('X-RelayHub-Event', 'unknown'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '-')
            ->toString();

        $webhook = InboundWebhook::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_name' => $event,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'status' => 'accepted',
        ]);

        event(new InboundWebhookReceived($webhook));

        return response()->json([
            'accepted' => true,
            'duplicate' => false,
            'id' => $webhook->uuid,
        ], 202);
    }
}
