<?php

namespace Tests\Unit\Support\Sueldos;

use App\Support\Sueldos\SancionReporteListadoFiltros;
use PHPUnit\Framework\TestCase;

class SancionReporteListadoFiltrosTest extends TestCase
{
    public function test_la_vista_no_pisa_empresas_ni_apellido(): void
    {
        $base = array_merge(SancionReporteListadoFiltros::filtrosVacios(), [
            'empresa_ids' => [2, 5],
            'apellido' => 'Gomez',
            'fecha_desde' => '2026-01-01',
            'fecha_hasta' => '2026-11-10',
            'estado' => 'firme',
        ]);

        $fusion = SancionReporteListadoFiltros::fusionarDesdeVista($base, [
            'modo' => 'qbe',
            'empresa_ids' => [9],
            'apellido' => 'Perez',
            'estado' => 'anulada',
            'qbe' => [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => false,
                    'criterios' => [[
                        'campo' => 'tipo',
                        'op' => 'contiene',
                        'valor' => 'suspension',
                        'valor_hasta' => '',
                    ]],
                ]],
            ],
        ]);

        $this->assertSame([2, 5], $fusion['empresa_ids']);
        $this->assertSame('Gomez', $fusion['apellido']);
        $this->assertSame('firme', $fusion['estado']);
        $this->assertSame('qbe', $fusion['modo']);
        $this->assertSame('suspension', $fusion['qbe']['grupos'][0]['criterios'][0]['valor']);
    }

    public function test_sin_consolidar_agrupa_por_empresa(): void
    {
        $agrupar = SancionReporteListadoFiltros::agruparEfectivo([
            'consolidar_empresas' => false,
            'agrupar' => ['tipo'],
        ]);

        $this->assertSame(['empresa', 'tipo'], $agrupar);
    }
}
