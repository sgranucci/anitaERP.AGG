<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProveedorCuentacorrienteListadoFiltrosTest extends TestCase
{
    public function test_default_usa_primera_empresa_asignada(): void
    {
        $filtros = ProveedorCuentacorrienteListadoFiltros::resolverDesdeRequest(
            Request::create('/compras/listarcuentacorrienteproveedor/1', 'GET'),
            null,
            4
        );

        $this->assertSame(4, $filtros['empresa_id']);
        $this->assertSame('una', $filtros['empresa_scope']);
        $this->assertSame(ProveedorCuentacorrienteListadoFiltros::MODO_TODOS, $filtros['modo']);
    }

    public function test_todas_las_empresas_limpia_empresa_id(): void
    {
        $filtros = ProveedorCuentacorrienteListadoFiltros::resolverDesdeRequest(
            Request::create('/compras/listarcuentacorrienteproveedor/1', 'GET', [
                'empresa_todas' => 1,
            ]),
            null,
            4
        );

        $this->assertNull($filtros['empresa_id']);
        $this->assertSame('todas', $filtros['empresa_scope']);
    }

    public function test_query_string_conserva_empresa_y_filtro_texto(): void
    {
        $q = ProveedorCuentacorrienteListadoFiltros::paraQueryString([
            'empresa_id' => 2,
            'empresa_scope' => 'una',
            'modo' => ProveedorCuentacorrienteListadoFiltros::MODO_CAMPO,
            'campo' => 'fecha',
            'operador' => 'desde',
            'valor' => '01/01/2026',
        ]);

        $this->assertSame(2, $q['empresa_id']);
        $this->assertSame(ProveedorCuentacorrienteListadoFiltros::MODO_CAMPO, $q['filtro_modo']);
        $this->assertSame('fecha', $q['filtro_campo']);
        $this->assertSame('desde', $q['filtro_operador']);
        $this->assertSame('01/01/2026', $q['filtro_valor']);
        $this->assertArrayNotHasKey('empresa_todas', $q);
    }

    public function test_busqueda_rapida_fuerza_modo_todos(): void
    {
        $filtros = ProveedorCuentacorrienteListadoFiltros::resolverDesdeRequest(
            Request::create('/compras/listarcuentacorrienteproveedor/1', 'GET', [
                'filtro_busqueda_rapida' => 1,
                'filtro_valor' => 'FAC',
                'filtro_modo' => ProveedorCuentacorrienteListadoFiltros::MODO_CAMPO,
                'filtro_campo' => 'fecha',
                'empresa_todas' => 1,
            ])
        );

        $this->assertSame(ProveedorCuentacorrienteListadoFiltros::MODO_TODOS, $filtros['modo']);
        $this->assertSame('contiene', $filtros['operador']);
        $this->assertSame('FAC', $filtros['valor']);
    }
}
