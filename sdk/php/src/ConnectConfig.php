<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

use InvalidArgumentException;

final readonly class ConnectConfig
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $clientId,
        public string $privateKey,
        public string $keyId,
        public string $tokenEndpoint,
        public string $apiBaseUrl,
        public array $scopes = [],
        public string $algorithm = 'ES256',
        public int $assertionTtlSeconds = 120,
        public int $requestTimeoutSeconds = 15,
    ) {
        if ($this->clientId === '' || strlen($this->clientId) > 120) {
            throw new InvalidArgumentException('clientId is required and must not exceed 120 characters.');
        }
        if ($this->privateKey === '') {
            throw new InvalidArgumentException('privateKey is required.');
        }
        if ($this->keyId === '' || strlen($this->keyId) > 120 || ! preg_match('/^[A-Za-z0-9._-]+$/', $this->keyId)) {
            throw new InvalidArgumentException('keyId must contain only letters, numbers, dot, underscore or hyphen and must not exceed 120 characters.');
        }
        if ($this->algorithm !== 'ES256') {
            throw new InvalidArgumentException('PrymeStudy Connect v1 supports ES256 client assertions.');
        }

        $this->assertSecureEndpoint($this->tokenEndpoint, 'tokenEndpoint', false);
        $this->assertSecureEndpoint($this->apiBaseUrl, 'apiBaseUrl', true);

        if ($this->assertionTtlSeconds < 30 || $this->assertionTtlSeconds > 300) {
            throw new InvalidArgumentException('assertionTtlSeconds must be between 30 and 300 seconds.');
        }
        if ($this->requestTimeoutSeconds < 1 || $this->requestTimeoutSeconds > 120) {
            throw new InvalidArgumentException('requestTimeoutSeconds must be between 1 and 120 seconds.');
        }
        foreach ($this->scopes as $scope) {
            if (! is_string($scope) || trim($scope) === '') {
                throw new InvalidArgumentException('scopes must contain non-empty strings.');
            }
        }

        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false) {
            throw new InvalidArgumentException('privateKey must contain a valid PEM private key.');
        }
        $details = openssl_pkey_get_details($key);
        $curve = is_array($details) ? ($details['ec']['curve_name'] ?? null) : null;
        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ! in_array($curve, ['prime256v1', 'secp256r1'], true)) {
            throw new InvalidArgumentException('privateKey must be an EC P-256 (prime256v1/secp256r1) private key.');
        }
    }

    private function assertSecureEndpoint(string $value, string $name, bool $baseUrl): void
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException($name.' must be an absolute URL.');
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException($name.' must be an absolute URL with a host.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
            throw new InvalidArgumentException($name.' must not contain credentials, a query string or a fragment.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(trim((string) $parts['host'], '[]'));
        if ($scheme !== 'https' && ! ($scheme === 'http' && self::isLoopbackHost($host))) {
            throw new InvalidArgumentException($name.' must use HTTPS. HTTP is allowed only for loopback development hosts.');
        }

        if ($baseUrl && isset($parts['path']) && ! in_array($parts['path'], ['', '/'], true)) {
            throw new InvalidArgumentException($name.' must be an origin/base URL without a path.');
        }
    }

    private static function isLoopbackHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_ends_with($host, '.localhost');
    }
}
