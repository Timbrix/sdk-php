<?php

declare(strict_types=1);

namespace Timbrix\Tests\Resources;

use GuzzleHttp\Psr7\Response;
use Timbrix\Models\SatCatalogEntry;
use Timbrix\Models\SatSearchResult;
use Timbrix\Tests\TestCase;

final class SatTest extends TestCase
{
    public function test_regimenes_lists_entries_with_query(): void
    {
        $client = $this->clientWith([new Response(200, [], '[{"codigo":"601","descripcion":"General","tipoPersona":"moral"}]')]);
        $entries = $client->sat->regimenes(['tipoPersona' => 'moral', 'ignored' => 'x']);

        $request = $this->lastRequest();
        $this->assertCount(1, $entries);
        $this->assertInstanceOf(SatCatalogEntry::class, $entries[0]);
        $this->assertSame('601', $entries[0]->codigo);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/sat/regimenes-fiscales', $request->getUri()->getPath());
        $this->assertSame('tipoPersona=moral', $request->getUri()->getQuery());
        $this->assertFalse($request->hasHeader('X-Organization-Id'));
    }

    public function test_usos_cfdi_sends_both_filters(): void
    {
        $client = $this->clientWith([new Response(200, [], '[{"codigo":"G03","descripcion":"Gastos en general"}]')]);
        $entries = $client->sat->usosCfdi(['tipoPersona' => 'fisica', 'regimen' => '612']);

        $request = $this->lastRequest();
        $this->assertSame('G03', $entries[0]->codigo);
        $this->assertSame('/sat/usos-cfdi', $request->getUri()->getPath());
        $this->assertSame('tipoPersona=fisica&regimen=612', $request->getUri()->getQuery());
    }

    public function test_sat_search_wraps_entries(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"data":[{"codigo":"80141600","descripcion":"Ventas"}],"total":1}')]);
        $result = $client->sat->searchClavesProdServ(['q' => 'venta', 'limit' => 10]);

        $request = $this->lastRequest();
        $this->assertInstanceOf(SatSearchResult::class, $result);
        $this->assertSame(1, $result->total);
        $this->assertCount(1, $result->data);
        $this->assertSame('80141600', $result->data[0]->codigo);
        $this->assertSame('/sat/claves-prod-serv', $request->getUri()->getPath());
        $this->assertSame('q=venta&limit=10', $request->getUri()->getQuery());
    }

    public function test_search_claves_unidad_without_params(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"data":[],"total":0}')]);
        $result = $client->sat->searchClavesUnidad();

        $request = $this->lastRequest();
        $this->assertSame(0, $result->total);
        $this->assertSame('/sat/claves-unidad', $request->getUri()->getPath());
        $this->assertSame('', $request->getUri()->getQuery());
    }
}
