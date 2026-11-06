<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorNetoCuentaIvaSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComprobanteProveedorNetoCuentaIvaSupportTest extends TestCase
{
    public function test_detecta_rango_iva_credito_fiscal(): void
    {
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal(114010002));
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal('114010-002'));
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal('114010009'));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal(521230001));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal(211010001));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal(114040001));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::esCodigoIvaCreditoFiscal(null));
    }

    public function test_aplica_a_neto_y_exento_no_a_iva_ni_percepciones(): void
    {
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('G', '51'));
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('N', '2'));
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('E', '1'));
        $this->assertTrue(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('T', '510'));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('I', '321'));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('P', '103'));
        $this->assertFalse(ComprobanteProveedorNetoCuentaIvaSupport::aplicaAConcepto('B', '140'));
    }

    public function test_assert_codigo_rechaza_iva_y_acepta_gasto(): void
    {
        ComprobanteProveedorNetoCuentaIvaSupport::assertCodigoPermitidoParaNeto(521230001, 'el neto');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('crédito fiscal de IVA');
        ComprobanteProveedorNetoCuentaIvaSupport::assertCodigoPermitidoParaNeto('114010-002', 'el neto «Gravado»');
    }
}
