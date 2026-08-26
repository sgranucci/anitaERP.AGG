<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorConversionSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorConversionSupportTest extends TestCase
{
    public function test_precio_unitario_neto_aplica_descuento_de_pie(): void
    {
        $neto = RecepcionProveedorConversionSupport::precioUnitarioNetoDesdeLineaOc(39184.80, 0, 20);

        $this->assertSame(31347.84, $neto);
    }

    public function test_precio_unitario_neto_aplica_descuento_linea_y_cabecera(): void
    {
        $neto = RecepcionProveedorConversionSupport::precioUnitarioNetoDesdeLineaOc(1000, 10, 20);

        $this->assertSame(720.0, $neto);
    }

    public function test_importe_linea_en_moneda_referencia_no_convierte_si_moneda_pesos(): void
    {
        $importe = RecepcionProveedorConversionSupport::importeLineaEnMonedaReferencia(
            1,
            1,
            2,
            1000,
            0,
            0,
            1500,
        );

        $this->assertSame(2000.0, $importe);
    }

    public function test_importe_linea_en_moneda_referencia_convierte_dolares_a_pesos(): void
    {
        $importe = RecepcionProveedorConversionSupport::importeLineaEnMonedaReferencia(
            1,
            2,
            1,
            100,
            0,
            0,
            1500,
        );

        $this->assertSame(150000.0, $importe);
    }

    public function test_convertir_moneda_codigo_1_no_reconvierte_aunque_haya_cotizacion(): void
    {
        $this->assertTrue(RecepcionProveedorConversionSupport::esCodigoMonedaPesos('1'));
        $this->assertTrue(RecepcionProveedorConversionSupport::esCodigoMonedaPesos(1));
        $this->assertTrue(RecepcionProveedorConversionSupport::esCodigoMonedaPesos(''));
        $this->assertFalse(RecepcionProveedorConversionSupport::esCodigoMonedaPesos('2'));

        $this->assertSame(
            10235947.6,
            RecepcionProveedorConversionSupport::convertirMoneda(10235947.6, 1415, 1, '1')
        );
        $this->assertSame(
            10235947.6,
            RecepcionProveedorConversionSupport::convertirMoneda(10235947.6, 1415, 1, 1)
        );
    }

    public function test_convertir_moneda_codigo_extranjero_aplica_cotizacion(): void
    {
        $this->assertSame(
            141500.0,
            RecepcionProveedorConversionSupport::convertirMoneda(100.0, 1415, 1, '2')
        );
    }
}
