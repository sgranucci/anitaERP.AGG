<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturaListadoFiltros;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class FacturaListadoFiltrosTest extends TestCase
{
    public function test_rango_por_defecto_reparto_es_hoy(): void
    {
        $hoy = date('Y-m-d');
        $rango = FacturaListadoFiltros::rangoFechasPorDefecto(FacturaListadoFiltros::ORDEN_REPARTO);

        $this->assertSame($hoy, $rango['fecha_desde']);
        $this->assertSame($hoy, $rango['fecha_hasta']);
    }

    public function test_rango_por_defecto_siempre_es_hoy(): void
    {
        $hoy = date('Y-m-d');
        $rangoId = FacturaListadoFiltros::rangoFechasPorDefecto(FacturaListadoFiltros::ORDEN_ID);

        $this->assertSame($hoy, $rangoId['fecha_desde']);
        $this->assertSame($hoy, $rangoId['fecha_hasta']);
    }

    public function test_primera_carga_usa_empresa_1(): void
    {
        $filtros = FacturaListadoFiltros::resolverDesdeRequest(Request::create('/ventas/factura', 'GET'));

        $this->assertSame(1, $filtros['empresa_id']);
        $this->assertSame('una', $filtros['empresa_scope']);
        $this->assertSame(['empresa_id' => 1], FacturaListadoFiltros::paraQueryStringEmpresa($filtros));
    }

    public function test_empresa_todas_no_filtra_una(): void
    {
        $filtros = FacturaListadoFiltros::resolverDesdeRequest(Request::create('/ventas/factura', 'GET', [
            'empresa_todas' => 1,
        ]));

        $this->assertSame(0, $filtros['empresa_id']);
        $this->assertSame('todas', $filtros['empresa_scope']);
        $this->assertSame(['empresa_todas' => 1], FacturaListadoFiltros::paraQueryStringEmpresa($filtros));
    }

    public function test_primera_carga_reparto_usa_hoy(): void
    {
        $hoy = date('Y-m-d');
        $filtros = FacturaListadoFiltros::resolverDesdeRequest(Request::create('/ventas/factura', 'GET'));

        $this->assertSame(FacturaListadoFiltros::ORDEN_REPARTO, $filtros['orden']);
        $this->assertSame($hoy, $filtros['fecha_desde']);
        $this->assertSame($hoy, $filtros['fecha_hasta']);
        $this->assertFalse(FacturaListadoFiltros::tieneCriteriosAplicados($filtros));
    }

    public function test_primera_carga_por_id_usa_hoy(): void
    {
        $hoy = date('Y-m-d');
        $filtros = FacturaListadoFiltros::resolverDesdeRequest(Request::create('/ventas/factura', 'GET', [
            'filtro_orden' => FacturaListadoFiltros::ORDEN_ID,
        ]));

        $this->assertSame(FacturaListadoFiltros::ORDEN_ID, $filtros['orden']);
        $this->assertSame($hoy, $filtros['fecha_desde']);
        $this->assertSame($hoy, $filtros['fecha_hasta']);
        $this->assertFalse(FacturaListadoFiltros::tieneCriteriosAplicados($filtros));
    }

    public function test_query_impresion_reparto_incluye_fechas_y_solo_copias(): void
    {
        $filtros = FacturaListadoFiltros::filtrosVacios();
        $params = FacturaListadoFiltros::paraImpresionReparto($filtros, 12, true);

        $this->assertSame(12, $params['transporteId']);
        $this->assertSame($filtros['fecha_desde'], $params['fecha_desde']);
        $this->assertSame($filtros['fecha_hasta'], $params['fecha_hasta']);
        $this->assertSame(1, $params['solo_copias']);
    }

    public function test_con_orden_conserva_el_rango_de_hoy(): void
    {
        $hoy = date('Y-m-d');
        $reparto = FacturaListadoFiltros::filtrosVacios();
        $haciaId = FacturaListadoFiltros::conOrden($reparto, FacturaListadoFiltros::ORDEN_ID);

        $this->assertSame(FacturaListadoFiltros::ORDEN_ID, $haciaId['orden']);
        $this->assertSame($hoy, $haciaId['fecha_desde']);
        $this->assertSame($hoy, $haciaId['fecha_hasta']);
    }

    public function test_con_orden_conserva_rango_custom(): void
    {
        $filtros = FacturaListadoFiltros::filtrosVacios();
        $filtros['fecha_desde'] = '2026-08-01';
        $filtros['fecha_hasta'] = '2026-08-15';

        $haciaId = FacturaListadoFiltros::conOrden($filtros, FacturaListadoFiltros::ORDEN_ID);

        $this->assertSame('2026-08-01', $haciaId['fecha_desde']);
        $this->assertSame('2026-08-15', $haciaId['fecha_hasta']);
    }
}
