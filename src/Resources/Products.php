<?php

declare(strict_types=1);

namespace Timbrix\Resources;

use Timbrix\Models\Product;

/**
 * Productos y servicios con claves del catálogo SAT.
 */
final class Products extends Resource
{
    private const LIST_PARAMS = ['description', 'sku', 'productKey'];

    /**
     * Lista los productos de la organización.
     *
     * @param array<string, mixed> $params description, sku, productKey
     *
     * @return list<Product>
     */
    public function list(array $params = []): array
    {
        $query = array_intersect_key($params, array_flip(self::LIST_PARAMS));
        $items = $this->items($this->transport->json('GET', 'products', [
            'query' => $query,
        ]));

        return array_map(static fn (array $item): Product => Product::fromArray($item), $items);
    }

    /** Obtiene un producto por su id. */
    public function get(string $id): Product
    {
        return Product::fromArray($this->object($this->transport->json('GET', $this->path('products', $id))));
    }

    /**
     * Crea un producto o servicio. No se reintenta automáticamente.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Product
    {
        return Product::fromArray($this->object($this->transport->json('POST', 'products', [
            'json' => $data,
            'idempotent' => false,
        ])));
    }

    /**
     * Actualiza un producto (PUT). No se reintenta automáticamente.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Product
    {
        return Product::fromArray($this->object($this->transport->json('PUT', $this->path('products', $id), [
            'json' => $data,
            'idempotent' => false,
        ])));
    }

    /** Elimina un producto. */
    public function delete(string $id): void
    {
        $this->transport->json('DELETE', $this->path('products', $id));
    }
}
