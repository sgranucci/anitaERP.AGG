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
        ]);

        $filtros = ComprobantesPdfLoteFiltros::resolverDesdeRequest($request);

        $this->assertSame([10, 12], $filtros['proveedor_ids']);
        $this->assertTrue(ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros));

        $filtros['fecha_hasta'] = '2025-12-01';
        $this->assertFalse(ComprobantesPdfLoteFiltros::tieneCriteriosAplicados($filtros));
    }
}
