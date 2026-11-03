<?php

namespace Tests\Unit\Support\Presupuesto;

use App\Support\Presupuesto\CapexEtiqueta;
use PHPUnit\Framework\TestCase;

final class CapexEtiquetaTest extends TestCase
{
    public function test_agrega_el_codigo_de_proyecto_al_numero(): void
    {
        $capex = (object) [
            'codigo' => '2326',
            'codigoproyecto' => 'Sis 02-92-2512(2)',
        ];

        $this->assertSame('2326 — Sis 02-92-2512(2)', CapexEtiqueta::numeroYCodigoProyecto($capex));
    }

    public function test_muestra_solo_el_numero_si_no_hay_codigo_de_proyecto(): void
    {
        $this->assertSame('2326', CapexEtiqueta::numeroYCodigoProyecto(['codigo' => '2326', 'codigoproyecto' => '']));
    }

    public function test_no_repite_el_numero_cuando_coincide_con_el_codigo(): void
    {
        $this->assertSame('2326', CapexEtiqueta::numeroYCodigoProyecto((object) [
            'codigo' => '2326',
            'codigoproyecto' => '2326',
        ]));
    }

    public function test_vacio_sin_capex(): void
    {
        $this->assertSame('', CapexEtiqueta::numeroYCodigoProyecto(null));
    }
}
