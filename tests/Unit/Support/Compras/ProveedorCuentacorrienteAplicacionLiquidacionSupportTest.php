<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteAplicacionLiquidacionSupport;
use Tests\TestCase;

class ProveedorCuentacorrienteAplicacionLiquidacionSupportTest extends TestCase
{
    public function test_pesos_contra_pesos_ignora_cotizacion_informativa(): void
    {
        $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
            ['moneda_id' => 1, 'cotizacion' => 1510],
            ['moneda_id' => 1, 'cotizacion' => 1465],
            48000
        );

        $this->assertFalse($liq['cruzada']);
        $this->assertSame(48000.0, $liq['monto_deuda']);
        $this->assertSame(48000.0, $liq['monto_credito']);
        $this->assertSame(48000.0, $liq['valor_local_deuda']);
        $this->assertSame(48000.0, $liq['valor_local_credito']);
        $this->assertSame(0.0, $liq['dc']);
    }

    public function test_misma_moneda_usd_reusa_formula_dc(): void
    {
        $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
            ['moneda_id' => 2, 'cotizacion' => 1200],
            ['moneda_id' => 2, 'cotizacion' => 1100],
            1000
        );

        $this->assertFalse($liq['cruzada']);
        $this->assertSame(1000.0, $liq['monto_deuda']);
        $this->assertSame(1000.0, $liq['monto_credito']);
        $this->assertSame(100000.0, $liq['dc']);
    }

    public function test_ars_contra_usd_consume_montos_distintos_y_dc_economica(): void
    {
        $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
            ['moneda_id' => 2, 'cotizacion' => 1200],
            ['moneda_id' => 1, 'cotizacion' => 1],
            1000,
            1100
        );

        $this->assertTrue($liq['cruzada']);
        $this->assertSame(1000.0, $liq['monto_deuda']);
        $this->assertSame(1100000.0, $liq['monto_credito']);
        $this->assertSame(1200000.0, $liq['valor_local_deuda']);
        $this->assertSame(1100000.0, $liq['valor_local_credito']);
        $this->assertSame(-100000.0, $liq['dc']);
    }

    public function test_usd_contra_ars_invierte_la_conversion(): void
    {
        $liq = ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
            ['moneda_id' => 1, 'cotizacion' => 1],
            ['moneda_id' => 2, 'cotizacion' => 1100],
            1100000,
            1100
        );

        $this->assertTrue($liq['cruzada']);
        $this->assertSame(1100000.0, $liq['monto_deuda']);
        $this->assertSame(1000.0, $liq['monto_credito']);
        $this->assertSame(0.0, $liq['dc']);
    }

    public function test_tope_no_excede_saldo_del_credito_en_otra_moneda(): void
    {
        $max = ProveedorCuentacorrienteAplicacionLiquidacionSupport::montoDeudaMaximo(
            ['moneda_id' => 2, 'cotizacion' => 1200],
            ['moneda_id' => 1, 'cotizacion' => 1],
            1000,
            550000,
            1100
        );

        $this->assertSame(500.0, $max);
    }

    public function test_cruzada_sin_cotizacion_invalida(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProveedorCuentacorrienteAplicacionLiquidacionSupport::liquidar(
            ['moneda_id' => 2, 'cotizacion' => 1200],
            ['moneda_id' => 3, 'cotizacion' => 1300],
            1000,
            0
        );
    }
}
