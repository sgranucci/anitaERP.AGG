<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorConceptoIvaTiposTest extends TestCase
{
    public function test_impuesto_interno_por_tipo_t_o_codigo_anita(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('T'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('t'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('N', '5'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('T', '510'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('I'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('N', '1'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNeto('T'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia('N', '5'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia('G', '50'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia('N', '2'));
    }

    public function test_ii_solo_revierte_provision_si_la_com_lo_incluye(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('G'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('N'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('E'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('T'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('N', '5'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('T', '510', true));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('N', '5', true));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('I'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('P'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('B'));
    }
}
