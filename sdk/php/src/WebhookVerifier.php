<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

final class WebhookVerifier
{
    /**
     * Verify a PrymeStudy Connect Webhook Signature v1 delivery.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, mixed>
     */
    public static function verify(
        string $rawBody,
        array $headers,
        string $publicKeyPem,
        int $toleranceSeconds = 300,
        ?string $expectedKeyId = null,
        ?int $now = null,
    ): array {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? implode(',', $value) : $value;
        }

        $version = trim((string) ($normalized['x-prymestudy-signature-version'] ?? ''));
        $eventId = trim((string) ($normalized['x-prymestudy-event-id'] ?? ''));
        $timestampRaw = trim((string) ($normalized['x-prymestudy-timestamp'] ?? ''));
        $keyId = trim((string) ($normalized['x-prymestudy-key-id'] ?? ''));
        $signature = trim((string) ($normalized['x-prymestudy-signature'] ?? ''));

        if ($version !== 'v1') {
            throw new ConnectException('Unsupported or missing webhook signature version.', 'webhook_signature_version');
        }
        if ($eventId === '' || $keyId === '' || $signature === '' || ! ctype_digit($timestampRaw)) {
            throw new ConnectException('Webhook signature headers are incomplete.', 'webhook_signature_headers');
        }
        if ($expectedKeyId !== null && ! hash_equals($expectedKeyId, $keyId)) {
            throw new ConnectException('Webhook signing key does not match the expected key.', 'webhook_key_mismatch');
        }

        $timestamp = (int) $timestampRaw;
        $clock = $now ?? time();
        if ($toleranceSeconds < 1 || abs($clock - $timestamp) > $toleranceSeconds) {
            throw new ConnectException('Webhook timestamp is outside the accepted freshness window.', 'webhook_stale');
        }

        $digest = hash('sha256', $rawBody);
        $message = "v1\n{$timestamp}\n{$eventId}\n{$digest}";
        if (! Es256Signer::verifyRaw($message, $signature, $publicKeyPem)) {
            throw new ConnectException('Webhook signature verification failed.', 'webhook_signature_invalid');
        }

        $event = json_decode($rawBody, true);
        if (! is_array($event)) {
            throw new ConnectException('Webhook body is not valid JSON.', 'webhook_invalid_json');
        }
        if (! is_string($event['id'] ?? null) || ! hash_equals($eventId, $event['id'])) {
            throw new ConnectException('Webhook event ID does not match the signed header.', 'webhook_event_id_mismatch');
        }

        return $event;
    }
}
