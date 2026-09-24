<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorListadoFiltros;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class PagoproveedorListadoFiltrosTest extends TestCase
{
    public function test_resuelve_rango_de_fechas_desde_request(): void
    {
        $filtros = PagoproveedorListadoFiltros::resolverDesdeRequest(Request::create('/compras/pagoproveedor', 'GET', [
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-01-31',
            'empresa_todas' => 1,
        ]));

        $this->assertSame('2026-01-01', $filtros['fecha_desde']);
        $this->assertSame('2026-01-31', $filtros['fecha_hasta']);
        $this->assertTrue(PagoproveedorListadoFiltros::tieneCriteriosTexto($filtros));
        $this->assertFalse(PagoproveedorListadoFiltros::tieneCriteriosTextoParaBusqueda($filtros));
    }

    public function test_query_string_incluye_fechas(): void
    {
        $filtros = PagoproveedorListadoFiltros::filtrosVacios();
        $filtros['fecha_desde'] = '2026-03-01';
        $filtros['fecha_hasta'] = '2026-03-15';
        $filtros['empresa_scope'] = 'todas';

        $params = PagoproveedorListadoFiltros::paraQueryString($filtros);

        $this->assertSame('2026-03-01', $params['fecha_desde']);
        $this->assertSame('2026-03-15', $params['fecha_hasta']);
        $this->assertSame(1, $params['empresa_todas']);
    }

    public function test_limpiar_filtros_deja_fechas_vacias(): void
    {
        $filtros = PagoproveedorListadoFiltros::resolverDesdeRequest(Request::create('/compras/pagoproveedor', 'GET', [
            'filtro_limpiar' => 1,
            'fecha_desde' => '2026-01-01',
            'empresa_todas' => 1,
        ]));

        $this->assertSame('', $filtros['fecha_desde']);
        $this->assertSame('', $filtros['fecha_hasta']);
    }
}
