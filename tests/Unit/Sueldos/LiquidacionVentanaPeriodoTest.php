<?php

namespace Tests\Unit\Sueldos;

use App\Support\Sueldos\LiquidacionVentanaPeriodo;
use PHPUnit\Framework\TestCase;

class LiquidacionVentanaPeriodoTest extends TestCase
{
    public function test_quincenas_noviembre_2026(): void
    {
        [$d1, $h1] = LiquidacionVentanaPeriodo::resolver(2026, 11, 'quincena_1');
        $this->assertSame('2026-11-01', $d1);
        $this->assertSame('2026-11-15', $h1);

        [$d2, $h2] = LiquidacionVentanaPeriodo::resolver(2026, 11, 'quincena_2');
        $this->assertSame('2026-11-16', $d2);
        $this->assertSame('2026-11-30', $h2);
    }

    public function test_vacaciones_por_descripcion(): void
    {
        [$d, $h] = LiquidacionVentanaPeriodo::resolver(2026, 11, 'vacaciones', 'VACA 2DA QUINC. 11-2026');
        $this->assertSame('2026-11-16', $d);
        $this->assertSame('2026-11-30', $h);

        [$d1, $h1] = LiquidacionVentanaPeriodo::resolver(2026, 11, 'vacaciones', 'VACA 1RA QUINC. 11-2026');
        $this->assertSame('2026-11-01', $d1);
        $this->assertSame('2026-11-15', $h1);
    }
}
