<?php

declare(strict_types=1);

namespace Timbrix\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Timbrix\Exceptions\SignatureVerificationException;
use Timbrix\Webhook;

final class WebhookTest extends TestCase
{
    private const PAYLOAD = '{"event":"invoice.stamped","data":{"uuid":"d3bfbc57-44af-4390-a064-f0afab85e5df"}}';
    private const SECRET = 'whsec_test';

    /**
     * Vector fijo producido con Node con el mismo algoritmo que
     * apps/api/src/features/webhooks/infrastructure/webhook-delivery.service.ts
     * (createHmac('sha256', secret).update(payload).digest('hex'), prefijo "sha256=").
     */
    private const SIGNATURE = 'sha256=398aff0ef49932abe4e4f6b8e26bec2313f9d526301ffa7ec22e679a18d917ef';

    public function test_verifies_signature_generated_like_the_api(): void
    {
        $this->assertTrue(Webhook::verify(self::PAYLOAD, self::SIGNATURE, self::SECRET));
    }

    public function test_rejects_tampered_payload(): void
    {
        $this->assertFalse(Webhook::verify(self::PAYLOAD . ' ', self::SIGNATURE, self::SECRET));
    }

    public function test_rejects_wrong_secret(): void
    {
        $this->assertFalse(Webhook::verify(self::PAYLOAD, self::SIGNATURE, 'whsec_other'));
    }

    public function test_rejects_empty_secret(): void
    {
        $this->assertFalse(Webhook::verify(self::PAYLOAD, self::SIGNATURE, ''));

        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent(self::PAYLOAD, self::SIGNATURE, '');
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function invalidSignatures(): array
    {
        return [
            'null' => [null],
            'vacía' => [''],
            'sin prefijo' => [substr(self::SIGNATURE, 7)],
        ];
    }

    #[DataProvider('invalidSignatures')]
    public function test_rejects_missing_or_unprefixed_signature(?string $signature): void
    {
        $this->assertFalse(Webhook::verify(self::PAYLOAD, $signature, self::SECRET));

        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent(self::PAYLOAD, $signature, self::SECRET);
    }

    public function test_construct_event_returns_decoded_payload(): void
    {
        $event = Webhook::constructEvent(self::PAYLOAD, self::SIGNATURE, self::SECRET);

        $this->assertSame('invoice.stamped', $event['event']);
        $this->assertSame(['uuid' => 'd3bfbc57-44af-4390-a064-f0afab85e5df'], $event['data']);
    }

    public function test_construct_event_rejects_non_json_payload(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent('not json', $this->sign('not json'), self::SECRET);
    }

    #[DataProvider('nonObjectPayloads')]
    public function test_construct_event_rejects_non_object_json(string $payload): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($payload, $this->sign($payload), self::SECRET);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonObjectPayloads(): array
    {
        return ['escalar' => ['42'], 'lista' => ['[1,2]'], 'null' => ['null']];
    }

    public function test_signature_exception_has_status_zero(): void
    {
        try {
            Webhook::constructEvent(self::PAYLOAD, null, self::SECRET);
            $this->fail('Se esperaba SignatureVerificationException.');
        } catch (SignatureVerificationException $e) {
            $this->assertSame(0, $e->getStatusCode());
        }
    }

    private function sign(string $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, self::SECRET);
    }
}
