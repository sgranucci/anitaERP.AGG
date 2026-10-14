<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorTipoComprobanteSupportTest extends TestCase
{
    public function test_ctipo_scan_05_es_recibo(): void
    {
        $this->assertSame('REC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('05'));
        $this->assertSame('REC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('5'));
        $this->assertSame('REC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('REC'));
    }

    public function test_ctipo_scan_de_factura_nc_y_nd_no_cambia(): void
    {
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('01'));
        $this->assertSame('ND', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('02'));
        $this->assertSame('NC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('03'));
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::desdeCtipoScan('FC'));
    }
}
