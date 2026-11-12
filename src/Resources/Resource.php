<?php

declare(strict_types=1);

namespace Timbrix\Resources;

use InvalidArgumentException;
use Timbrix\Exceptions\ApiException;
use Timbrix\Http\Transport;

/**
 * Base de los recursos del SDK: comparte la capa HTTP y arma rutas seguras.
 */
abstract class Resource
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    /**
     * Une segmentos codificados con "/". Rechaza segmentos vacíos y "." o "..",
     * porque rawurlencode los deja intactos y alterarían la ruta.
     */
    protected function path(string ...$segments): string
    {
        $encoded = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Segmento de ruta inválido: "' . $segment . '".');
            }
            $encoded[] = rawurlencode($segment);
        }

        return implode('/', $encoded);
    }

    /**
     * Garantiza que la respuesta sea un objeto JSON (mapa) y no una lista.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function object(array $data): array
    {
        if ($data !== [] && array_is_list($data)) {
            throw $this->unexpected($data);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Garantiza que la respuesta sea una lista y conserva solo los elementos que son objetos.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    protected function items(array $data): array
    {
        if (!array_is_list($data)) {
            throw $this->unexpected($data);
        }

        $out = [];
        foreach ($data as $item) {
            if (is_array($item) && ($item === [] || !array_is_list($item))) {
                /** @var array<string, mixed> $item */
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function unexpected(array $data): ApiException
    {
        return new ApiException(
            'Respuesta inesperada de la API.',
            200,
            null,
            null,
            json_encode($data) ?: null,
        );
    }
}
