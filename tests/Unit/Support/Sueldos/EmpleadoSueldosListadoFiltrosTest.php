<?php

namespace Tests\Unit\Support\Sueldos;

use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
use PHPUnit\Framework\TestCase;

class EmpleadoSueldosListadoFiltrosTest extends TestCase
{
    public function test_la_vista_no_pisa_estado_ni_empresa(): void
    {
        $base = array_merge(EmpleadoSueldosListadoFiltros::filtrosVacios(), [
            'estado' => EmpleadoEstados::BAJA,
            'empresa_id' => 7,
            'empresa_scope' => 'una',
        ]);

        $fusion = EmpleadoSueldosListadoFiltros::fusionarDesdeVista($base, [
            'modo' => 'qbe',
            'estado' => EmpleadoEstados::ACTIVO,
            'empresa_id' => 99,
            'empresa_scope' => 'todas',
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => false,
                    'criterios' => [[
                        'campo' => 'nombre',
                        'op' => 'contiene',
                        'valor' => 'ana',
                        'valor_hasta' => '',
                    ]],
                ]],
            ],
        ]);

        $this->assertSame(EmpleadoEstados::BAJA, $fusion['estado']);
        $this->assertSame(7, $fusion['empresa_id']);
        $this->assertSame('una', $fusion['empresa_scope']);
        $this->assertSame('qbe', $fusion['modo']);
        $this->assertTrue(EmpleadoSueldosListadoFiltros::tieneCriteriosTexto($fusion));
    }

    public function test_limpiar_conserva_los_filtros_externos_y_quita_el_qbe(): void
    {
        $base = array_merge(EmpleadoSueldosListadoFiltros::filtrosVacios(), [
            'estado' => EmpleadoEstados::PROVISORIO,
            'empresa_id' => 3,
            'empresa_scope' => 'una',
            '_limpiar' => true,
            'modo' => 'qbe',
            'valor' => 'juan',
        ]);

        $fusion = EmpleadoSueldosListadoFiltros::fusionarDesdeVista($base, [
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => false,
                    'criterios' => [[
                        'campo' => 'cuil',
                        'op' => 'contiene',
                        'valor' => '20',
                        'valor_hasta' => '',
                    ]],
                ]],
            ],
        ]);

        $this->assertSame(EmpleadoEstados::PROVISORIO, $fusion['estado']);
        $this->assertSame(3, $fusion['empresa_id']);
        $this->assertFalse(EmpleadoSueldosListadoFiltros::tieneCriteriosTexto($fusion));
        $this->assertArrayNotHasKey('_limpiar', $fusion);
    }
}
