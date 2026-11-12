<?php

declare(strict_types=1);

namespace Timbrix\Exceptions;

use Throwable;

/** Estado 429: demasiadas peticiones. */
class RateLimitException extends TimbrixException
{
    /**
     * @param array<array-key, mixed>|string|null $body
     * @param list<string> $messages
     */
    public function __construct(
        string $message,
        int $statusCode = 429,
        ?string $errorCode = null,
        ?string $suggestion = null,
        array|string|null $body = null,
        array $messages = [],
        ?Throwable $previous = null,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode, $errorCode, $suggestion, $body, $messages, $previous);
    }

    /** Segundos de espera sugeridos por el header Retry-After, o null si no vino o no es un entero. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
