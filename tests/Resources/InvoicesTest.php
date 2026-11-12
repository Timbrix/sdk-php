<?php

declare(strict_types=1);

namespace Timbrix\Tests\Resources;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Timbrix\Exceptions\ApiException;
use Timbrix\Models\Invoice;
use Timbrix\Models\InvoiceCancellation;
use Timbrix\Models\InvoiceEmailSendResult;
use Timbrix\Models\InvoiceList;
use Timbrix\Models\InvoicePaymentSummary;
use Timbrix\Tests\TestCase;

final class InvoicesTest extends TestCase
{
    private function invoiceJson(): string
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/invoice.json');
        self::assertIsString($json);

        return $json;
    }

    private function requestTimeout(int $index): mixed
    {
        $entry = $this->history[$index] ?? null;
        self::assertIsArray($entry);
        $options = $entry['options'] ?? null;
        self::assertIsArray($options);

        return $options['timeout'] ?? null;
    }

    public function test_create_posts_json_and_returns_invoice(): void
    {
        $client = $this->clientWith([new Response(201, [], $this->invoiceJson())]);
        $invoice = $client->invoices->create(['type' => 'I', 'idempotencyKey' => 'order-1']);

        $request = $this->lastRequest();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/invoices', $request->getUri()->getPath());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(['type' => 'I', 'idempotencyKey' => 'order-1'], json_decode((string) $request->getBody(), true));
    }

    public function test_create_uses_stamping_timeout(): void
    {
        $client = $this->clientWith([new Response(201, [], $this->invoiceJson())]);
        $client->invoices->create(['type' => 'I']);

        $this->assertSame(120.0, $this->requestTimeout(0));
    }

    public function test_create_without_idempotency_key_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(500, [], '{"message":"boom"}'), new Response(200, [], $this->invoiceJson())]);

        try {
            $client->invoices->create(['type' => 'I']);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_create_with_empty_idempotency_key_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], $this->invoiceJson())]);

        try {
            $client->invoices->create(['type' => 'I', 'idempotencyKey' => '']);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_create_with_idempotency_key_is_retried(): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], $this->invoiceJson())]);
        $invoice = $client->invoices->create(['type' => 'I', 'idempotencyKey' => 'order-1']);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $requests = $this->requests();
        $this->assertCount(2, $requests);
        $this->assertSame((string) $requests[0]->getBody(), (string) $requests[1]->getBody());
    }

    public function test_create_with_non_string_idempotency_key_is_not_retried(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], $this->invoiceJson())]);

        try {
            $client->invoices->create(['type' => 'I', 'idempotencyKey' => 123]);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_create_with_whitespace_idempotency_key_is_retried(): void
    {
        $client = $this->clientWith([new Response(503, [], '{}'), new Response(200, [], $this->invoiceJson())]);
        $client->invoices->create(['type' => 'I', 'idempotencyKey' => '  ']);

        $this->assertCount(2, $this->requests());
    }

    public function test_list_response_instead_of_object_is_rejected(): void
    {
        $client = $this->clientWith([new Response(200, [], '[{"uuid":"a"}]')]);

        $this->expectException(ApiException::class);
        $client->invoices->get('abc');
    }

    public function test_register_payment_posts_and_uses_stamping_timeout(): void
    {
        $client = $this->clientWith([new Response(201, [], $this->invoiceJson())]);
        $invoice = $client->invoices->registerPayment(['payments' => [['invoiceUuid' => 'u', 'amount' => 10]]]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('/invoices/payments', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame(120.0, $this->requestTimeout(0));
    }

    public function test_register_payment_retry_depends_on_idempotency_key(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], $this->invoiceJson())]);
        try {
            $client->invoices->registerPayment(['amount' => 1]);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }

        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], $this->invoiceJson())]);
        $client->invoices->registerPayment(['amount' => 1, 'idempotencyKey' => 'pay-1']);
        $this->assertCount(2, $this->requests());
    }

    public function test_get_payments_returns_summary(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"invoiceUuid":"abc","total":100,"paid":40,"balance":60,"payments":[]}')]);
        $summary = $client->invoices->getPayments('abc');

        $this->assertInstanceOf(InvoicePaymentSummary::class, $summary);
        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/invoices/abc/payments', $this->lastRequest()->getUri()->getPath());
    }

    public function test_preview_pdf_returns_bytes_and_is_idempotent(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], '%PDF-bytes')]);
        $pdf = $client->invoices->previewPdf(['type' => 'I']);

        $this->assertSame('%PDF-bytes', $pdf);
        $requests = $this->requests();
        $this->assertCount(2, $requests);
        $this->assertSame('POST', $requests[1]->getMethod());
        $this->assertSame('/invoices/preview/pdf', $requests[1]->getUri()->getPath());
        $this->assertSame('*/*', $requests[1]->getHeaderLine('Accept'));
    }

    public function test_list_builds_query_and_skips_missing(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"data":[],"total":0,"page":2,"limit":20}')]);
        $list = $client->invoices->list(['status' => 'vigente', 'page' => 2]);

        $this->assertInstanceOf(InvoiceList::class, $list);
        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/invoices', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        ksort($query);
        $this->assertSame(['page' => '2', 'status' => 'vigente'], $query);
    }

    public function test_list_without_params_has_no_query(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"data":[]}')]);
        $client->invoices->list();

        $this->assertSame('', $this->lastRequest()->getUri()->getQuery());
    }

    public function test_list_ignores_unknown_params(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"data":[]}')]);
        $client->invoices->list(['q' => 'abc', 'bogus' => 'x']);

        $this->assertSame('q=abc', $this->lastRequest()->getUri()->getQuery());
    }

    public function test_get_returns_invoice(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->invoiceJson())]);
        $invoice = $client->invoices->get('d3bfbc57-44af-4390-a064-f0afab85e5df');

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('/invoices/d3bfbc57-44af-4390-a064-f0afab85e5df', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_xml_returns_bytes(): void
    {
        $client = $this->clientWith([new Response(200, [], '<cfdi/>')]);

        $this->assertSame('<cfdi/>', $client->invoices->getXml('abc'));
        $this->assertSame('/invoices/abc/xml', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_pdf_returns_bytes(): void
    {
        $client = $this->clientWith([new Response(200, [], "%PDF-1.4\n\x00\xff")]);

        $this->assertSame("%PDF-1.4\n\x00\xff", $client->invoices->getPdf('abc'));
        $this->assertSame('/invoices/abc/pdf', $this->lastRequest()->getUri()->getPath());
    }

    public function test_uuid_is_url_encoded(): void
    {
        $client = $this->clientWith([new Response(200, [], $this->invoiceJson())]);
        $client->invoices->get('a/b');

        $this->assertSame('/invoices/a%2Fb', $this->lastRequest()->getUri()->getPath());
    }

    public function test_dot_segments_and_empty_uuid_are_rejected(): void
    {
        $client = $this->clientWith([]);

        foreach (['..', '.', ''] as $bad) {
            try {
                $client->invoices->get($bad);
                $this->fail("Se esperaba InvalidArgumentException para '{$bad}'.");
            } catch (InvalidArgumentException) {
                $this->assertCount(0, $this->requests());
            }
        }
    }

    public function test_cancel_posts_body_and_returns_cancellation(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"cancellationStatus":"pendiente"}')]);
        $result = $client->invoices->cancel('abc', ['motive' => '02']);

        $this->assertInstanceOf(InvoiceCancellation::class, $result);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/invoices/abc/cancel', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['motive' => '02'], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function test_cancel_is_not_retried_on_500(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], '{}')]);

        try {
            $client->invoices->cancel('abc', ['motive' => '02']);
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }

    public function test_send_email_posts_body(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"id":"e1","status":"sent","sentTo":"a@b.mx"}')]);
        $result = $client->invoices->sendEmail('abc', ['to' => 'a@b.mx']);

        $this->assertInstanceOf(InvoiceEmailSendResult::class, $result);
        $this->assertSame('/invoices/abc/email', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['to' => 'a@b.mx'], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function test_send_email_without_recipient_sends_empty_object(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"id":"e1"}')]);
        $client->invoices->sendEmail('abc');

        $this->assertSame('{}', (string) $this->lastRequest()->getBody());
    }

    public function test_send_email_is_not_retried_on_500(): void
    {
        $client = $this->clientWith([new Response(500, [], '{}'), new Response(200, [], '{}')]);

        try {
            $client->invoices->sendEmail('abc');
            $this->fail('Se esperaba ApiException.');
        } catch (ApiException) {
            $this->assertCount(1, $this->requests());
        }
    }
}
