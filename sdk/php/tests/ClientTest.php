<?php

declare(strict_types=1);

namespace PrymeStudy\Connect\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PrymeStudy\Connect\ConnectClient;
use PrymeStudy\Connect\ConnectConfig;

final class ClientTest extends TestCase
{
    public function testRemoteHttpEndpointIsRejectedButLoopbackHttpIsAllowed(): void
    {
        $private = $this->privateKey();

        $this->expectException(InvalidArgumentException::class);
        new ConnectConfig(
            clientId: 'ps_test_example',
            privateKey: $private,
            keyId: 'key_test',
            tokenEndpoint: 'http://identity.example.edu/connect/token',
            apiBaseUrl: 'https://prymestudy.com',
        );
    }

    public function testLoopbackHttpCanBeUsedForLocalDevelopment(): void
    {
        $config = new ConnectConfig(
            clientId: 'ps_test_example',
            privateKey: $this->privateKey(),
            keyId: 'key_test',
            tokenEndpoint: 'http://127.0.0.1:8000/connect/v1/oauth/token',
            apiBaseUrl: 'http://localhost:8000',
        );

        self::assertSame('http://127.0.0.1:8000/connect/v1/oauth/token', $config->tokenEndpoint);
    }

    public function testClientUsesAuthAudienceCachesTokenAndSendsIdempotency(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'pct_test_example_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 300,
                'scope' => 'connect:sso.launch',
            ], JSON_THROW_ON_ERROR)),
            new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'launch_id' => 'launch-1',
                'launch_url' => 'https://auth.prymestudy.com/connect/launch/psl_example',
                'expires_at' => '2026-09-13T16:00:00+00:00',
            ], JSON_THROW_ON_ERROR)),
            new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'launch_id' => 'launch-2',
                'launch_url' => 'https://auth.prymestudy.com/connect/launch/psl_example_2',
                'expires_at' => '2026-09-13T16:01:00+00:00',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack, 'http_errors' => false, 'allow_redirects' => false]);

        $config = new ConnectConfig(
            clientId: 'ps_test_example',
            privateKey: $this->privateKey(),
            keyId: 'key_test',
            tokenEndpoint: 'https://auth.prymestudy.com/connect/v1/oauth/token',
            apiBaseUrl: 'https://prymestudy.com',
            scopes: ['connect:sso.launch'],
        );
        $client = new ConnectClient($config, $http);

        $client->createLaunch([
            'identity' => ['sub' => 'student-example'],
            'academic' => ['institution' => 'EXAMPLE_UNIVERSITY'],
        ], 'idem_first_123456');
        $client->createLaunch([
            'identity' => ['sub' => 'student-example'],
            'academic' => ['institution' => 'EXAMPLE_UNIVERSITY'],
        ], 'idem_second_123456');

        self::assertCount(3, $history, 'The second API request should reuse the cached access token.');

        $tokenRequest = $history[0]['request'];
        self::assertSame('auth.prymestudy.com', $tokenRequest->getUri()->getHost());
        parse_str((string) $tokenRequest->getBody(), $form);
        self::assertSame('ps_test_example', $form['client_id'] ?? null);

        $jwt = explode('.', (string) ($form['client_assertion'] ?? ''));
        self::assertCount(3, $jwt);
        $claims = json_decode($this->decode($jwt[1]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://auth.prymestudy.com/connect/v1/oauth/token', $claims['aud']);
        self::assertSame('ps_test_example', $claims['iss']);
        self::assertNotEmpty($claims['jti']);

        $apiRequest = $history[1]['request'];
        self::assertSame('prymestudy.com', $apiRequest->getUri()->getHost());
        self::assertSame('Bearer pct_test_example_access_token', $apiRequest->getHeaderLine('Authorization'));
        self::assertSame('idem_first_123456', $apiRequest->getHeaderLine('Idempotency-Key'));
    }

    private function privateKey(): string
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $private));
        return $private;
    }

    private function decode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        self::assertIsString($decoded);
        return $decoded;
    }
}
