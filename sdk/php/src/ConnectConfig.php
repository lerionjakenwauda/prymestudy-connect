<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

use InvalidArgumentException;

final readonly class ConnectConfig
{
    /**
     * @param list<string> $scopes
     */
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

        if (! filter_var($this->tokenEndpoint, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('tokenEndpoint must be an absolute URL.');
        }

        if (! filter_var($this->apiBaseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('apiBaseUrl must be an absolute URL.');
        }

        if ($this->assertionTtlSeconds < 30 || $this->assertionTtlSeconds > 300) {
            throw new InvalidArgumentException('assertionTtlSeconds must be between 30 and 300 seconds.');
        }

        if ($this->requestTimeoutSeconds < 1 || $this->requestTimeoutSeconds > 120) {
            throw new InvalidArgumentException('requestTimeoutSeconds must be between 1 and 120 seconds.');
        }
    }
}
