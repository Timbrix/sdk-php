<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Producto o servicio con clave del catálogo SAT. */
final class Product extends Model
{
    public readonly string $id;
    public readonly string $organizationId;
    public readonly bool $livemode;
    public readonly string $description;
    public readonly int $productKey;
    public readonly float $price;
    public readonly bool $taxIncluded;
    public readonly string $taxability;
    /** @var list<array<string, mixed>> */
    public readonly array $taxes;
    /** @var list<array<string, mixed>> */
    public readonly array $localTaxes;
    public readonly string $unitKey;
    public readonly string $unitName;
    public readonly ?string $sku;
    public readonly string $createdAt;
    public readonly string $updatedAt;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->organizationId = $this->string($a, 'organizationId');
        $this->livemode = $this->bool($a, 'livemode');
        $this->description = $this->string($a, 'description');
        $this->productKey = $this->int($a, 'productKey');
        $this->price = $this->float($a, 'price');
        $this->taxIncluded = $this->bool($a, 'taxIncluded');
        $this->taxability = $this->string($a, 'taxability');
        /** @var list<array<string, mixed>> $taxes */
        $taxes = $this->array($a, 'taxes');
        $this->taxes = $taxes;
        /** @var list<array<string, mixed>> $localTaxes */
        $localTaxes = $this->array($a, 'localTaxes');
        $this->localTaxes = $localTaxes;
        $this->unitKey = $this->string($a, 'unitKey');
        $this->unitName = $this->string($a, 'unitName');
        $this->sku = $this->nullableString($a, 'sku');
        $this->createdAt = $this->string($a, 'createdAt');
        $this->updatedAt = $this->string($a, 'updatedAt');
    }
}
