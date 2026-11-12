<?php

declare(strict_types=1);

namespace Timbrix\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Timbrix\Exceptions\ApiException;
use Timbrix\Exceptions\AuthenticationException;
use Timbrix\Exceptions\ConflictException;
use Timbrix\Exceptions\NetworkException;
use Timbrix\Exceptions\NotFoundException;
use Timbrix\Exceptions\PaymentRequiredException;
use Timbrix\Exceptions\PermissionException;
use Timbrix\Exceptions\RateLimitException;
use Timbrix\Exceptions\TimbrixException;
use Timbrix\Exceptions\ValidationException;

/**
 * Convierte respuestas de error y fallos de transporte en excepciones tipadas.
 */
final class ErrorFactory
{
    public static function fromResponse(ResponseInterface $response): TimbrixException
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();

        $body = null;
        $message = null;
        $messages = [];
        $errorCode = null;
        $suggestion = null;

        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
                $rawMessage = $decoded['message'] ?? null;
                if (is_string($rawMessage) && $rawMessage !== '') {
                    $message = $rawMessage;
                } elseif (is_array($rawMessage)) {
                    foreach ($rawMessage as $item) {
                        if (is_string($item) && $item !== '') {
                            $messages[] = $item;
                        }
                    }
                    if ($messages !== []) {
                        $message = implode(', ', $messages);
                    }
                }
                $error = $decoded['error'] ?? null;
                $errorCode = is_string($error) && $error !== '' ? $error : null;
                $hint = $decoded['suggestion'] ?? null;
                $suggestion = is_string($hint) && $hint !== '' ? $hint : null;
            } else {
                $body = $raw;
            }
        }

        if ($message === null) {
            $reason = $response->getReasonPhrase();
            $message = $reason !== '' ? $reason : 'HTTP ' . $status;
        }

        if ($status === 429) {
            return new RateLimitException(
                $message,
                $status,
                $errorCode,
                $suggestion,
                $body,
                $messages,
                null,
                self::parseRetryAfter($response->getHeaderLine('Retry-After')),
            );
        }

        $class = match ($status) {
            400, 422 => ValidationException::class,
            401 => AuthenticationException::class,
            402 => PaymentRequiredException::class,
            403 => PermissionException::class,
            404 => NotFoundException::class,
            409 => ConflictException::class,
            default => ApiException::class,
        };

        return new $class($message, $status, $errorCode, $suggestion, $body, $messages);
    }

    public static function fromTransportError(Throwable $error): NetworkException
    {
        // Sin query string (las consultas llevan RFCs) y sin la excepción original
        // como "previous": su traza conserva el header X-API-Key.
        $message = preg_replace('#(https?://[^\\s?\\#"\'<>)]+)\\?[^\\s"\'<>)]*#i', '$1', $error->getMessage()) ?? '';

        return new NetworkException(
            $message !== '' ? $message : 'No se pudo conectar con la API de Timbrix.',
        );
    }

    private static function parseRetryAfter(string $value): ?int
    {
        $value = trim($value);

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }
}
