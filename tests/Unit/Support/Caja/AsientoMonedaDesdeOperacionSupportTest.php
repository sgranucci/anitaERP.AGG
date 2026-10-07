<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\AsientoMonedaDesdeOperacionSupport;
use PHPUnit\Framework\TestCase;

class AsientoMonedaDesdeOperacionSupportTest extends TestCase
{
    public function test_nominal_en_dolares_grabado_como_pesos_pasa_a_dolares(): void
    {
        $monedas = AsientoMonedaDesdeOperacionSupport::alinear(
            [1, 1],
            [242, ''],
            ['', 242],
            [1515, 1515],
            [[
                'moneda_id' => 2,
                'monto' => 242,
                'cotizacion' => 1515,
            ]],
        );

        $this->assertSame([2, 2], $monedas);
    }

    public function test_importe_ya_convertido_a_pesos_no_cambia(): void
    {
        $monedas = AsientoMonedaDesdeOperacionSupport::alinear(
            [1],
            [366630],
            [''],
            [1515],
            [[
                'moneda_id' => 2,
                'monto' => 242,
                'cotizacion' => 1515,
            ]],
        );

        $this->assertSame([1], $monedas);
    }

    public function test_cotizacion_uno_no_reasigna_la_moneda(): void
    {
        $monedas = AsientoMonedaDesdeOperacionSupport::alinear(
            [1, 1],
            [20200000, ''],
            ['', 20200000],
            [1, 1],
            [[
                'moneda_id' => 2,
                'monto' => 20200000,
                'cotizacion' => 1,
            ]],
        );

        $this->assertSame([1, 1], $monedas);
    }
}
