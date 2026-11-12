<?php

declare(strict_types=1);

namespace Timbrix\Resources;

use Timbrix\Models\Customer;

/**
 * Clientes (receptores) con sus datos fiscales.
 */
final class Customers extends Resource
{
    private const LIST_PARAMS = ['q', 'limit'];

    /**
     * Lista los clientes de la organización.
     *
     * @param array<string, mixed> $params q, limit
     *
     * @return list<Customer>
     */
    public function list(array $params = []): array
    {
        $query = array_intersect_key($params, array_flip(self::LIST_PARAMS));
        $items = $this->items($this->transport->json('GET', 'customers', [
            'query' => $query,
        ]));

        return array_map(static fn (array $item): Customer => Customer::fromArray($item), $items);
    }

    /** Obtiene un cliente por su id. */
    public function get(string $id): Customer
    {
        return Customer::fromArray($this->object($this->transport->json('GET', $this->path('customers', $id))));
    }

    /**
     * Crea un cliente. Si su RFC aparece como "Presunto" en la lista 69-B,
     * `warnings` lo indica. No se reintenta automáticamente.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Customer
    {
        return Customer::fromArray($this->object($this->transport->json('POST', 'customers', [
            'json' => $data,
            'idempotent' => false,
        ])));
    }

    /**
     * Actualiza un cliente (PUT). No se reintenta automáticamente.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): Customer
    {
        return Customer::fromArray($this->object($this->transport->json('PUT', $this->path('customers', $id), [
            'json' => $data,
            'idempotent' => false,
        ])));
    }

    /** Elimina un cliente. */
    public function delete(string $id): void
    {
        $this->transport->json('DELETE', $this->path('customers', $id));
    }
}
