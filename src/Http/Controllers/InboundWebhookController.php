<?php

namespace Dagemawi\RelayHub\Http\Controllers;

use Dagemawi\RelayHub\Events\InboundWebhookReceived;
use Dagemawi\RelayHub\Models\InboundWebhook;
use Dagemawi\RelayHub\Services\IdempotencyFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use JsonException;

class InboundWebhookController extends Controller
{
    public function __construct(private readonly IdempotencyFingerprint $fingerprints)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 190) {
            return response()->json([
                'message' => 'Idempotency-Key header must contain between 1 and 190 bytes.',
            ], 422);
        }

        $event = strtolower(trim((string) $request->header('X-RelayHub-Event', '')));

        if (! preg_match('/^[a-z0-9][a-z0-9._-]{0,189}$/', $event)) {
            return response()->json([
                'message' => 'X-RelayHub-Event must be a lowercase event token.',
            ], 422);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json([
                'message' => 'Webhook body must be valid JSON containing an object or array.',
            ], 422);
        }

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Webhook body must be valid JSON containing an object or array.',
            ], 422);
        }

        $webhook = InboundWebhook::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'uuid' => (string) Str::uuid(),
                'event_name' => $event,
                'payload' => $payload,
                'status' => 'accepted',
            ]
        );

        if (! $webhook->wasRecentlyCreated) {
            $requested = $this->fingerprints->make($event, $payload);
            $existing = $this->fingerprints->make($webhook->event_name, (array) $webhook->payload);

            if (! hash_equals($existing, $requested)) {
                return response()->json([
                    'message' => 'Idempotency key was already used for different request content.',
                    'code' => 'idempotency_conflict',
                ], 409);
            }

            return response()->json([
                'accepted' => true,
                'duplicate' => true,
                'id' => $webhook->uuid,
            ], 200);
        }

        event(new InboundWebhookReceived($webhook));

        return response()->json([
            'accepted' => true,
            'duplicate' => false,
            'id' => $webhook->uuid,
        ], 202);
    }
}
