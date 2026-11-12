<?php

declare(strict_types=1);

namespace Timbrix\Tests\Models;

use PHPUnit\Framework\TestCase;
use Timbrix\Models\Customer;
use Timbrix\Models\Invoice;
use Timbrix\Models\InvoiceCancellation;
use Timbrix\Models\InvoiceEmailSendResult;
use Timbrix\Models\InvoiceList;
use Timbrix\Models\InvoiceListItem;
use Timbrix\Models\InvoicePaymentSummary;
use Timbrix\Models\Product;
use Timbrix\Models\SatCatalogEntry;
use Timbrix\Models\SatSearchResult;

final class ModelTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function invoiceData(): array
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/invoice.json');
        self::assertIsString($json);
        $data = json_decode($json, true);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function test_invoice_hydrates_typed_properties(): void
    {
        $i = Invoice::fromArray($this->invoiceData());

        self::assertSame('d3bfbc57-44af-4390-a064-f0afab85e5df', $i->uuid);
        self::assertSame(116.0, $i->total);
        self::assertSame('vigente', $i->status);
        self::assertFalse($i->cancellationDesynced);
        self::assertSame('PUE', $i->paymentMethod);
        self::assertSame('sandbox', $i->environment);
    }

    public function test_unknown_fields_survive_to_array(): void
    {
        $i = Invoice::fromArray($this->invoiceData() + ['nuevoCampo' => 'x']);

        self::assertSame('x', $i->toArray()['nuevoCampo']);
        self::assertSame('x', $i->jsonSerialize()['nuevoCampo']);
    }

    public function test_missing_optionals_are_null(): void
    {
        $i = Invoice::fromArray($this->invoiceData());

        self::assertNull($i->warnings);
        self::assertNull($i->payment);
        self::assertNull($i->balance);
        self::assertNull($i->cancellationStatus);
    }

    public function test_balance_and_payment_hydrate_when_present(): void
    {
        $i = Invoice::fromArray(array_merge($this->invoiceData(), [
            'balance' => 50,
            'payment' => ['amount' => 10],
            'warnings' => [['rfc' => 'X']],
        ]));

        self::assertSame(50.0, $i->balance);
        self::assertSame(['amount' => 10], $i->payment);
        self::assertSame([['rfc' => 'X']], $i->warnings);
    }

    public function test_missing_required_keys_fall_back_without_warnings(): void
    {
        $i = Invoice::fromArray([]);

        self::assertSame('', $i->uuid);
        self::assertSame(0.0, $i->total);
        self::assertFalse($i->cancellationDesynced);
        self::assertSame([], $i->relatedCfdis);
    }

    public function test_invoice_list_wraps_items(): void
    {
        $item = ['id' => '1', 'uuid' => 'u', 'serie' => 'A', 'folio' => '1', 'total' => 10, 'status' => 'vigente'];
        $raw = ['data' => [$item, $item], 'total' => 2, 'page' => 1, 'limit' => 20, 'totalPages' => 1];
        $list = InvoiceList::fromArray($raw);

        self::assertInstanceOf(InvoiceListItem::class, $list->data[0]);
        self::assertCount(2, $list->data);
        self::assertSame(10.0, $list->data[1]->total);
        self::assertSame(2, $list->total);
        self::assertSame(1, $list->totalPages);
        self::assertSame($raw, $list->toArray());
    }

    public function test_models_are_readonly(): void
    {
        $i = Invoice::fromArray($this->invoiceData());

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $i->uuid = 'x';
    }

    public function test_invoice_cancellation_hydrates(): void
    {
        $c = InvoiceCancellation::fromArray([
            'id' => 'c1', 'invoiceId' => 'i1', 'motivo' => '02', 'requiresApproval' => true,
            'cancellationStatus' => 'pendiente', 'createdAt' => 'now', 'respondBy' => null,
        ]);

        self::assertSame('02', $c->motivo);
        self::assertTrue($c->requiresApproval);
        self::assertNull($c->respondBy);
        self::assertNull($c->pacCodigoRespuestaSat);
    }

    public function test_payment_summary_hydrates(): void
    {
        $p = InvoicePaymentSummary::fromArray([
            'total' => 100, 'paid' => 40, 'credited' => 10, 'balance' => 50,
            'currency' => 'MXN', 'installments' => [['n' => 1]],
        ]);

        self::assertSame(50.0, $p->balance);
        self::assertSame(40.0, $p->paid);
        self::assertSame([['n' => 1]], $p->installments);
    }

    public function test_email_send_result_hydrates(): void
    {
        $r = InvoiceEmailSendResult::fromArray([
            'id' => 'e1', 'invoiceId' => 'i1', 'sentTo' => 'a@b.mx', 'status' => 'sent', 'createdAt' => 'now',
        ]);

        self::assertSame('a@b.mx', $r->sentTo);
        self::assertNull($r->sentBy);
    }

    public function test_customer_hydrates(): void
    {
        $c = Customer::fromArray([
            'id' => 'c1', 'organizationId' => 'o1', 'legalName' => 'ACME', 'taxId' => 'XAXX010101000',
            'taxSystem' => '601', 'email' => 'a@b.mx', 'phone' => null, 'defaultInvoiceUse' => 'G03',
            'address' => ['zip' => '01000'], 'createdAt' => 'a', 'updatedAt' => 'b',
        ]);

        self::assertSame('ACME', $c->legalName);
        self::assertNull($c->phone);
        self::assertSame(['zip' => '01000'], $c->address);
        self::assertNull($c->warnings);
    }

    public function test_product_hydrates(): void
    {
        $p = Product::fromArray([
            'id' => 'p1', 'organizationId' => 'o1', 'livemode' => false, 'description' => 'Servicio',
            'productKey' => 81112100, 'price' => 100, 'taxIncluded' => true, 'taxability' => '02',
            'taxes' => [['type' => 'IVA']], 'localTaxes' => [], 'unitKey' => 'E48', 'unitName' => 'Servicio',
            'sku' => null, 'createdAt' => 'a', 'updatedAt' => 'b',
        ]);

        self::assertSame(81112100, $p->productKey);
        self::assertSame(100.0, $p->price);
        self::assertTrue($p->taxIncluded);
        self::assertNull($p->sku);
        self::assertSame([['type' => 'IVA']], $p->taxes);
    }

    public function test_sat_search_result_wraps_entries(): void
    {
        $raw = [
            'data' => [
                ['codigo' => 'H87', 'nombre' => 'Pieza', 'descripcion' => 'Pieza', 'simbolo' => null],
                ['codigo' => '01010101', 'descripcion' => 'x', 'incluyeIvaTrasladado' => true, 'incluyeIepsTrasladado' => false],
                ['codigo' => '601', 'descripcion' => 'General', 'tipoPersona' => 'moral'],
            ],
            'total' => 3,
        ];
        $r = SatSearchResult::fromArray($raw);

        self::assertInstanceOf(SatCatalogEntry::class, $r->data[0]);
        self::assertSame('Pieza', $r->data[0]->nombre);
        self::assertNull($r->data[0]->simbolo);
        self::assertTrue($r->data[1]->incluyeIvaTrasladado);
        self::assertFalse($r->data[1]->incluyeIepsTrasladado);
        self::assertNull($r->data[0]->incluyeIvaTrasladado);
        self::assertSame('moral', $r->data[2]->tipoPersona);
        self::assertSame(3, $r->total);
        self::assertSame($raw, $r->toArray());
    }

    public function test_constructor_cannot_be_called_again_on_an_instance(): void
    {
        $invoice = Invoice::fromArray($this->invoiceData());
        $before = $invoice->toArray();

        try {
            $invoice->__construct(['uuid' => 'otro']);
            self::fail('Se esperaba \Error.');
        } catch (\Error) {
            self::assertSame($before, $invoice->toArray());
        }
    }
}
