<?php

declare(strict_types=1);

namespace Timbrix\Tests\Resources;

use Timbrix\Exceptions\ApiException;
use Timbrix\Resources\Resource;
use Timbrix\Tests\TestCase;

final class ResourceTest extends TestCase
{
    private function probe(): ResourceProbe
    {
        return new ResourceProbe($this->clientWith([])->transport());
    }

    public function test_object_returns_maps_and_empty_arrays(): void
    {
        $probe = $this->probe();

        $this->assertSame(['a' => 1], $probe->obj(['a' => 1]));
        $this->assertSame([], $probe->obj([]));
    }

    public function test_object_rejects_a_list(): void
    {
        $this->expectException(ApiException::class);
        $this->probe()->obj([['a' => 1]]);
    }

    public function test_items_rejects_a_map(): void
    {
        $this->expectException(ApiException::class);
        $this->probe()->list(['a' => 1]);
    }

    public function test_items_keeps_only_object_items(): void
    {
        $out = $this->probe()->list([['a' => 1], 'x', 5, [1, 2], null, ['b' => 2]]);

        $this->assertSame([['a' => 1], ['b' => 2]], $out);
    }

    public function test_items_accepts_empty_list(): void
    {
        $this->assertSame([], $this->probe()->list([]));
    }
}

final class ResourceProbe extends Resource
{
    /**
     * @param array<array-key, mixed> $d
     *
     * @return array<string, mixed>
     */
    public function obj(array $d): array
    {
        return $this->object($d);
    }

    /**
     * @param array<array-key, mixed> $d
     *
     * @return list<array<string, mixed>>
     */
    public function list(array $d): array
    {
        return $this->items($d);
    }
}
