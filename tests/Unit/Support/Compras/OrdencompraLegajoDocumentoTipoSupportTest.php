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

    public function test_abreviatura_anita_cis_es_nota_credito(): void
    {
        $this->assertSame('NC', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('CIS'));
        $this->assertSame('NC', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('CGA'));
        $this->assertSame('NC', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('CNS'));
        $this->assertFalse(OrdencompraLegajoDocumentoTipoSupport::exigeCom(
            OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('CIS')
        ));
    }

    public function test_abreviatura_anita_dis_es_nota_debito(): void
    {
        $this->assertSame('ND', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('DIS'));
        $this->assertFalse(OrdencompraLegajoDocumentoTipoSupport::exigeCom(
            OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('DIS')
        ));
    }

    public function test_abreviatura_anita_fis_es_factura(): void
    {
        $this->assertSame('FC', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('FIS'));
        $this->assertTrue(OrdencompraLegajoDocumentoTipoSupport::exigeCom(
            OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('FIS')
        ));
    }

    public function test_informe_recepcion_com_no_es_nota_credito(): void
    {
        $this->assertSame('FC', OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura('COM'));
    }

    public function test_numero_con_tipo_no_duplica_prefijo_cis(): void
    {
        $this->assertSame(
            'CIS A 0070-00030193',
            OrdencompraLegajoDocumentoTipoSupport::numeroConTipo('NC', 'CIS A 0070-00030193')
        );
    }
}
