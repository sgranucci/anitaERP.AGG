<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorCuotasTotalSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorCuotasTotalSupportTest extends TestCase
{
    public function test_cuadra_exacto(): void
    {
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(1127627.20, [
            ['monto' => 1127627.20],
        ]);

        $this->assertTrue($cuadre['aplica']);
        $this->assertTrue($cuadre['cuadra']);
        $this->assertSame(0.0, $cuadre['diferencia']);
    }

    public function test_cuadra_dentro_de_tolerancia(): void
    {
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(100.00, [
            ['monto' => 50.02],
            ['monto' => 49.99],
        ]);

        $this->assertTrue($cuadre['cuadra']);
        $this->assertEqualsWithDelta(0.01, $cuadre['diferencia'], 0.001);
    }

    public function test_desvio_como_el_de_biyemas_no_cuadra(): void
    {
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(1127254.80, [
            ['monto' => 1127627.20],
        ]);

        $this->assertFalse($cuadre['cuadra']);
        $this->assertEqualsWithDelta(372.40, $cuadre['diferencia'], 0.001);
        $this->assertStringContainsString('suma de cuotas', $cuadre['mensaje']);
    }

    public function test_assert_lanza_si_no_cuadra(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('suma de cuotas');

        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal(100.00, [
            ['monto' => 150.00],
        ]);
    }

    public function test_lista_vacia_no_aplica_y_no_bloquea(): void
    {
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(100.00, []);

        $this->assertFalse($cuadre['aplica']);
        $this->assertTrue($cuadre['cuadra']);

        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal(100.00, []);
        $this->addToAssertionCount(1);
    }

    public function test_una_cuota_en_cero_usa_total_como_cc(): void
    {
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(500.00, [
            ['monto' => 0],
        ]);

        $this->assertTrue($cuadre['cuadra']);
        $this->assertSame(500.0, $cuadre['suma']);
    }

    public function test_acepta_objetos_con_propiedad_monto(): void
    {
        $cuota = (object) ['monto' => 200.00];
        $cuadre = ComprobanteProveedorCuotasTotalSupport::cuadreConTotal(200.00, [$cuota]);

        $this->assertTrue($cuadre['cuadra']);
    }

    public function test_redistribuye_una_cuota_al_total(): void
    {
        $out = ComprobanteProveedorCuotasTotalSupport::redistribuirAlTotal([
            ['monto' => 1127627.20, 'numero_cuota' => 1],
        ], 1127254.80);

        $this->assertSame(1127254.80, $out[0]['monto']);
    }

    public function test_redistribuye_varias_cuotas_proporcional(): void
    {
        $out = ComprobanteProveedorCuotasTotalSupport::redistribuirAlTotal([
            ['monto' => 60.0],
            ['monto' => 40.0],
        ], 200.0);

        $this->assertSame(120.0, $out[0]['monto']);
        $this->assertSame(80.0, $out[1]['monto']);
        $this->assertEqualsWithDelta(200.0, $out[0]['monto'] + $out[1]['monto'], 0.001);
    }

    public function test_alinear_solo_si_hace_falta(): void
    {
        $ok = [
            ['monto' => 100.0],
        ];
        $this->assertSame($ok, ComprobanteProveedorCuotasTotalSupport::alinearConTotalSiHaceFalta($ok, 100.0));

        $desvio = [
            ['monto' => 150.0],
        ];
        $alineado = ComprobanteProveedorCuotasTotalSupport::alinearConTotalSiHaceFalta($desvio, 100.0);
        $this->assertSame(100.0, $alineado[0]['monto']);
    }
}
