<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\MovimientoStockLoteNumeracionSupport;
use PHPUnit\Framework\TestCase;

class MovimientoStockLoteNumeracionSupportTest extends TestCase
{
    public function test_reubica_pares_que_el_traslado_ya_movio(): void
    {
        // Alta: se sacan 6 del 35 y se suman 6 al 40. El traslado solo llevó 5 del 35.
        $delta = [35 => -6.0, 40 => 6.0];
        $traslado = [35 => 5.0, 36 => 10.0, 37 => 15.0, 38 => 15.0, 39 => 10.0, 40 => 5.0];

        $aplicar = MovimientoStockLoteNumeracionSupport::ajusteTrasladoPorDelta($delta, $traslado);

        $this->assertEqualsWithDelta(-5.0, $aplicar[35], 0.0001);
        $this->assertEqualsWithDelta(5.0, $aplicar[40], 0.0001);
        $this->assertEqualsWithDelta(0.0, array_sum($aplicar), 0.0001);
        $this->assertArrayNotHasKey(36, $aplicar);
    }

    public function test_no_toca_el_traslado_si_ese_talle_no_salio(): void
    {
        $aplicar = MovimientoStockLoteNumeracionSupport::ajusteTrasladoPorDelta(
            [41 => -4.0, 40 => 4.0],
            [36 => 10.0, 37 => 15.0]
        );

        $this->assertSame([], $aplicar);
    }

    public function test_no_cambia_el_total_si_solo_baja_pares(): void
    {
        $aplicar = MovimientoStockLoteNumeracionSupport::ajusteTrasladoPorDelta(
            [35 => -6.0],
            [35 => 5.0, 40 => 5.0]
        );

        $this->assertSame([], $aplicar);
    }

    public function test_delta_de_curvas(): void
    {
        $delta = MovimientoStockLoteNumeracionSupport::deltaTalles(
            [35 => 6.0, 40 => 6.0],
            [40 => 12.0]
        );

        $this->assertEqualsWithDelta(-6.0, $delta[35], 0.0001);
        $this->assertEqualsWithDelta(6.0, $delta[40], 0.0001);
    }
}
