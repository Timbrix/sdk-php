<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Resultado de una búsqueda en un catálogo SAT. */
final class SatSearchResult extends Model
{
    /** @var list<SatCatalogEntry> */
    public readonly array $data;
    public readonly int $total;

    protected function hydrate(array $a): void
    {
        $this->data = $this->models($a, 'data', SatCatalogEntry::class);
        $this->total = $this->int($a, 'total');
    }
}
