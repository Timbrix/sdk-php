<?php

declare(strict_types=1);

namespace Timbrix\Resources;

use Timbrix\Models\Invoice;
use Timbrix\Models\InvoiceCancellation;
use Timbrix\Models\InvoiceEmailSendResult;
use Timbrix\Models\InvoiceList;
use Timbrix\Models\InvoicePaymentSummary;

/**
 * Facturas CFDI 4.0: timbrado, consulta, descargas, pagos, cancelación y envío por correo.
 */
final class Invoices extends Resource
{
    private const STAMP_TIMEOUT = 120.0;

    private const LIST_PARAMS = [
        'page', 'limit', 'type', 'status', 'dateFrom', 'dateTo',
        'rfcReceptor', 'environment', 'channel', 'q',
    ];

    /**
     * Crea y timbra un CFDI 4.0 con el PAC.
     *
     * Pasa `idempotencyKey` con una llave estable, por ejemplo el id de tu
     * pedido: solo así el SDK reintenta ante un fallo de red o un 5xx sin
     * riesgo de timbrar dos veces. Sin ella, esos fallos no se reintentan.
     *
     * Si la API responde `PAC_STAMP_UNCONFIRMED`, no se sabe si el PAC timbró:
     * espera el webhook `invoice.stamped` o `invoice.stamping_failed`, o
     * reintenta más tarde con la misma `idempotencyKey`.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Invoice
    {
        return Invoice::fromArray($this->object($this->transport->json('POST', 'invoices', [
            'json' => $data,
            'timeout' => self::STAMP_TIMEOUT,
            'idempotent' => $this->hasIdempotencyKey($data),
        ])));
    }

    /**
     * Registra el pago de facturas PPD y timbra su Complemento de Pago
     * (CFDI tipo "P"). Cuenta como un CFDI del plan.
     *
     * Aplica lo mismo que en `create` para `idempotencyKey` (pasa una llave
     * estable para poder reintentar) y para `PAC_STAMP_UNCONFIRMED` (espera el
     * webhook `invoice.stamped` o `invoice.stamping_failed`, o reintenta
     * después con la misma llave).
     *
     * @param array<string, mixed> $data
     */
    public function registerPayment(array $data): Invoice
    {
        return Invoice::fromArray($this->object($this->transport->json('POST', 'invoices/payments', [
            'json' => $data,
            'timeout' => self::STAMP_TIMEOUT,
            'idempotent' => $this->hasIdempotencyKey($data),
        ])));
    }

    /** Parcialidades (Complementos de Pago) y saldo de una factura PPD. */
    public function getPayments(string $uuid): InvoicePaymentSummary
    {
        return InvoicePaymentSummary::fromArray($this->object(
            $this->transport->json('GET', $this->path('invoices', $uuid, 'payments'))
        ));
    }

    /**
     * Genera el PDF de vista previa sin timbrar: no se guarda nada ni consume
     * timbres, por eso es seguro reintentarlo. Devuelve los bytes del PDF.
     *
     * @param array<string, mixed> $data Mismo cuerpo que `create`.
     */
    public function previewPdf(array $data): string
    {
        return $this->transport->raw('POST', 'invoices/preview/pdf', [
            'json' => $data,
            'idempotent' => true,
        ]);
    }

    /**
     * Lista las facturas de la organización, las más recientes primero.
     *
     * @param array<string, mixed> $params page, limit, type, status, dateFrom, dateTo,
     *                                     rfcReceptor, environment, channel, q
     */
    public function list(array $params = []): InvoiceList
    {
        $query = array_intersect_key($params, array_flip(self::LIST_PARAMS));

        return InvoiceList::fromArray($this->object($this->transport->json('GET', 'invoices', [
            'query' => $query,
        ])));
    }

    /** Obtiene una factura por su folio fiscal (UUID). */
    public function get(string $uuid): Invoice
    {
        return Invoice::fromArray($this->object($this->transport->json('GET', $this->path('invoices', $uuid))));
    }

    /** Descarga el XML timbrado (bytes). */
    public function getXml(string $uuid): string
    {
        return $this->transport->raw('GET', $this->path('invoices', $uuid, 'xml'));
    }

    /** Descarga el PDF de la factura (bytes). */
    public function getPdf(string $uuid): string
    {
        return $this->transport->raw('GET', $this->path('invoices', $uuid, 'pdf'));
    }

    /**
     * Cancela un CFDI timbrado según las reglas del SAT. Nunca se reintenta
     * automáticamente.
     *
     * Si la respuesta trae `cancellationStatus: "pendiente"`, el receptor debe
     * aceptar la cancelación; Timbrix no puede confirmarlo en tiempo real.
     * Si la API responde `PAC_CANCEL_UNCONFIRMED`, no se sabe si el SAT la
     * procesó: consulta la factura con `get()` antes de volver a intentar.
     *
     * @param array<string, mixed> $data
     */
    public function cancel(string $uuid, array $data): InvoiceCancellation
    {
        return InvoiceCancellation::fromArray($this->object($this->transport->json(
            'POST',
            $this->path('invoices', $uuid, 'cancel'),
            ['json' => $data, 'idempotent' => false],
        )));
    }

    /**
     * Envía el CFDI (XML y PDF) por correo. Si omites `to`, se usa el correo
     * del cliente registrado para el RFC receptor; sin ninguno la API
     * responde 400. No se reintenta automáticamente.
     *
     * @param array<string, mixed> $data
     */
    public function sendEmail(string $uuid, array $data = []): InvoiceEmailSendResult
    {
        return InvoiceEmailSendResult::fromArray($this->object($this->transport->json(
            'POST',
            $this->path('invoices', $uuid, 'email'),
            ['json' => $data === [] ? (object) [] : $data, 'idempotent' => false],
        )));
    }

    /** @param array<string, mixed> $data */
    private function hasIdempotencyKey(array $data): bool
    {
        $key = $data['idempotencyKey'] ?? null;

        return is_string($key) && $key !== '';
    }
}
