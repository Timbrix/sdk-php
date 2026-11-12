<?php

declare(strict_types=1);

namespace Timbrix\Tests\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Timbrix\Tests\TestCase;

final class TransportTest extends TestCase
{
    public function test_sends_auth_and_client_headers(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')]);
        $client->transport()->json('GET', 'invoices');

        $request = $this->lastRequest();
        $this->assertSame('test-key', $request->getHeaderLine('X-API-Key'));
        $this->assertSame('sdk', $request->getHeaderLine('X-Timbrix-Client'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertStringStartsWith('timbrix-sdk-php/', $request->getHeaderLine('User-Agent'));
    }

    public function test_raw_requests_accept_any_content_type(): void
    {
        $client = $this->clientWith([new Response(200, [], '<cfdi/>')]);
        $client->transport()->raw('GET', 'invoices/x/xml');

        $this->assertSame('*/*', $this->lastRequest()->getHeaderLine('Accept'));
    }

    public function test_resolves_relative_path_against_base_with_suffix(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')], ['baseUrl' => 'https://proxy.local/timbrix']);
        $client->transport()->json('GET', 'invoices');

        $this->assertSame('https://proxy.local/timbrix/invoices', (string) $this->lastRequest()->getUri());
    }

    public function test_path_with_colon_stays_on_base_host_and_prefix(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')], ['baseUrl' => 'https://proxy.local/timbrix/']);
        $client->transport()->json('GET', 'a:b/c', ['query' => ['x' => 1]]);

        $uri = $this->lastRequest()->getUri();
        $this->assertSame('proxy.local', $uri->getHost());
        $this->assertSame('/timbrix/a:b/c', $uri->getPath());
        $this->assertSame('x=1', $uri->getQuery());
    }

    public function test_empty_object_json_is_sent_as_braces(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')]);
        $client->transport()->json('POST', 'x', ['json' => (object) []]);

        $this->assertSame('{}', (string) $this->lastRequest()->getBody());
    }

    public function test_never_sends_organization_id_header(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}'), new Response(200, [], '{}')]);
        $client->transport()->json('GET', 'invoices', ['organizationId' => 'org_1']);
        $client->transport()->json('GET', 'invoices');

        foreach ($this->requests() as $request) {
            $this->assertFalse($request->hasHeader('X-Organization-Id'));
        }
    }

    public function test_drops_null_query_values(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')]);
        $client->transport()->json('GET', 'invoices', ['query' => ['q' => null, 'limit' => 5]]);

        $this->assertSame('limit=5', $this->lastRequest()->getUri()->getQuery());
    }

    public function test_sends_json_body(): void
    {
        $client = $this->clientWith([new Response(201, [], '{"id":"1"}')]);
        $result = $client->transport()->json('POST', 'invoices', ['json' => ['a' => 1]]);

        $this->assertSame(['id' => '1'], $result);
        $this->assertSame('{"a":1}', (string) $this->lastRequest()->getBody());
        $this->assertStringContainsString('application/json', $this->lastRequest()->getHeaderLine('Content-Type'));
    }

    public function test_204_returns_empty_array(): void
    {
        $client = $this->clientWith([new Response(204)]);
        $this->assertSame([], $client->transport()->json('DELETE', 'invoices/1'));
    }

    /**
     * @return iterable<string, array{int, class-string<TimbrixException>}>
     */
    public static function statusProvider(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '402' => [402, PaymentRequiredException::class];
        yield '403' => [403, PermissionException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ApiException::class];
        yield '418' => [418, ApiException::class];
    }

    /**
     * @param class-string<TimbrixException> $class
     *
     * @dataProvider statusProvider
     */
    public function test_maps_status_to_exception_class(int $status, string $class): void
    {
        $client = $this->clientWith([new Response($status, [], '{"message":"x"}')], ['maxRetries' => 0]);

        try {
            $client->transport()->json('GET', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (TimbrixException $e) {
            $this->assertSame($class, $e::class);
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_exception_carries_api_fields(): void
    {
        $body = '{"message":"Ya existe","error":"FOLIO_ALREADY_USED","suggestion":"Usa otro folio"}';
        $client = $this->clientWith([new Response(409, [], $body)]);

        try {
            $client->transport()->json('POST', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (TimbrixException $e) {
            $this->assertSame('Ya existe', $e->getMessage());
            $this->assertSame('FOLIO_ALREADY_USED', $e->getErrorCode());
            $this->assertSame('Usa otro folio', $e->getSuggestion());
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_joins_validation_message_array(): void
    {
        $client = $this->clientWith([new Response(422, [], '{"message":["rfc inválido","total requerido"]}')]);

        try {
            $client->transport()->json('POST', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (TimbrixException $e) {
            $this->assertSame('rfc inválido, total requerido', $e->getMessage());
            $this->assertSame(['rfc inválido', 'total requerido'], $e->getMessages());
        }
    }

    public function test_missing_message_uses_reason_phrase(): void
    {
        $client = $this->clientWith([new Response(404, [], '{}')]);

        try {
            $client->transport()->json('GET', 'invoices/1');
            $this->fail('Se esperaba una excepción.');
        } catch (TimbrixException $e) {
            $this->assertSame('Not Found', $e->getMessage());
            $this->assertSame(['Not Found'], $e->getMessages());
        }
    }

    public function test_keeps_extra_body_fields(): void
    {
        $client = $this->clientWith([new Response(402, [], '{"error":"SUBSCRIPTION_INACTIVE","reason":"trial_active"}')]);

        try {
            $client->transport()->json('POST', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (PaymentRequiredException $e) {
            $body = $e->getBody();
            $this->assertIsArray($body);
            $this->assertSame('trial_active', $body['reason']);
        }
    }

    public function test_rate_limit_exposes_retry_after(): void
    {
        $client = $this->clientWith([
            new Response(429, ['Retry-After' => '12'], '{}'),
            new Response(429, [], '{}'),
            new Response(429, ['Retry-After' => 'soon'], '{}'),
        ], ['maxRetries' => 0]);

        $retryAfter = [];
        for ($i = 0; $i < 3; ++$i) {
            try {
                $client->transport()->json('GET', 'invoices');
            } catch (RateLimitException $e) {
                $retryAfter[] = $e->getRetryAfter();
            }
        }

        $this->assertSame([12, null, null], $retryAfter);
    }

    public function test_non_json_error_body_becomes_api_exception(): void
    {
        $client = $this->clientWith([
            new Response(502, [], '<html>Bad gateway</html>'),
            new Response(500, [], ''),
        ], ['maxRetries' => 0]);

        try {
            $client->transport()->json('GET', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame('<html>Bad gateway</html>', $e->getBody());
        }

        try {
            $client->transport()->json('GET', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertNull($e->getBody());
        }
    }

    public function test_network_error_becomes_network_exception(): void
    {
        $client = $this->clientWith([new ConnectException('boom', new Request('GET', 'https://api.timbrix.mx/invoices'))], ['maxRetries' => 0]);

        try {
            $client->transport()->json('GET', 'invoices');
            $this->fail('Se esperaba una excepción.');
        } catch (NetworkException $e) {
            $this->assertSame(0, $e->getStatusCode());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_network_exception_strips_query_string_and_hides_key(): void
    {
        $message = 'cURL error 7: Failed to connect (see https://api.timbrix.mx/invoices?rfcReceptor=XAXX010101000&page=2 for details)';
        $client = $this->clientWith([new ConnectException($message, new Request('GET', 'https://api.timbrix.mx/invoices?rfcReceptor=XAXX010101000'))], ['maxRetries' => 0]);

        try {
            $client->transport()->json('GET', 'invoices', ['query' => ['rfcReceptor' => 'XAXX010101000']]);
            $this->fail('Se esperaba una excepción.');
        } catch (NetworkException $e) {
            $this->assertStringNotContainsString('rfcReceptor', $e->getMessage());
            $this->assertStringNotContainsString('XAXX010101000', $e->getMessage());
            $this->assertStringNotContainsString('test-key', $e->getMessage());
            $this->assertStringContainsString('https://api.timbrix.mx/invoices', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_debug_dumps_never_expose_the_api_key(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')]);

        $dump = print_r($client, true) . print_r($client->transport(), true);
        ob_start();
        var_dump($client, $client->transport());
        $dump .= (string) ob_get_clean();

        $this->assertStringNotContainsString('test-key', $dump);
        $this->assertStringContainsString('[redacted]', $dump);
    }

    public function test_raw_returns_bytes(): void
    {
        $client = $this->clientWith([new Response(200, [], "%PDF-1.7\n\x00\x01binary")]);
        $this->assertSame("%PDF-1.7\n\x00\x01binary", $client->transport()->raw('GET', 'invoices/1/pdf'));
    }

    public function test_raw_maps_errors(): void
    {
        $client = $this->clientWith([new Response(404, [], '{"message":"No existe"}')]);

        $this->expectException(NotFoundException::class);
        $client->transport()->raw('GET', 'invoices/1/pdf');
    }
}
