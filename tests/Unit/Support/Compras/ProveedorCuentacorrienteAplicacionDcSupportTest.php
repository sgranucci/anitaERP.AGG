<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use PHPUnit\Framework\TestCase;

class ProveedorCuentacorrienteAplicacionDcSupportTest extends TestCase
{
    public function test_misma_cotizacion_no_genera_dc(): void
    {
        $this->assertSame(0.0, ProveedorCuentacorrienteAplicacionDcSupport::calcular(1000, 1200, 1200));
        $this->assertFalse(ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento(0.0));
    }

    public function test_deuda_mas_cara_es_perdida(): void
    {
        $dc = ProveedorCuentacorrienteAplicacionDcSupport::calcular(1000, 1200, 1100);

        $this->assertSame(100000.0, $dc);
        $this->assertTrue(ProveedorCuentacorrienteAplicacionDcSupport::esPerdida($dc));
        $this->assertSame('Pérdida', ProveedorCuentacorrienteAplicacionDcSupport::etiqueta($dc));
    }

    public function test_credito_mas_caro_es_ganancia(): void
    {
        $dc = ProveedorCuentacorrienteAplicacionDcSupport::calcular(1000, 1100, 1200);

        $this->assertSame(-100000.0, $dc);
        $this->assertFalse(ProveedorCuentacorrienteAplicacionDcSupport::esPerdida($dc));
        $this->assertSame('Ganancia', ProveedorCuentacorrienteAplicacionDcSupport::etiqueta($dc));
    }

    public function test_centavos_bajo_tolerancia_se_omiten(): void
    {
        $this->assertSame(0.0, ProveedorCuentacorrienteAplicacionDcSupport::calcular(1, 1.005, 1.0));
    }

    public function test_monedas_distintas_no_calculan(): void
    {
        $dc = ProveedorCuentacorrienteAplicacionDcSupport::calcularDesdeFilas(
            ['moneda_id' => 2, 'cotizacion' => 1200],
            ['moneda_id' => 1, 'cotizacion' => 1],
            1000
        );

        $this->assertSame(0.0, $dc);
    }

    public function test_cotizacion_nula_o_cero_se_trata_como_uno(): void
    {
        $this->assertSame(0.0, ProveedorCuentacorrienteAplicacionDcSupport::calcular(50, 0, 0));
        $this->assertSame(1.0, ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada(0));
    }
}
