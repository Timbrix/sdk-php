<?php

declare(strict_types=1);

namespace Timbrix\Tests\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Timbrix\Exceptions\ApiException;
use Timbrix\Exceptions\ConflictException;
use Timbrix\Exceptions\NetworkException;
use Timbrix\Exceptions\ValidationException;
use Timbrix\Http\RetryPolicy;
use Timbrix\Tests\TestCase;

final class RetryPolicyTest extends TestCase
{
    private function connectError(): ConnectException
    {
        return new ConnectException('boom', new Request('GET', 'https://x'));
    }

    public function test_get_is_retried_on_503_then_succeeds(): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], '{"ok":true}')]);

        $this->assertSame(['ok' => true], $client->transport()->json('GET', 'x'));
        $this->assertCount(2, $this->requests());
        $this->assertCount(1, $this->sleeps);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function writeMethodProvider(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH'] as $method) {
            yield $method . ' sin idempotencia' => [$method, false];
            yield $method . ' con idempotencia' => [$method, true];
        }
    }

    /** @dataProvider writeMethodProvider */
    public function test_write_methods_follow_idempotency_rule(string $method, bool $idempotent): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], '{"ok":1}')]);

        if ($idempotent) {
            $this->assertSame(['ok' => 1], $client->transport()->json($method, 'x', ['idempotent' => true]));
            $this->assertCount(2, $this->requests());

            return;
        }

        try {
            $client->transport()->json($method, 'x', ['idempotent' => false]);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertCount(1, $this->requests());
    }

    public function test_delete_is_retried_on_503(): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], '{"ok":1}')]);

        $this->assertSame(['ok' => 1], $client->transport()->json('DELETE', 'x'));
        $this->assertCount(2, $this->requests());
    }

    public function test_post_without_idempotency_is_never_retried_on_500(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], '{}')]);

        try {
            $client->transport()->json('POST', 'x', ['idempotent' => false]);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
        }
        $this->assertCount(1, $this->requests());
        $this->assertSame([], $this->sleeps);
    }

    public function test_post_without_idempotency_is_never_retried_on_network_error(): void
    {
        $client = $this->clientWith([$this->connectError(), new Response(200, [], '{}')]);

        $this->expectException(NetworkException::class);
        try {
            $client->transport()->json('POST', 'x', ['idempotent' => false]);
        } finally {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_idempotent_post_is_retried_on_network_error(): void
    {
        $client = $this->clientWith([$this->connectError(), new Response(200, [], '{"ok":1}')]);

        $this->assertSame(['ok' => 1], $client->transport()->json('POST', 'x', ['idempotent' => true]));
        $this->assertCount(2, $this->requests());
    }

    public function test_stamp_pending_is_retried_with_idempotency(): void
    {
        $client = $this->clientWith([
            new Response(409, [], '{"error":"PAC_STAMP_PENDING"}'),
            new Response(200, [], '{"ok":1}'),
        ]);

        $this->assertSame(['ok' => 1], $client->transport()->json('POST', 'x', ['idempotent' => true]));
        $this->assertCount(2, $this->requests());
    }

    public function test_stamp_pending_is_not_retried_without_idempotency(): void
    {
        $client = $this->clientWith([
            new Response(409, [], '{"error":"PAC_STAMP_PENDING"}'),
            new Response(200, [], '{}'),
        ]);

        $this->expectException(ConflictException::class);
        try {
            $client->transport()->json('POST', 'x');
        } finally {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_stamp_unconfirmed_is_not_retried(): void
    {
        $client = $this->clientWith([
            new Response(409, [], '{"error":"PAC_STAMP_UNCONFIRMED","message":"revisa"}'),
            new Response(200, [], '{}'),
        ]);

        try {
            $client->transport()->json('POST', 'x', ['idempotent' => true]);
            $this->fail('Se esperaba ConflictException.');
        } catch (ConflictException $e) {
            $this->assertSame('PAC_STAMP_UNCONFIRMED', $e->getErrorCode());
            $this->assertSame('revisa', $e->getMessage());
        }
        $this->assertCount(1, $this->requests());
    }

    public function test_422_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(422, [], '{}'), new Response(200, [], '{}')]);

        try {
            $client->transport()->json('GET', 'x');
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_respects_retry_after(): void
    {
        $client = $this->clientWith([new Response(429, ['Retry-After' => '3'], '{}'), new Response(200, [], '{}')]);
        $client->transport()->json('GET', 'x');

        $this->assertSame([3.0], $this->sleeps);
    }

    public function test_retry_after_is_capped_at_max_delay(): void
    {
        $policy = new RetryPolicy();

        $this->assertSame(8.0, $policy->delay(0, new Response(429, ['Retry-After' => '60'])));
    }

    public function test_gives_up_after_max_retries(): void
    {
        $client = $this->clientWith([
            new Response(503, [], '{}'),
            new Response(503, [], '{}'),
            new Response(503, [], '{}'),
            new Response(200, [], '{}'),
        ], ['maxRetries' => 2]);

        try {
            $client->transport()->json('GET', 'x');
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertCount(3, $this->requests());
    }

    public function test_max_retries_zero_disables_retries(): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], '{}')], ['maxRetries' => 0]);

        $this->expectException(ApiException::class);
        try {
            $client->transport()->json('GET', 'x');
        } finally {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_backoff_is_capped_and_within_jitter_bounds(): void
    {
        $policy = new RetryPolicy();

        $this->assertLessThanOrEqual(8.0 * 1.25, $policy->delay(10, null));
        $first = $policy->delay(0, null);
        $this->assertGreaterThanOrEqual(0.5, $first);
        $this->assertLessThanOrEqual(0.5 * 1.25, $first);
    }
}
