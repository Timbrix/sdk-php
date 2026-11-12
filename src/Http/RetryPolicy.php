<?php

declare(strict_types=1);

namespace Timbrix\Http;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Política de reintentos. Regla fiscal: un POST, PUT o PATCH sin idempotencyKey nunca se reintenta,
 * porque repetir un timbrado podría duplicar un CFDI.
 *
 * @internal
 */
final class RetryPolicy
{
    /** @var callable(float): void */
    private $sleeper;

    /**
     * @param (callable(float): void)|null $sleeper Espera entre reintentos; por defecto usleep.
     */
    public function __construct(
        private readonly int $maxRetries = 2,
        ?callable $sleeper = null,
        private readonly float $baseDelay = 0.5,
        private readonly float $maxDelay = 8.0,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * @param int $attempt Número de reintentos ya hechos (0 en el primer fallo).
     */
    public function shouldRetry(
        string $method,
        bool $idempotent,
        ?ResponseInterface $response,
        ?Throwable $error,
        int $attempt,
    ): bool {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        $safeMethod = in_array(strtoupper($method), ['GET', 'DELETE'], true);
        if (!$safeMethod && !$idempotent) {
            return false;
        }

        if ($response === null) {
            return $error !== null;
        }

        $status = $response->getStatusCode();
        if ($status === 429 || $status >= 500) {
            return true;
        }

        // Única excepción a "nunca reintentar 4xx": timbrado aún en curso, con idempotencyKey.
        if ($status === 409 && $idempotent) {
            return $this->errorCode($response) === 'PAC_STAMP_PENDING';
        }

        return false;
    }

    /**
     * Segundos a esperar antes del siguiente intento.
     */
    public function delay(int $attempt, ?ResponseInterface $response): float
    {
        if ($response !== null) {
            $retryAfter = trim($response->getHeaderLine('Retry-After'));
            if ($retryAfter !== '' && ctype_digit($retryAfter)) {
                return min($this->maxDelay, (float) $retryAfter);
            }
        }

        $base = min($this->maxDelay, $this->baseDelay * (2 ** $attempt));

        return $base + $base * 0.25 * (random_int(0, 1_000_000) / 1_000_000);
    }

    public function sleep(float $seconds): void
    {
        ($this->sleeper)($seconds);
    }

    /** Lee el código de error del cuerpo y deja el stream listo para releerse. */
    private function errorCode(ResponseInterface $response): ?string
    {
        $body = $response->getBody();
        $raw = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $decoded = json_decode($raw, true);
        $code = is_array($decoded) ? ($decoded['error'] ?? null) : null;

        return is_string($code) ? $code : null;
    }
}
