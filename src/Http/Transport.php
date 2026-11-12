<?php

declare(strict_types=1);

namespace Timbrix\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Timbrix\Exceptions\ApiException;
use Timbrix\TimbrixClient;

/**
 * Capa HTTP del SDK: arma las peticiones y convierte errores en excepciones tipadas.
 *
 * Opciones de cada petición: query (los null se descartan), json,
 * timeout (segundos) e idempotent (lo usa el bucle de reintentos).
 *
 * @internal
 */
final class Transport
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly ClientInterface $http,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly RetryPolicy $retry,
        private readonly float $timeout,
        string $baseUrl = TimbrixClient::DEFAULT_BASE_URL,
    ) {
        $this->baseUrl = TimbrixClient::normalizeBaseUrl($baseUrl);
    }

    /**
     * Evita que var_dump o print_r expongan la API key.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => '[redacted]',
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<array-key, mixed>
     */
    public function json(string $method, string $path, array $options = []): array
    {
        $response = $this->send($method, $path, $options, 'application/json');
        $raw = (string) $response->getBody();

        if ($response->getStatusCode() === 204 || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ApiException(
                'La API devolvió una respuesta que no es JSON.',
                $response->getStatusCode(),
                null,
                null,
                $raw,
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function raw(string $method, string $path, array $options = []): string
    {
        return (string) $this->send($method, $path, $options, '*/*')->getBody();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $path, array $options, string $accept): ResponseInterface
    {
        $headers = [
            'X-API-Key' => $this->apiKey,
            'X-Timbrix-Client' => 'sdk',
            'Accept' => $accept,
            'User-Agent' => 'timbrix-sdk-php/' . TimbrixClient::VERSION,
        ];

        $timeout = $options['timeout'] ?? $this->timeout;
        $requestOptions = [
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => is_int($timeout) || is_float($timeout) ? (float) $timeout : $this->timeout,
        ];

        $query = $options['query'] ?? null;
        if (is_array($query)) {
            $query = array_filter($query, static fn ($value): bool => $value !== null);
            if ($query !== []) {
                $requestOptions['query'] = $query;
            }
        }

        if (array_key_exists('json', $options)) {
            $requestOptions['json'] = $options['json'];
        }

        $url = $this->baseUrl . ltrim($path, '/');
        $idempotent = ($options['idempotent'] ?? false) === true;

        for ($attempt = 0;; $attempt++) {
            try {
                $response = $this->http->request($method, $url, $requestOptions);
            } catch (GuzzleException $e) {
                if ($this->retry->shouldRetry($method, $idempotent, null, $e, $attempt)) {
                    $this->retry->sleep($this->retry->delay($attempt, null));

                    continue;
                }

                throw ErrorFactory::fromTransportError($e);
            }

            if ($response->getStatusCode() < 400) {
                return $response;
            }

            if ($this->retry->shouldRetry($method, $idempotent, $response, null, $attempt)) {
                $this->retry->sleep($this->retry->delay($attempt, $response));

                continue;
            }

            throw ErrorFactory::fromResponse($response);
        }
    }
}
