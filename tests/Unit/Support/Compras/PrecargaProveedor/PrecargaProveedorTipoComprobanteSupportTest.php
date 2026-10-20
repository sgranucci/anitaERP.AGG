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

    public function test_factura_n_c_no_computable_es_factura_no_nota_credito(): void
    {
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::normalizar('Factura N/C Servicios'));
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::normalizar('Factura N/C Locaciones'));
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::normalizar('FACTURA NO COMPUTABLE SERVICIOS'));
        $this->assertSame('FC', PrecargaProveedorTipoComprobanteSupport::normalizar('FNS'));
    }

    public function test_nota_credito_sigue_siendo_nc(): void
    {
        $this->assertSame('NC', PrecargaProveedorTipoComprobanteSupport::normalizar('Nota de Crédito'));
        $this->assertSame('NC', PrecargaProveedorTipoComprobanteSupport::normalizar('N/C'));
        $this->assertSame('NC', PrecargaProveedorTipoComprobanteSupport::normalizar('NC'));
        $this->assertSame('NC', PrecargaProveedorTipoComprobanteSupport::normalizar('Credit Note'));
    }
}
