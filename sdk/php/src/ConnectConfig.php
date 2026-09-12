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
        if ($this->clientId === '') {
            throw new InvalidArgumentException('clientId is required.');
        }
        if ($this->privateKey === '') {
            throw new InvalidArgumentException('privateKey is required.');
        }
        if ($this->keyId === '') {
            throw new InvalidArgumentException('keyId is required.');
        }
        if ($this->algorithm !== 'ES256') {
            throw new InvalidArgumentException('PrymeStudy Connect v1 supports ES256 client assertions.');
        }

        $this->assertHttpUrl($this->tokenEndpoint, 'tokenEndpoint');
        $this->assertHttpUrl($this->apiBaseUrl, 'apiBaseUrl');

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
        if (openssl_pkey_get_private($this->privateKey) === false) {
            throw new InvalidArgumentException('privateKey must contain a valid PEM private key.');
        }
    }

    private function assertHttpUrl(string $value, string $name): void
    {
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException($name.' must be an absolute URL.');
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException($name.' must use HTTP or HTTPS.');
        }
    }
}
