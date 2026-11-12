<?php

declare(strict_types=1);

namespace Timbrix\Resources;

use Timbrix\Models\SatCatalogEntry;
use Timbrix\Models\SatSearchResult;

/**
 * Catálogos del SAT (rutas públicas).
 */
final class Sat extends Resource
{
    /**
     * Regímenes fiscales.
     *
     * @param array<string, mixed> $params tipoPersona
     *
     * @return list<SatCatalogEntry>
     */
    public function regimenes(array $params = []): array
    {
        return $this->catalog('sat/regimenes-fiscales', $params, ['tipoPersona']);
    }

    /**
     * Usos de CFDI.
     *
     * @param array<string, mixed> $params tipoPersona, regimen
     *
     * @return list<SatCatalogEntry>
     */
    public function usosCfdi(array $params = []): array
    {
        return $this->catalog('sat/usos-cfdi', $params, ['tipoPersona', 'regimen']);
    }

    /**
     * Busca claves de producto o servicio (c_ClaveProdServ).
     *
     * @param array<string, mixed> $params q, limit
     */
    public function searchClavesProdServ(array $params = []): SatSearchResult
    {
        return $this->search('sat/claves-prod-serv', $params);
    }

    /**
     * Busca claves de unidad de medida (c_ClaveUnidad).
     *
     * @param array<string, mixed> $params q, limit
     */
    public function searchClavesUnidad(array $params = []): SatSearchResult
    {
        return $this->search('sat/claves-unidad', $params);
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $allowed
     *
     * @return list<SatCatalogEntry>
     */
    private function catalog(string $path, array $params, array $allowed): array
    {
        $items = $this->items($this->transport->json('GET', $path, [
            'query' => array_intersect_key($params, array_flip($allowed)),
        ]));

        return array_map(static fn (array $item): SatCatalogEntry => SatCatalogEntry::fromArray($item), $items);
    }

    /** @param array<string, mixed> $params */
    private function search(string $path, array $params): SatSearchResult
    {
        return SatSearchResult::fromArray($this->object($this->transport->json('GET', $path, [
            'query' => array_intersect_key($params, array_flip(['q', 'limit'])),
        ])));
    }
}
