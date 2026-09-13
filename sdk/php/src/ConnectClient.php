<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

final class ConnectClient
{
    private ClientInterface $http;
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly ConnectConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'timeout' => $config->requestTimeoutSeconds,
            'connect_timeout' => min(10, $config->requestTimeoutSeconds),
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createLaunch(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/connect/v1/launches', $payload, $idempotencyKey ?? self::randomIdempotencyKey());
    }

    /** @param list<array<string, mixed>> $students @return array<string, mixed> */
    public function upsertStudents(array $students, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/connect/v1/students:upsert', ['items' => $students], $idempotencyKey ?? self::randomIdempotencyKey());
    }

    /** @param list<array<string, mixed>> $courses @return array<string, mixed> */
    public function upsertCourses(array $courses, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/connect/v1/courses:upsert', ['items' => $courses], $idempotencyKey ?? self::randomIdempotencyKey());
    }

    /** @param list<array<string, mixed>> $enrollments @return array<string, mixed> */
    public function upsertEnrollments(array $enrollments, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/connect/v1/enrollments:upsert', ['items' => $enrollments], $idempotencyKey ?? self::randomIdempotencyKey());
    }

    /** @return array<string, mixed> */
    public function getSyncJob(string $jobId): array
    {
        if ($jobId === '' || str_contains($jobId, '/')) {
            throw new \InvalidArgumentException('jobId must be a non-empty opaque identifier.');
        }
        return $this->request('GET', '/connect/v1/sync-jobs/'.rawurlencode($jobId));
    }

    /**
     * Execute an authenticated Connect API request.
     *
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): array {
        if (! str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Connect API paths must start with /.');
        }

        $url = rtrim($this->config->apiBaseUrl, '/').'/'.ltrim($path, '/');
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->accessToken(),
            'User-Agent' => 'prymestudy-connect-php/1.0',
        ];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $options = [
            'headers' => $headers,
            'allow_redirects' => false,
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request(strtoupper($method), $url, $options);
        } catch (GuzzleException $e) {
            throw new ConnectException('Unable to reach PrymeStudy Connect.', 'transport_error', previous: $e);
        }

        return $this->decodeResponse($response);
    }

    public function clearAccessToken(): void
    {
        $this->accessToken = null;
        $this->accessTokenExpiresAt = 0;
    }

    private function accessToken(): string
    {
        $now = time();
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > ($now + 30)) {
            return $this->accessToken;
        }

        $assertion = $this->clientAssertion($now);
        $form = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->clientId,
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
        ];
        if ($this->config->scopes !== []) {
            $form['scope'] = implode(' ', $this->config->scopes);
        }

        try {
            $response = $this->http->request('POST', $this->config->tokenEndpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'prymestudy-connect-php/1.0',
                ],
                'form_params' => $form,
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new ConnectException('Unable to reach the PrymeStudy authorization server.', 'token_transport_error', previous: $e);
        }

        $data = $this->decodeResponse($response);
        $token = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new ConnectException('Token response did not contain a valid access_token.', 'invalid_token_response', statusCode: $response->getStatusCode());
        }

        $ttl = is_int($expiresIn) || is_numeric($expiresIn) ? max(1, (int) $expiresIn) : 300;
        $this->accessToken = $token;
        $this->accessTokenExpiresAt = $now + $ttl;
        return $token;
    }

    private function clientAssertion(int $now): string
    {
        return Es256Signer::jwt([
            'iss' => $this->config->clientId,
            'sub' => $this->config->clientId,
            'aud' => $this->config->tokenEndpoint,
            'iat' => $now,
            'exp' => $now + $this->config->assertionTtlSeconds,
            'jti' => 'jti_'.bin2hex(random_bytes(24)),
        ], $this->config->privateKey, $this->config->keyId);
    }

    /** @return array<string, mixed> */
    private function decodeResponse(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return $data;
        }

        $error = isset($data['error']) && is_array($data['error']) ? $data['error'] : [];
        throw new ConnectException(
            message: is_string($error['message'] ?? null) ? $error['message'] : sprintf('PrymeStudy Connect returned HTTP %d.', $status),
            errorCode: is_string($error['code'] ?? null) ? $error['code'] : 'connect_error',
            requestId: is_string($error['request_id'] ?? null) ? $error['request_id'] : ($response->getHeaderLine('X-Request-Id') ?: null),
            statusCode: $status,
            details: is_array($error['details'] ?? null) ? $error['details'] : [],
        );
    }

    private static function randomIdempotencyKey(): string
    {
        return 'idem_'.bin2hex(random_bytes(18));
    }
}
