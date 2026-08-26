<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrdencompraLegajoBandejaFiltrosTest extends TestCase
{
    public function test_default_usa_primera_empresa_y_pendientes(): void
    {
        $filtros = OrdencompraLegajoBandejaFiltros::resolverDesdeRequest(
            Request::create('/compras/legajos', 'GET'),
            4
        );

        $this->assertSame(4, $filtros['empresa_id']);
        $this->assertSame('una', $filtros['empresa_scope']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES, $filtros['vista']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::TAB_TODOS, $filtros['tab']);
        $this->assertSame('', $filtros['atajo']);
        $this->assertSame(OrdencompraListadoFiltros::MODO_TODOS, $filtros['modo']);
    }

    public function test_conserva_vista_atajo_y_documentos(): void
    {
        $filtros = OrdencompraLegajoBandejaFiltros::resolverDesdeRequest(
            Request::create('/compras/legajos', 'GET', [
                'vista' => OrdencompraLegajoBandejaFiltros::VISTA_CXP,
                'tab' => OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA,
                'atajo' => OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR,
                'nro_factura' => '1234',
                'nro_com' => '88',
                'nro_op' => '55',
                'empresa_todas' => 1,
            ])
        );

        $this->assertSame(OrdencompraLegajoBandejaFiltros::VISTA_CXP, $filtros['vista']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA, $filtros['tab']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR, $filtros['atajo']);
        $this->assertSame('1234', $filtros['nro_factura']);
        $this->assertSame('88', $filtros['nro_com']);
        $this->assertSame('55', $filtros['nro_op']);
        $this->assertNull($filtros['empresa_id']);
    }

    public function test_query_string_incluye_bandeja_y_filtros_inteligentes(): void
    {
        $q = OrdencompraLegajoBandejaFiltros::paraQueryString([
            'vista' => OrdencompraLegajoBandejaFiltros::VISTA_PAGOS,
            'tab' => OrdencompraLegajoBandejaFiltros::TAB_RESTO,
            'atajo' => OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO,
            'nro_factura' => '99',
            'empresa_id' => 2,
            'empresa_scope' => 'una',
            'modo' => OrdencompraListadoFiltros::MODO_CAMPO,
            'campo' => 'nombreproveedor',
            'operador' => 'contiene',
            'valor' => 'Kandiko',
        ]);

        $this->assertSame(OrdencompraLegajoBandejaFiltros::VISTA_PAGOS, $q['vista']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::TAB_RESTO, $q['tab']);
        $this->assertSame(OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO, $q['atajo']);
        $this->assertSame('99', $q['nro_factura']);
        $this->assertSame(2, $q['empresa_id']);
        $this->assertSame(OrdencompraListadoFiltros::MODO_CAMPO, $q['filtro_modo']);
        $this->assertSame('Kandiko', $q['filtro_valor']);
    }
}
