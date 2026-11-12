<?php

declare(strict_types=1);

namespace Timbrix\Models;

/**
 * Entrada de un catálogo SAT (regímenes, usos de CFDI, claves de producto o de
 * unidad). Los campos opcionales dependen del catálogo.
 */
final class SatCatalogEntry extends Model
{
    public readonly string $codigo;
    public readonly string $descripcion;
    /** @var string|null "fisica" | "moral" | "ambas" (regímenes y usos de CFDI) */
    public readonly ?string $tipoPersona;
    /** Solo en claves de unidad. */
    public readonly ?string $nombre;
    /** Solo en claves de unidad. */
    public readonly ?string $simbolo;
    /** Solo en claves de producto o servicio. */
    public readonly ?bool $incluyeIvaTrasladado;
    /** Solo en claves de producto o servicio. */
    public readonly ?bool $incluyeIepsTrasladado;

    protected function hydrate(array $a): void
    {
        $this->codigo = $this->string($a, 'codigo');
        $this->descripcion = $this->string($a, 'descripcion');
        $this->tipoPersona = $this->nullableString($a, 'tipoPersona');
        $this->nombre = $this->nullableString($a, 'nombre');
        $this->simbolo = $this->nullableString($a, 'simbolo');
        $this->incluyeIvaTrasladado = $this->nullableBool($a, 'incluyeIvaTrasladado');
        $this->incluyeIepsTrasladado = $this->nullableBool($a, 'incluyeIepsTrasladado');
    }
}
