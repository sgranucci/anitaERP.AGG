<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorConceptoIvaTiposTest extends TestCase
{
    public function test_permite_monto_negativo_en_exento_y_descuentos_80_81(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('E', '1'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('E', '81'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('G', '80'));
        // Anita conccomp 1 suele venir tipoconcepto N en el maestro (EXENTO / NO GRAVADO).
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('N', '1'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esDescuento('80'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esDescuento(81));
    }

    public function test_es_exento_por_tipo_e_o_codigo_1(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esExento('E', '99'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esExento('N', '1'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esExento('N', 1));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esExento('N', '2'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esExento('G', '50'));
    }

    public function test_permite_negativo_en_gravado_e_iva_ordinario(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('G', '50'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('G', '51'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('I', '321'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('I', '503'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('N', '2'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('P', '103'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('', '99'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esDescuento('50'));
        // Gravado/neto negativo → ajusta gasto; IVA/percepción negativo → Haber del concepto.
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::negativoAjustaNetoGasto('G', '51'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::negativoAjustaNetoGasto('E', '1'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::negativoAjustaNetoGasto('I', '321'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::negativoAjustaNetoGasto('P', '103'));
    }

    public function test_impuesto_interno_es_neto_de_gasto_sin_ser_mercaderia(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('T', '510'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('N', '5'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia('T', '510'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia('N', '5'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto('T', '510'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto('N', '5'));
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto('G', '50'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto('I', '503'));
    }

    public function test_retencion_tipo_v_es_impuesto_y_no_gasto(): void
    {
        $this->assertTrue(ComprobanteProveedorConceptoIvaTipos::esImpuesto('V'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto('V', '151'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno('V', '151'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esExento('V', '151'));
    }
}
