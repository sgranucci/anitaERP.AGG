<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecuentoListadoFiltros;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecuentoListadoFiltrosTest extends TestCase
{
    public function test_limpiar_conserva_ver_todos(): void
    {
        $request = Request::create('/stock/recuento', 'GET', [
            'filtro_limpiar' => '1',
            'ver_todos_recuentos' => '1',
            'filtro_valor' => 'kandiko',
        ]);

        $filtros = RecuentoListadoFiltros::resolverDesdeRequest($request);

        $this->assertTrue($filtros['ver_todos_recuentos']);
        $this->assertSame('', $filtros['valor']);
    }

    public function test_sesion_recuerda_ver_todos_y_la_busqueda(): void
    {
        $guardado = RecuentoListadoFiltros::paraSesion([
            'modo' => RecuentoListadoFiltros::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor' => 'RC-000150',
            'busqueda' => 'RC-000150',
            'valor_hasta' => '',
            'ver_todos_recuentos' => true,
        ]);

        $filtros = RecuentoListadoFiltros::desdeSesion($guardado);

        $this->assertTrue($filtros['ver_todos_recuentos']);
        $this->assertSame('RC-000150', $filtros['valor']);
        $this->assertSame('1', RecuentoListadoFiltros::paraQueryString($filtros)['ver_todos_recuentos']);
    }

    public function test_volver_a_solo_mios_deja_el_parametro_en_cero(): void
    {
        $params = RecuentoListadoFiltros::paraQueryStringAlternarAlcance([
            'modo' => RecuentoListadoFiltros::MODO_TODOS,
            'ver_todos_recuentos' => true,
            'valor' => '',
        ], false);

        $this->assertSame('0', $params['ver_todos_recuentos']);
    }
}
