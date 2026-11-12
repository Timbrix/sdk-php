<?php

declare(strict_types=1);

namespace Timbrix\Models;

/**
 * Base de los modelos de respuesta. Guarda el array crudo completo, así que
 * `toArray()` conserva los campos que la API agregue y el SDK aún no declare.
 */
abstract class Model implements \JsonSerializable
{
    /** @var array<string, mixed> */
    private readonly array $raw;

    /**
     * @param array<string, mixed> $attributes
     */
    final public function __construct(array $attributes)
    {
        $this->raw = $attributes;
        $this->hydrate($attributes);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    /**
     * Asigna las propiedades `public readonly` de la subclase.
     *
     * @param array<string, mixed> $a
     */
    abstract protected function hydrate(array $a): void;

    /**
     * @param array<string, mixed> $a
     */
    protected function string(array $a, string $key): string
    {
        return $this->nullableString($a, $key) ?? '';
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function nullableString(array $a, string $key): ?string
    {
        $v = $a[$key] ?? null;

        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function float(array $a, string $key): float
    {
        return $this->nullableFloat($a, $key) ?? 0.0;
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function nullableFloat(array $a, string $key): ?float
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function int(array $a, string $key): int
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (int) $v : 0;
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function bool(array $a, string $key): bool
    {
        return $this->nullableBool($a, $key) ?? false;
    }

    /**
     * @param array<string, mixed> $a
     */
    protected function nullableBool(array $a, string $key): ?bool
    {
        $v = $a[$key] ?? null;

        return is_bool($v) ? $v : null;
    }

    /**
     * @param array<string, mixed> $a
     * @return array<mixed>
     */
    protected function array(array $a, string $key): array
    {
        return $this->nullableArray($a, $key) ?? [];
    }

    /**
     * @param array<string, mixed> $a
     * @return array<mixed>|null
     */
    protected function nullableArray(array $a, string $key): ?array
    {
        $v = $a[$key] ?? null;

        return is_array($v) ? $v : null;
    }

    /**
     * @template T of Model
     * @param array<string, mixed> $a
     * @param class-string<T> $class
     * @return list<T>
     */
    protected function models(array $a, string $key, string $class): array
    {
        $out = [];
        foreach ($this->array($a, $key) as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = $class::fromArray($item);
            }
        }

        return $out;
    }
}
