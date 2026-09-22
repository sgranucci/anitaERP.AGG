<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorAsientoCuadreSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAsientoCuadreSupportTest extends TestCase
{
    public function test_detecta_centavos_a_imputar(): void
    {
        $this->assertTrue(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.03));
        $this->assertTrue(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(-0.01));
        $this->assertFalse(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.0));
        $this->assertFalse(ComprobanteProveedorAsientoCuadreSupport::hayDiferenciaAImputar(0.004));
    }

    public function test_absorbe_centavos_sin_tocar_provision(): void
    {
        $lineas = [
            ['cuentacontable_id' => 10, 'importe' => 17833.03],
            ['cuentacontable_id' => 20, 'importe' => 2635.13],
            ['cuentacontable_id' => 99, 'importe' => 110015.67],
        ];

        $ajustadas = ComprobanteProveedorAsientoCuadreSupport::absorberCentavosEnDebe($lineas, 0.03, 99);

        $this->assertSame(17833.03, $ajustadas[0]['importe']);
        $this->assertSame(2635.16, $ajustadas[1]['importe']);
        $this->assertSame(110015.67, $ajustadas[2]['importe']);
        $this->assertEqualsWithDelta(130483.86, array_sum(array_column($ajustadas, 'importe')), 0.001);
    }

    public function test_no_ajusta_fuera_de_tolerancia(): void
    {
        $lineas = [
            ['cuentacontable_id' => 10, 'importe' => 100.00],
        ];

        $iguales = ComprobanteProveedorAsientoCuadreSupport::absorberCentavosEnDebe($lineas, 0.10, 0);

        $this->assertSame(100.00, $iguales[0]['importe']);
    }

    public function test_diferencia_menor_al_cinco_porciento_se_imputa(): void
    {
        $this->assertTrue(
            ComprobanteProveedorAsientoCuadreSupport::diferenciaDentroDePorcentaje(0.03, 110015.67)
        );
        $this->assertTrue(
            ComprobanteProveedorAsientoCuadreSupport::diferenciaDentroDePorcentaje(-5500.00, 110015.67)
        );
        $this->assertFalse(
            ComprobanteProveedorAsientoCuadreSupport::diferenciaDentroDePorcentaje(6000.00, 110015.67)
        );
        $this->assertEqualsWithDelta(
            0.000027,
            ComprobanteProveedorAsientoCuadreSupport::porcentajeDiferencia(0.03, 110015.67),
            0.00001
        );
    }

    public function test_aplica_descuento_neto_negativo_sobre_debe_de_neto(): void
    {
        $lineas = [
            ['cuentacontable_id' => 1, 'importe' => 1000.00, 'origen' => 'neto_manual'],
            ['cuentacontable_id' => 2, 'importe' => 210.00, 'origen' => 'impuesto'],
        ];

        $ajustadas = ComprobanteProveedorAsientoCuadreSupport::aplicarDescuentoNetoEnDebe($lineas, -100.0);

        $this->assertCount(2, $ajustadas);
        $this->assertEqualsWithDelta(900.0, $ajustadas[0]['importe'], 0.001);
        $this->assertEqualsWithDelta(210.0, $ajustadas[1]['importe'], 0.001);
        $this->assertEqualsWithDelta(1110.0, array_sum(array_column($ajustadas, 'importe')), 0.001);
    }

    public function test_descuento_neto_no_toca_impuestos_si_hay_neto(): void
    {
        $lineas = [
            ['cuentacontable_id' => 1, 'importe' => 500.00, 'origen' => 'anticipo'],
            ['cuentacontable_id' => 2, 'importe' => 800.00, 'origen' => 'impuesto'],
        ];

        $ajustadas = ComprobanteProveedorAsientoCuadreSupport::aplicarDescuentoNetoEnDebe($lineas, -50.0);

        $this->assertEqualsWithDelta(450.0, $ajustadas[0]['importe'], 0.001);
        $this->assertEqualsWithDelta(800.0, $ajustadas[1]['importe'], 0.001);
    }
}
