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

    public function test_no_permite_negativo_en_gravado_ni_iva_ordinario(): void
    {
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('G', '50'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('I', '503'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::permiteMontoNegativo('N', '2'));
        $this->assertFalse(ComprobanteProveedorConceptoIvaTipos::esDescuento('50'));
    }
}
