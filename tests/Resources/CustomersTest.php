<?php

declare(strict_types=1);

namespace Timbrix\Tests\Resources;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Timbrix\Exceptions\ApiException;
use Timbrix\Models\Customer;
use Timbrix\Tests\TestCase;

final class CustomersTest extends TestCase
{
    /** @return array<string, mixed> */
    private function customer(): array
    {
        return [
            'id' => 'c1',
            'organizationId' => 'org-1',
            'legalName' => 'Acme SA',
            'taxId' => 'XAXX010101000',
            'taxSystem' => '601',
            'email' => 'a@acme.mx',
            'phone' => null,
            'defaultInvoiceUse' => 'G03',
            'address' => ['zip' => '01000'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'updatedAt' => '2026-01-01T00:00:00Z',
        ];
    }

    private function json(mixed $data): string
    {
        $json = json_encode($data);
        self::assertIsString($json);

        return $json;
    }

    public function test_list_returns_customers_with_query_and_org_header(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json([$this->customer()]))]);
        $customers = $client->customers->list(['q' => 'acme', 'limit' => 5, 'ignored' => 'x']);

        $request = $this->lastRequest();
        $this->assertCount(1, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/customers', $request->getUri()->getPath());
        $this->assertSame('q=acme&limit=5', $request->getUri()->getQuery());
    }

    public function test_list_without_params_sends_no_query_nor_org_header(): void
    {
        $client = $this->clientWith([new Response(200, [], '[]')]);

        $this->assertSame([], $client->customers->list());
        $this->assertSame('', $this->lastRequest()->getUri()->getQuery());
        $this->assertFalse($this->lastRequest()->hasHeader('X-Organization-Id'));
    }

    public function test_list_rejects_a_non_list_response(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->customer()))]);

        $this->expectException(ApiException::class);
        $client->customers->list();
    }

    public function test_get_encodes_the_id(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->customer()))]);
        $customer = $client->customers->get('c 1');

        $request = $this->lastRequest();
        $this->assertSame('Acme SA', $customer->legalName);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/customers/c%201', $request->getUri()->getPath());
    }

    public function test_get_rejects_an_invalid_id(): void
    {
        $client = $this->clientWith([]);

        $this->expectException(InvalidArgumentException::class);
        $client->customers->get('..');
    }

    public function test_create_posts_json(): void
    {
        $client = $this->clientWith([new Response(201, [], $this->json($this->customer()))]);
        $customer = $client->customers->create(['legalName' => 'Acme SA']);

        $request = $this->lastRequest();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/customers', $request->getUri()->getPath());
        $this->assertSame(['legalName' => 'Acme SA'], json_decode((string) $request->getBody(), true));
    }

    public function test_create_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(500, [], '{"message":"boom"}'), new Response(201, [], $this->json($this->customer()))]);

        try {
            $client->customers->create(['legalName' => 'Acme SA']);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_customer_update_uses_put(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->customer()))]);
        $client->customers->update('c1', ['email' => 'b@acme.mx']);

        $request = $this->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/customers/c1', $request->getUri()->getPath());
        $this->assertSame(['email' => 'b@acme.mx'], json_decode((string) $request->getBody(), true));
    }

    public function test_customer_warnings_are_exposed(): void
    {
        $data = $this->customer() + ['warnings' => [['code' => 'RFC_69B_PRESUNTO']]];
        $client = $this->clientWith([new Response(201, [], $this->json($data))]);
        $customer = $client->customers->create(['legalName' => 'Acme SA']);

        $this->assertIsArray($customer->warnings);
        $this->assertCount(1, $customer->warnings);
    }

    public function test_delete_returns_void_on_204(): void
    {
        $client = $this->clientWith([new Response(204)]);
        $client->customers->delete('c1');

        $request = $this->lastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/customers/c1', $request->getUri()->getPath());
    }
}
