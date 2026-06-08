<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use PHPUnit\Framework\TestCase;

class GastronomiaVentaComprobanteSignoSupportTest extends TestCase
{
    public function test_cantidad_linea_positiva_en_factura_negativa_en_nc(): void
    {
        $this->assertSame(3.0, GastronomiaVentaComprobanteSignoSupport::cantidadLineaVenta(3, 1));
        $this->assertSame(-2.0, GastronomiaVentaComprobanteSignoSupport::cantidadLineaVenta(2, -1));
        $this->assertSame(-2.0, GastronomiaVentaComprobanteSignoSupport::cantidadLineaVenta(-2, -1));
    }

    public function test_total_comprobante_no_invierte_doble_cuando_total_ya_es_negativo(): void
    {
        $this->assertSame(-6100.0, GastronomiaVentaComprobanteSignoSupport::totalComprobante(-6100, -1));
        $this->assertSame(7600.0, GastronomiaVentaComprobanteSignoSupport::totalComprobante(7600, 1));
    }

    public function test_es_nota_credito_por_signo(): void
    {
        $this->assertTrue(GastronomiaVentaComprobanteSignoSupport::esNotaCreditoSigno(-1));
        $this->assertFalse(GastronomiaVentaComprobanteSignoSupport::esNotaCreditoSigno(1));
    }

    public function test_sql_cantidad_usa_abs_y_signo_comprobante(): void
    {
        $sql = GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();

        $this->assertStringContainsString('ABS(ve.cantidad)', $sql);
        $this->assertStringContainsString('tt.signo', $sql);
    }
}
