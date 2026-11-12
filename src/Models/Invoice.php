<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Factura (CFDI) registrada en Timbrix. */
final class Invoice extends Model
{
    public readonly string $id;
    public readonly string $uuid;
    /** Canal de origen; null si se creó antes de registrarlo. */
    public readonly ?string $channel;
    /** @var string|null "sandbox" | "production" */
    public readonly ?string $environment;
    /** @var string "vigente" | "cancelado" */
    public readonly string $status;
    /** @var string|null "pendiente" | "rechazada" | "en_proceso" */
    public readonly ?string $cancellationStatus;
    public readonly bool $cancellationDesynced;
    /** @var string "I" | "E" | "T" | "P" */
    public readonly string $type;
    public readonly string $series;
    public readonly string $folioNumber;
    public readonly float $total;
    public readonly string $date;
    public readonly string $xml;
    public readonly string $rfcReceptor;
    /** @var list<array<string, mixed>> */
    public readonly array $relatedCfdis;
    /** @var list<array<string, mixed>> */
    public readonly array $substitutedBy;
    public readonly string $createdAt;
    public readonly ?string $paymentMethod;
    /** Saldo pendiente de una factura PPD; null en las demás. */
    public readonly ?float $balance;
    /**
     * Solo en complementos de pago (type "P").
     *
     * @var array<string, mixed>|null
     */
    public readonly ?array $payment;
    /**
     * Advertencias de la lista 69-B, solo al timbrar.
     *
     * @var list<array<string, mixed>>|null
     */
    public readonly ?array $warnings;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->uuid = $this->string($a, 'uuid');
        $this->channel = $this->nullableString($a, 'channel');
        $this->environment = $this->nullableString($a, 'environment');
        $this->status = $this->string($a, 'status');
        $this->cancellationStatus = $this->nullableString($a, 'cancellationStatus');
        $this->cancellationDesynced = $this->bool($a, 'cancellationDesynced');
        $this->type = $this->string($a, 'type');
        $this->series = $this->string($a, 'series');
        $this->folioNumber = $this->string($a, 'folioNumber');
        $this->total = $this->float($a, 'total');
        $this->date = $this->string($a, 'date');
        $this->xml = $this->string($a, 'xml');
        $this->rfcReceptor = $this->string($a, 'rfcReceptor');
        /** @var list<array<string, mixed>> $related */
        $related = $this->array($a, 'relatedCfdis');
        $this->relatedCfdis = $related;
        /** @var list<array<string, mixed>> $substituted */
        $substituted = $this->array($a, 'substitutedBy');
        $this->substitutedBy = $substituted;
        $this->createdAt = $this->string($a, 'createdAt');
        $this->paymentMethod = $this->nullableString($a, 'paymentMethod');
        $this->balance = $this->nullableFloat($a, 'balance');
        /** @var array<string, mixed>|null $payment */
        $payment = $this->nullableArray($a, 'payment');
        $this->payment = $payment;
        /** @var list<array<string, mixed>>|null $warnings */
        $warnings = $this->nullableArray($a, 'warnings');
        $this->warnings = $warnings;
    }
}
