<?php

declare(strict_types=1);

namespace PrymeStudy\Connect\Tests;

use PHPUnit\Framework\TestCase;
use PrymeStudy\Connect\Es256Signer;
use PrymeStudy\Connect\WebhookVerifier;

final class CryptoTest extends TestCase
{
    public function testJwtUsesValidEs256Signature(): void
    {
        [$private, $public] = $this->keyPair();
        $jwt = Es256Signer::jwt([
            'iss' => 'ps_test_example',
            'sub' => 'ps_test_example',
            'aud' => 'https://auth.example.test/token',
            'iat' => 100,
            'exp' => 200,
            'jti' => 'jti_test',
        ], $private, 'key_test');

        $parts = explode('.', $jwt);
        self::assertCount(3, $parts);
        self::assertTrue(Es256Signer::verifyRaw($parts[0].'.'.$parts[1], $parts[2], $public));

        $header = json_decode($this->decode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ES256', $header['alg']);
        self::assertSame('key_test', $header['kid']);
    }

    public function testWebhookVerifierAcceptsValidDeliveryAndRejectsTampering(): void
    {
        [$private, $public] = $this->keyPair();
        $body = json_encode(['id' => 'evt_test_1', 'type' => 'student.updated', 'data' => ['ok' => true]], JSON_THROW_ON_ERROR);
        $timestamp = 1_800_000_000;
        $message = "v1\n{$timestamp}\nevt_test_1\n".hash('sha256', $body);
        $signature = Es256Signer::signRaw($message, $private);
        $headers = [
            'X-PrymeStudy-Signature-Version' => 'v1',
            'X-PrymeStudy-Event-Id' => 'evt_test_1',
            'X-PrymeStudy-Timestamp' => (string) $timestamp,
            'X-PrymeStudy-Key-Id' => 'whk_test',
            'X-PrymeStudy-Signature' => $signature,
        ];

        $event = WebhookVerifier::verify($body, $headers, $public, 300, 'whk_test', $timestamp);
        self::assertSame('student.updated', $event['type']);

        $this->expectException(\PrymeStudy\Connect\ConnectException::class);
        WebhookVerifier::verify($body.' ', $headers, $public, 300, 'whk_test', $timestamp);
    }

    /** @return array{0:string,1:string} */
    private function keyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $private));
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        return [$private, $details['key']];
    }

    private function decode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        self::assertIsString($decoded);
        return $decoded;
    }
}
