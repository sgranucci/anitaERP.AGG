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
}
