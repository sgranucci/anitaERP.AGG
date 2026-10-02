<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoAsientoDescripcionSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoAsientoDescripcionSupportTest extends TestCase
{
    public function test_el_detalle_del_encabezado_gana(): void
    {
        $texto = IngresoEgresoAsientoDescripcionSupport::resolver(
            'Gastos Bancarios Macro BSA 09.26',
            ['otro comentario de la cuenta'],
            'Egreso',
            ['BANCO MACRO S.A.'],
            ['BANCO MACRO NUEVAS EX ITAU BSA'],
        );

        $this->assertSame('Gastos Bancarios Macro BSA 09.26', $texto);
    }

    public function test_sin_encabezado_usa_el_comentario_de_la_cuenta(): void
    {
        $texto = IngresoEgresoAsientoDescripcionSupport::resolver(
            'Movimiento de caja',
            ['', 'Gastos Bancarios Bco Macro BSA 09.26', 'Gastos Bancarios Bco Macro BSA 09.26'],
            'Egreso',
            ['BANCO MACRO S.A.'],
            [],
        );

        $this->assertSame('Gastos Bancarios Bco Macro BSA 09.26', $texto);
    }

    public function test_sin_comentarios_arma_tipo_y_banco(): void
    {
        $texto = IngresoEgresoAsientoDescripcionSupport::resolver(
            '',
            ['Movimiento de caja'],
            'Egreso',
            ['BANCO MACRO S.A.'],
            ['BANCO MACRO NUEVAS EX ITAU BSA'],
        );

        $this->assertSame('Egreso BANCO MACRO S.A.', $texto);
    }

    public function test_sin_banco_usa_el_nombre_de_la_cuenta(): void
    {
        $texto = IngresoEgresoAsientoDescripcionSupport::resolver(
            '   ',
            [],
            'Egreso',
            [],
            ['Caja chica administración'],
        );

        $this->assertSame('Egreso Caja chica administración', $texto);
    }
}
