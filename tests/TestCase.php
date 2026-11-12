<?php

declare(strict_types=1);

namespace Timbrix\Tests;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use Timbrix\TimbrixClient;

abstract class TestCase extends BaseTestCase
{
    /** @var ArrayObject<int, array<string, mixed>> */
    protected ArrayObject $history;

    /** @var list<float> Esperas que el SDK habría hecho entre reintentos. */
    protected array $sleeps = [];

    /**
     * @param list<mixed> $responses Respuestas o excepciones para el MockHandler.
     * @param array<string, mixed> $options Opciones del cliente (sobrescriben las de prueba).
     */
    protected function clientWith(array $responses, array $options = []): TimbrixClient
    {
        $history = new ArrayObject();
        $this->history = $history;
        $this->sleeps = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new TimbrixClient('test-key', array_merge([
            'httpClient' => new Client(['handler' => $stack]),
            'sleeper' => function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        ], $options));
    }

    /** @return list<RequestInterface> */
    protected function requests(): array
    {
        $requests = [];
        foreach ($this->history as $entry) {
            $request = $entry['request'];
            assert($request instanceof RequestInterface);
            $requests[] = $request;
        }

        return $requests;
    }

    protected function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $this->assertNotEmpty($requests, 'No se hizo ninguna petición.');

        return $requests[array_key_last($requests)];
    }
}
