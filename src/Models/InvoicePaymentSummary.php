<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Resumen de pagos de una factura PPD. */
final class InvoicePaymentSummary extends Model
{
    public readonly float $total;
    /** Suma de los complementos de pago vigentes y en timbrado. */
    public readonly float $paid;
    /** Suma de las notas de crédito vigentes relacionadas con la factura. */
    public readonly float $credited;
    /** `total - credited - paid`, nunca negativo. */
    public readonly float $balance;
    public readonly string $currency;
    /** @var list<array<string, mixed>> */
    public readonly array $installments;

    protected function hydrate(array $a): void
    {
        $this->total = $this->float($a, 'total');
        $this->paid = $this->float($a, 'paid');
        $this->credited = $this->float($a, 'credited');
        $this->balance = $this->float($a, 'balance');
        $this->currency = $this->string($a, 'currency');
        /** @var list<array<string, mixed>> $installments */
        $installments = $this->array($a, 'installments');
        $this->installments = $installments;
    }
}
