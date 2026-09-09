<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
use PHPUnit\Framework\TestCase;

final class OrdencompraLegajoDocumentoTipoSupportTest extends TestCase
{
    public function test_exige_com_para_factura(): void
    {
        $this->assertTrue(OrdencompraLegajoDocumentoTipoSupport::exigeCom('FC'));
        $this->assertTrue(OrdencompraLegajoDocumentoTipoSupport::exigeCom('fc'));
    }

    public function test_no_exige_com_para_nota_credito(): void
    {
        $this->assertFalse(OrdencompraLegajoDocumentoTipoSupport::exigeCom('NC'));
    }

    public function test_no_exige_com_para_nota_debito(): void
    {
        $this->assertFalse(OrdencompraLegajoDocumentoTipoSupport::exigeCom('ND'));
    }

    public function test_no_exige_com_para_recibo(): void
    {
        $this->assertFalse(OrdencompraLegajoDocumentoTipoSupport::exigeCom('REC'));
    }
}
