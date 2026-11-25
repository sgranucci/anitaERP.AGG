<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobantesPdfLoteFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class ComprobantesPdfLoteFiltrosTest extends TestCase
{
    public function test_acepta_varios_proveedores_y_rechaza_rango_invertido(): void
    {
        $request = Request::create('/compras/comprobantes-pdf-lote', 'GET', [
            'empresa_id' => 3,
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-01-31',
            'proveedor_ids' => '10, 12,10,0',
            'nro_cliente' => ' 86691-1 ',
        ]);

        $filtros = ComprobantesPdfLoteFiltros::resolverDesdeRequest($request);

        $this->assertSame([10, 12], $filtros['proveedor_ids']);
        $this->assertSame(['fac'], $filtros['tipos']);
        $this->assertSame('86691-1', $filtros['nro_cliente']);
        $this->assertTrue(ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros));
        $this->assertSame('86691-1', ComprobantesPdfLoteFiltros::paraQueryString($filtros)['nro_cliente']);

        $filtros['fecha_hasta'] = '2025-12-01';
        $this->assertFalse(ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros));
    }

    public function test_acumula_tipos_marcados_y_claves(): void
    {
        $request = Request::create('/compras/comprobantes-pdf-lote', 'GET', [
            'empresa_id' => 3,
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-01-31',
            'proveedor_ids' => '10',
            'tipos_enviados' => 1,
            'tipos' => ['fac', 'oc', 'COM', 'opp', 'opa', 'xx'],
            'claves' => ['fac:4', 'oc:0', 'opa:9', 'no'],
        ]);

        $filtros = ComprobantesPdfLoteFiltros::resolverDesdeRequest($request);

        $this->assertSame(['fac', 'oc', 'com', 'opp', 'opa'], $filtros['tipos']);
        $this->assertSame(['fac:4', 'opa:9'], ComprobantesPdfLoteFiltros::clavesDesdeRequest($request));

        $vacio = Request::create('/compras/comprobantes-pdf-lote', 'GET', [
            'tipos_enviados' => 1,
            'empresa_id' => 3,
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-01-31',
            'proveedor_ids' => '10',
        ]);
        $sinTipos = ComprobantesPdfLoteFiltros::resolverDesdeRequest($vacio);
        $this->assertSame([], $sinTipos['tipos']);
        $this->assertFalse(ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($sinTipos));
    }
}
