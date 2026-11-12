<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Resultado del envío de una factura por correo. */
final class InvoiceEmailSendResult extends Model
{
    public readonly string $id;
    public readonly string $invoiceId;
    public readonly string $sentTo;
    /** @var string "sent" | "failed" */
    public readonly string $status;
    /** Usuario que pidió el envío; null cuando se envió con API key. */
    public readonly ?string $sentBy;
    public readonly string $createdAt;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->invoiceId = $this->string($a, 'invoiceId');
        $this->sentTo = $this->string($a, 'sentTo');
        $this->status = $this->string($a, 'status');
        $this->sentBy = $this->nullableString($a, 'sentBy');
        $this->createdAt = $this->string($a, 'createdAt');
    }
}
