<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorProrrateoMultiCcSupportTest extends TestCase
{
    public function test_detecta_abreviatura_prorrateada(): void
    {
        $this->assertTrue(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('FPB'));
        $this->assertTrue(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('fps'));
        $this->assertTrue(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('CPU'));
        $this->assertTrue(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('DPL'));
        $this->assertFalse(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('FGA'));
        $this->assertFalse(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('FIB'));
        $this->assertFalse(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('FPR'));
        $this->assertFalse(PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado('FC'));
    }

    public function test_familia_desde_abreviatura(): void
    {
        $this->assertSame('FC', PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura('FPB'));
        $this->assertSame('NC', PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura('CPS'));
        $this->assertSame('ND', PrecargaProveedorProrrateoMultiCcSupport::familiaDesdeAbreviatura('DPU'));
    }
}
