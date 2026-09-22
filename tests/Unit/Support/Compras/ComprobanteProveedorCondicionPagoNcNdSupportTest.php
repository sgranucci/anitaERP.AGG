<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorCondicionPagoNcNdSupport;
use RuntimeException;
use Tests\TestCase;

class ComprobanteProveedorCondicionPagoNcNdSupportTest extends TestCase
{
    public function test_debe_restringir_solo_nc_nd(): void
    {
        $this->assertTrue(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('NC'));
        $this->assertTrue(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('ND'));
        $this->assertTrue(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('DNB'));
        $this->assertFalse(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('FC'));
        $this->assertFalse(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('FNB'));
        $this->assertFalse(ComprobanteProveedorCondicionPagoNcNdSupport::debeRestringirAUnaCuota('REC'));
    }

    public function test_factura_admite_plan_multi_cuota(): void
    {
        ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitidaParaTipoGenerico('FC', null, 24);
        $this->assertTrue(true);
    }

    public function test_nd_rechaza_plan_multi_cuota(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('solo admiten una cuota');
        ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitidaParaTipoGenerico('ND', null, 24);
    }

    public function test_nc_admite_una_cuota(): void
    {
        ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitidaParaTipoGenerico('NC', null, 1);
        $this->assertTrue(true);
    }

    public function test_cantidad_cuotas_plan(): void
    {
        $this->assertSame(0, ComprobanteProveedorCondicionPagoNcNdSupport::cantidadCuotasPlan([]));
        $this->assertSame(2, ComprobanteProveedorCondicionPagoNcNdSupport::cantidadCuotasPlan([
            ['fechavencimiento' => '2026-01-01'],
            ['fechavencimiento' => '2026-02-01'],
        ]));
    }
}
