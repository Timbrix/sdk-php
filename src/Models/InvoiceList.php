<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Página de resultados del listado de facturas. */
final class InvoiceList extends Model
{
    /** @var list<InvoiceListItem> */
    public readonly array $data;
    public readonly int $total;
    public readonly int $page;
    public readonly int $limit;
    public readonly int $totalPages;

    protected function hydrate(array $a): void
    {
        $this->data = $this->models($a, 'data', InvoiceListItem::class);
        $this->total = $this->int($a, 'total');
        $this->page = $this->int($a, 'page');
        $this->limit = $this->int($a, 'limit');
        $this->totalPages = $this->int($a, 'totalPages');
    }
}
