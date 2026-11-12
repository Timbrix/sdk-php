<?php

declare(strict_types=1);

namespace Timbrix;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use Timbrix\Http\RetryPolicy;
use Timbrix\Http\Transport;
use Timbrix\Resources\Customers;
use Timbrix\Resources\Invoices;
use Timbrix\Resources\Products;
use Timbrix\Resources\Sat;

/**
 * Cliente de la API de Timbrix.
 *
 * Opciones: baseUrl (string), timeout (float, segundos), maxRetries (int),
 * httpClient (GuzzleHttp\ClientInterface) y sleeper (callable, solo para pruebas).
 */
final class TimbrixClient
{
    public const VERSION = '0.1.2';
    public const DEFAULT_BASE_URL = 'https://api.timbrix.mx';
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 2;

    private const KNOWN_OPTIONS = ['baseUrl', 'timeout', 'maxRetries', 'httpClient', 'sleeper'];

    public readonly Invoices $invoices;
    public readonly Customers $customers;
    public readonly Products $products;
    public readonly Sat $sat;

    private readonly Transport $transport;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, array $options = [])
    {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('La API key es obligatoria.');
        }

        $unknown = array_diff(array_keys($options), self::KNOWN_OPTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Opciones desconocidas: ' . implode(', ', array_map('strval', $unknown)) . '.'
            );
        }

        $baseUrl = $options['baseUrl'] ?? self::DEFAULT_BASE_URL;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException('baseUrl debe ser un string no vacío.');
        }

        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        if (!(is_int($timeout) || is_float($timeout)) || $timeout <= 0) {
            throw new InvalidArgumentException('timeout debe ser un número mayor a 0.');
        }

        $maxRetries = $options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES;
        if (!is_int($maxRetries) || $maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries debe ser un entero mayor o igual a 0.');
        }

        $httpClient = $options['httpClient'] ?? null;
        if ($httpClient !== null && !$httpClient instanceof ClientInterface) {
            throw new InvalidArgumentException('httpClient debe implementar GuzzleHttp\ClientInterface.');
        }

        $sleeper = $options['sleeper'] ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
        if (!is_callable($sleeper)) {
            throw new InvalidArgumentException('sleeper debe ser un callable.');
        }

        $normalizedBaseUrl = self::normalizeBaseUrl($baseUrl);
        $httpClient ??= new Client(['base_uri' => $normalizedBaseUrl, 'http_errors' => false]);

        $this->transport = new Transport(
            $httpClient,
            $apiKey,
            new RetryPolicy($maxRetries, $sleeper),
            (float) $timeout,
            $normalizedBaseUrl,
        );

        $this->invoices = new Invoices($this->transport);
        $this->customers = new Customers($this->transport);
        $this->products = new Products($this->transport);
        $this->sat = new Sat($this->transport);
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
            'invoices' => $this->invoices,
            'customers' => $this->customers,
            'products' => $this->products,
            'sat' => $this->sat,
        ];
    }

    /**
     * Capa HTTP compartida por los recursos del SDK.
     *
     * @internal
     */
    public function transport(): Transport
    {
        return $this->transport;
    }

    /**
     * Quita espacios y garantiza exactamente una "/" final, para que las rutas
     * relativas no pierdan un sufijo de path (por ejemplo, un proxy).
     */
    public static function normalizeBaseUrl(string $url): string
    {
        return rtrim(trim($url), '/') . '/';
    }
}
