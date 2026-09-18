<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorConceptoIvaTiposTest extends TestCase
{
    public function test_impuesto_interno_revierte_provision_com_junto_al_neto(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('T'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('t'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('I'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNeto('T'));

        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('G'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('N'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('E'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('T'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('I'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('P'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::revierteProvisionCom('B'));
    }
}
