<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Solicitud de cancelación de un CFDI. */
final class InvoiceCancellation extends Model
{
    public readonly string $id;
    public readonly string $invoiceId;
    public readonly string $motivo;
    public readonly ?string $folioSustitucion;
    public readonly bool $requiresApproval;
    public readonly ?string $respondBy;
    /** @var string "pendiente" | "aceptada" | "rechazada" | "en_proceso" | "fallida" */
    public readonly string $cancellationStatus;
    public readonly ?string $requestedBy;
    public readonly ?string $resolvedBy;
    public readonly ?string $resolvedAt;
    public readonly string $createdAt;
    /** Código de respuesta del SAT, si el PAC lo devolvió. */
    public readonly ?string $pacCodigoRespuestaSat;
    public readonly ?string $pacCodigoRespuestaSatTexto;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->invoiceId = $this->string($a, 'invoiceId');
        $this->motivo = $this->string($a, 'motivo');
        $this->folioSustitucion = $this->nullableString($a, 'folioSustitucion');
        $this->requiresApproval = $this->bool($a, 'requiresApproval');
        $this->respondBy = $this->nullableString($a, 'respondBy');
        $this->cancellationStatus = $this->string($a, 'cancellationStatus');
        $this->requestedBy = $this->nullableString($a, 'requestedBy');
        $this->resolvedBy = $this->nullableString($a, 'resolvedBy');
        $this->resolvedAt = $this->nullableString($a, 'resolvedAt');
        $this->createdAt = $this->string($a, 'createdAt');
        $this->pacCodigoRespuestaSat = $this->nullableString($a, 'pacCodigoRespuestaSat');
        $this->pacCodigoRespuestaSatTexto = $this->nullableString($a, 'pacCodigoRespuestaSatTexto');
    }
}
