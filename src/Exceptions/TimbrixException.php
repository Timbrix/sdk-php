<?php

declare(strict_types=1);

namespace Timbrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base de todos los errores del SDK.
 *
 * Sin respuesta HTTP (errores de red, firmas de webhook) el estado es 0 y el body null.
 */
abstract class TimbrixException extends RuntimeException
{
    /**
     * @param array<array-key, mixed>|string|null $body Body de la respuesta: array si era JSON, el texto crudo si no, null si estaba vacío.
     * @param list<string> $messages Mensajes individuales (por ejemplo, uno por campo inválido).
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?string $errorCode = null,
        private readonly ?string $suggestion = null,
        private readonly array|string|null $body = null,
        private readonly array $messages = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /** Estado HTTP, o 0 si no hubo respuesta. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Código de error de la API (por ejemplo, FOLIO_ALREADY_USED). */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Sugerencia de la API para resolver el error. */
    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    public function getBody(): array|string|null
    {
        return $this->body;
    }

    /**
     * @return list<string>
     */
    public function getMessages(): array
    {
        return $this->messages === [] ? [$this->getMessage()] : $this->messages;
    }
}
