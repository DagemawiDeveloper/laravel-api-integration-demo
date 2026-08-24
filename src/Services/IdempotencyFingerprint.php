<?php

namespace Dagemawi\RelayHub\Services;

use JsonException;

final class IdempotencyFingerprint
{
    /**
     * @throws JsonException
     */
    public function make(string $event, array $payload): string
    {
        return hash('sha256', json_encode([
            'event' => $event,
            'payload' => $this->normalize($payload),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }
}
