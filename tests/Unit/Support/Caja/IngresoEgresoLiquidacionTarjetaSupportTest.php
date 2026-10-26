<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoLiquidacionTarjetaSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoLiquidacionTarjetaSupportTest extends TestCase
{
    public function test_el_neto_es_el_bruto_menos_los_gastos(): void
    {
        $this->assertTrue(IngresoEgresoLiquidacionTarjetaSupport::cierra(1435700.00, 93689.27, 1342010.73));
        $this->assertFalse(IngresoEgresoLiquidacionTarjetaSupport::cierra(1435700.00, 93689.27, 1342000.00));
    }

    public function test_la_leyenda_guarda_la_tarjeta_y_el_bruto(): void
    {
        $leyenda = IngresoEgresoLiquidacionTarjetaSupport::leyenda(267, 1435700, 'VISA');
        $marca = IngresoEgresoLiquidacionTarjetaSupport::parsearLeyenda($leyenda);

        $this->assertSame(267, $marca['cuentacaja_tarjeta_id']);
        $this->assertSame(1435700.0, $marca['bruto_cupones']);
        $this->assertStringContainsString('VISA', $leyenda);
    }

    public function test_rearmar_la_leyenda_no_duplica_la_marca(): void
    {
        $primera = IngresoEgresoLiquidacionTarjetaSupport::leyenda(267, 100, 'nota');
        $segunda = IngresoEgresoLiquidacionTarjetaSupport::leyenda(268, 200, $primera);

        $this->assertSame(1, substr_count($segunda, 'LT:'));
        $this->assertSame(268, IngresoEgresoLiquidacionTarjetaSupport::parsearLeyenda($segunda)['cuentacaja_tarjeta_id']);
    }
}
