<?php

declare(strict_types=1);

namespace Timbrix;

use JsonException;
use Timbrix\Exceptions\SignatureVerificationException;

/**
 * Verificación de la firma de los webhooks de Timbrix. No usa la red.
 *
 * La API firma el body exacto que envía (HMAC-SHA256, cabecera `X-Webhook-Signature`
 * con el formato `sha256=<hex>`), así que hay que pasar el body crudo recibido,
 * sin decodificarlo ni reserializarlo.
 */
final class Webhook
{
    private const PREFIX = 'sha256=';

    /**
     * Indica si la firma corresponde al payload crudo. Nunca lanza excepciones.
     */
    public static function verify(string $payload, ?string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === null || !str_starts_with($signature, self::PREFIX)) {
            return false;
        }

        $expected = self::PREFIX . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Verifica la firma y devuelve el evento decodificado.
     *
     * @return array<string, mixed>
     *
     * @throws SignatureVerificationException Si la firma no es válida o el payload no es un objeto JSON.
     */
    public static function constructEvent(string $payload, ?string $signature, string $secret): array
    {
        if (!self::verify($payload, $signature, $secret)) {
            throw new SignatureVerificationException('La firma del webhook no es válida.');
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SignatureVerificationException(
                'El payload del webhook no es un objeto JSON válido.',
                previous: $e,
            );
        }

        if (!is_array($event) || ($event !== [] && array_is_list($event))) {
            throw new SignatureVerificationException('El payload del webhook no es un objeto JSON válido.');
        }

        /** @var array<string, mixed> $event */
        return $event;
    }
}
