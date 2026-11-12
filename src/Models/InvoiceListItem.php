<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Fila del listado de facturas (nombres de campo del listado de la API). */
final class InvoiceListItem extends Model
{
    public readonly string $id;
    public readonly string $uuid;
    public readonly ?string $channel;
    public readonly string $serie;
    public readonly string $folio;
    /** @var string "I" | "E" | "T" | "P" */
    public readonly string $tipoComprobante;
    public readonly string $rfcReceptor;
    public readonly string $moneda;
    public readonly float $total;
    /** @var string "vigente" | "cancelado" */
    public readonly string $status;
    /** @var list<array<string, mixed>> */
    public readonly array $relatedCfdis;
    /** @var list<array<string, mixed>> */
    public readonly array $substitutedBy;
    public readonly string $createdAt;
    public readonly ?string $paymentMethod;
    public readonly ?float $balance;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->uuid = $this->string($a, 'uuid');
        $this->channel = $this->nullableString($a, 'channel');
        $this->serie = $this->string($a, 'serie');
        $this->folio = $this->string($a, 'folio');
        $this->tipoComprobante = $this->string($a, 'tipoComprobante');
        $this->rfcReceptor = $this->string($a, 'rfcReceptor');
        $this->moneda = $this->string($a, 'moneda');
        $this->total = $this->float($a, 'total');
        $this->status = $this->string($a, 'status');
        /** @var list<array<string, mixed>> $related */
        $related = $this->array($a, 'relatedCfdis');
        $this->relatedCfdis = $related;
        /** @var list<array<string, mixed>> $substituted */
        $substituted = $this->array($a, 'substitutedBy');
        $this->substitutedBy = $substituted;
        $this->createdAt = $this->string($a, 'createdAt');
        $this->paymentMethod = $this->nullableString($a, 'paymentMethod');
        $this->balance = $this->nullableFloat($a, 'balance');
    }
}
