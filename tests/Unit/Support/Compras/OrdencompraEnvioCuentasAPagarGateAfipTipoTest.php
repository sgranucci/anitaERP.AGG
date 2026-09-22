<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use PHPUnit\Framework\TestCase;

class OrdencompraEnvioCuentasAPagarGateAfipTipoTest extends TestCase
{
    public function test_codigo_afip_01_es_factura(): void
    {
        $this->assertSame(
            'FC',
            OrdencompraEnvioCuentasAPagarGateSupport::tipoComprobanteGenericoDesdeCodigoAfip('01')
        );
        $this->assertSame(
            'FC',
            OrdencompraEnvioCuentasAPagarGateSupport::tipoComprobanteGenericoDesdeCodigoAfip('001')
        );
    }

    public function test_codigo_afip_02_es_nota_debito(): void
    {
        $this->assertSame(
            'ND',
            OrdencompraEnvioCuentasAPagarGateSupport::tipoComprobanteGenericoDesdeCodigoAfip('02')
        );
    }

    public function test_codigo_afip_03_es_nota_credito(): void
    {
        $this->assertSame(
            'NC',
            OrdencompraEnvioCuentasAPagarGateSupport::tipoComprobanteGenericoDesdeCodigoAfip('03')
        );
    }
}
