<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use Tests\TestCase;

class RecepcionProveedorCuadreContableSupportTest extends TestCase
{
    public function test_cuadre_ok_dentro_tolerancia(): void
    {
        RecepcionProveedorCuadreContableSupport::assertTotales(1000.00, 1000.00, 1000.00, 'RC-001');

        $this->addToAssertionCount(1);
    }

    public function test_rechaza_debe_distinto_haber(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('desbalanceado');

        RecepcionProveedorCuadreContableSupport::assertTotales(1000.00, 1000.00, 999.00);
    }

    public function test_rechaza_recepcion_distinta_contabilidad(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no suma lo mismo');

        RecepcionProveedorCuadreContableSupport::assertTotales(1000.00, 950.00, 950.00);
    }

    public function test_totales_desde_movimientos_persistidos(): void
    {
        $movimientos = [
            (object) ['monto' => 600.50],
            (object) ['monto' => 399.50],
            (object) ['monto' => -1000.00],
        ];

        $totales = RecepcionProveedorCuadreContableSupport::totalesDesdeMovimientos($movimientos);

        $this->assertSame(1000.0, $totales['debe']);
        $this->assertSame(1000.0, $totales['haber']);
    }
}
