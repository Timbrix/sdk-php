<?php

declare(strict_types=1);

namespace Timbrix\Models;

/** Cliente (receptor) con sus datos fiscales. */
final class Customer extends Model
{
    public readonly string $id;
    public readonly string $organizationId;
    public readonly string $legalName;
    public readonly string $taxId;
    public readonly string $taxSystem;
    public readonly string $email;
    public readonly ?string $phone;
    public readonly string $defaultInvoiceUse;
    /**
     * Domicilio fiscal del cliente.
     *
     * @var array<string, mixed>
     */
    public readonly array $address;
    public readonly string $createdAt;
    public readonly string $updatedAt;
    /**
     * Solo al crear o cambiar el RFC, y solo si es "Presunto" en la lista 69-B.
     *
     * @var list<array<string, mixed>>|null
     */
    public readonly ?array $warnings;

    protected function hydrate(array $a): void
    {
        $this->id = $this->string($a, 'id');
        $this->organizationId = $this->string($a, 'organizationId');
        $this->legalName = $this->string($a, 'legalName');
        $this->taxId = $this->string($a, 'taxId');
        $this->taxSystem = $this->string($a, 'taxSystem');
        $this->email = $this->string($a, 'email');
        $this->phone = $this->nullableString($a, 'phone');
        $this->defaultInvoiceUse = $this->string($a, 'defaultInvoiceUse');
        /** @var array<string, mixed> $address */
        $address = $this->array($a, 'address');
        $this->address = $address;
        $this->createdAt = $this->string($a, 'createdAt');
        $this->updatedAt = $this->string($a, 'updatedAt');
        /** @var list<array<string, mixed>>|null $warnings */
        $warnings = $this->nullableArray($a, 'warnings');
        $this->warnings = $warnings;
    }
}
