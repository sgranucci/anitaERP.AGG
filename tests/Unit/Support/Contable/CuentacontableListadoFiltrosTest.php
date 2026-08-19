<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CuentacontableListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class CuentacontableListadoFiltrosTest extends TestCase
{
    public function test_default_es_arbol_en_una_empresa(): void
    {
        $filtros = CuentacontableListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/cuentacontable', 'GET'),
            null,
            4
        );

        $this->assertSame(4, $filtros['empresa_id']);
        $this->assertSame('una', $filtros['empresa_scope']);
        $this->assertSame(CuentacontableListadoFiltros::VISTA_ARBOL, $filtros['vista']);
        $this->assertFalse($filtros['mostrar_totalizadoras']);
    }

    public function test_todas_las_empresas_fuerza_vista_lista(): void
    {
        $filtros = CuentacontableListadoFiltros::resolverDesdeRequest(
            Request::create('/contable/cuentacontable', 'GET', [
                'empresa_todas' => 1,
                'vista' => 'arbol',
            ]),
            null,
            4
        );

        $this->assertNull($filtros['empresa_id']);
        $this->assertSame('todas', $filtros['empresa_scope']);
        $this->assertSame(CuentacontableListadoFiltros::VISTA_LISTA, $filtros['vista']);
    }

    public function test_query_string_conserva_empresa_y_vista_lista(): void
    {
        $q = CuentacontableListadoFiltros::paraQueryString([
            'empresa_id' => 2,
            'empresa_scope' => 'una',
            'vista' => 'lista',
            'mostrar_totalizadoras' => true,
            'valor' => 'caja',
        ]);

        $this->assertSame(2, $q['empresa_id']);
        $this->assertSame('lista', $q['vista']);
        $this->assertSame(1, $q['mostrar_totalizadoras']);
        $this->assertSame('caja', $q['filtro_valor']);
        $this->assertArrayNotHasKey('empresa_todas', $q);
    }
}
