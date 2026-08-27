<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\MovimientoStockListadoFiltros;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class MovimientoStockListadoFiltrosTest extends TestCase
{
    public function test_request_sin_query_no_trae_filtros(): void
    {
        $request = Request::create('/stock/movimientostock', 'GET');

        $this->assertFalse(MovimientoStockListadoFiltros::requestTraeFiltros($request));
    }

    public function test_request_con_empresa_trae_filtros(): void
    {
        $request = Request::create('/stock/movimientostock', 'GET', ['empresa_id' => 2]);

        $this->assertTrue(MovimientoStockListadoFiltros::requestTraeFiltros($request));
    }

    public function test_request_con_busqueda_trae_filtros(): void
    {
        $request = Request::create('/stock/movimientostock', 'GET', ['filtro_valor' => 'TR-']);

        $this->assertTrue(MovimientoStockListadoFiltros::requestTraeFiltros($request));
    }

    public function test_query_string_conserva_empresa_y_texto(): void
    {
        $params = MovimientoStockListadoFiltros::paraQueryString([
            'empresa_id' => 2,
            'empresa_scope' => 'una',
            'deposito_id' => 1863,
            'modo' => MovimientoStockListadoFiltros::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor' => 'TOMATE',
            'valor_hasta' => '',
        ]);

        $this->assertSame(2, $params['empresa_id']);
        $this->assertSame(1863, $params['deposito_id']);
        $this->assertSame('TOMATE', $params['filtro_valor']);
    }

    public function test_clave_sesion_separa_surmar(): void
    {
        $this->assertSame(
            MovimientoStockListadoFiltros::SESSION_FILTROS,
            MovimientoStockListadoFiltros::claveSesion(false)
        );
        $this->assertSame(
            MovimientoStockListadoFiltros::SESSION_FILTROS_SURMAR,
            MovimientoStockListadoFiltros::claveSesion(true)
        );
    }
}
