<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorLiquidacionSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorLiquidacionSupportTest extends TestCase
{
    public function test_misma_moneda_modo_dia_reusa_dc(): void
    {
        $liq = PagoproveedorLiquidacionSupport::calcular(1000, 2, 1200, 2, 1100);

        $this->assertFalse($liq['cruzada']);
        $this->assertSame(1000.0, $liq['equivalente_pago']);
        $this->assertSame(100000.0, $liq['dc']);
    }

    public function test_pago_ars_factura_usd(): void
    {
        $liq = PagoproveedorLiquidacionSupport::calcular(1000, 2, 1200, 1, 1100);

        $this->assertTrue($liq['cruzada']);
        $this->assertSame(1100000.0, $liq['equivalente_pago']);
        $this->assertSame(100000.0, $liq['dc']);
    }

    public function test_modo_factura_usa_cotizacion_deuda_tambien_cruzada(): void
    {
        $this->assertSame(
            1513.0,
            PagoproveedorLiquidacionSupport::cotizacionAplicadaDefault('factura', 1513, 1535, true)
        );
        $this->assertSame(
            1513.0,
            PagoproveedorLiquidacionSupport::cotizacionAplicadaDefault('factura', 1513, 1535, false)
        );
    }

    public function test_modo_dia_usa_cotizacion_del_dia(): void
    {
        $this->assertSame(
            1535.0,
            PagoproveedorLiquidacionSupport::cotizacionAplicadaDefault('dia', 1513, 1535, true)
        );
        $this->assertSame(
            1535.0,
            PagoproveedorLiquidacionSupport::cotizacionAplicadaDefault('dia', 1513, 1535, false)
        );
    }
}
