<?php

declare(strict_types=1);

namespace PrymeStudy\Connect;

use RuntimeException;
use Throwable;

final class ConnectException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        public readonly int $statusCode = 0,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
