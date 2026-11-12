<?php

declare(strict_types=1);

namespace Timbrix\Tests\Resources;

use GuzzleHttp\Psr7\Response;
use Timbrix\Exceptions\ApiException;
use Timbrix\Models\Product;
use Timbrix\Tests\TestCase;

final class ProductsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function product(): array
    {
        return [
            'id' => 'p1',
            'organizationId' => 'org-1',
            'livemode' => false,
            'description' => 'Servicio',
            'productKey' => 80141600,
            'price' => 100.5,
            'taxIncluded' => false,
            'taxability' => '02',
            'taxes' => [],
            'localTaxes' => [],
            'unitKey' => 'E48',
            'unitName' => 'Servicio',
            'sku' => null,
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

    public function test_list_returns_products_with_filters(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json([$this->product()]))]);
        $products = $client->products->list(
            ['description' => 'serv', 'sku' => 'A-1', 'productKey' => 80141600, 'ignored' => 'x'],
        );

        $request = $this->lastRequest();
        $this->assertCount(1, $products);
        $this->assertInstanceOf(Product::class, $products[0]);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/products', $request->getUri()->getPath());
        $this->assertSame('description=serv&sku=A-1&productKey=80141600', $request->getUri()->getQuery());
    }

    public function test_list_rejects_a_non_list_response(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->product()))]);

        $this->expectException(ApiException::class);
        $client->products->list();
    }

    public function test_get_returns_product(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->product()))]);
        $product = $client->products->get('p1');

        $request = $this->lastRequest();
        $this->assertSame(80141600, $product->productKey);
        $this->assertSame('/products/p1', $request->getUri()->getPath());
    }

    public function test_create_posts_json_and_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(500, [], '{"message":"boom"}'), new Response(201, [], $this->json($this->product()))]);

        try {
            $client->products->create(['description' => 'Servicio']);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/products', $request->getUri()->getPath());
        $this->assertSame(['description' => 'Servicio'], json_decode((string) $request->getBody(), true));
    }

    public function test_create_returns_product(): void
    {
        $client = $this->clientWith([new Response(201, [], $this->json($this->product()))]);

        $this->assertInstanceOf(Product::class, $client->products->create(['description' => 'Servicio']));
    }

    public function test_update_uses_put(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->json($this->product()))]);
        $client->products->update('p1', ['price' => 120]);

        $request = $this->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/products/p1', $request->getUri()->getPath());
        $this->assertSame(['price' => 120], json_decode((string) $request->getBody(), true));
    }

    public function test_delete_returns_void_on_204(): void
    {
        $client = $this->clientWith([new Response(204)]);
        $client->products->delete('p1');

        $request = $this->lastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/products/p1', $request->getUri()->getPath());
    }
}
