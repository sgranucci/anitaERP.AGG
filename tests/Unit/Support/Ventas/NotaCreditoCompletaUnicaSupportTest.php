<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\NotaCreditoCompletaUnicaSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): cuándo una nota de crédito es completa.
 */
class NotaCreditoCompletaUnicaSupportTest extends TestCase
{
    public function test_el_total_de_la_factura_es_completa(): void
    {
        $this->assertTrue(NotaCreditoCompletaUnicaSupport::esCompleta(1500.0, 1500.0));
        $this->assertTrue(NotaCreditoCompletaUnicaSupport::esCompleta(-1500.0, 1500.0));
    }

    public function test_un_importe_menor_no_es_completa(): void
    {
        $this->assertFalse(NotaCreditoCompletaUnicaSupport::esCompleta(500.0, 1500.0));
    }

    public function test_anulacion_fce_en_s_es_completa_aunque_el_importe_sea_menor(): void
    {
        $this->assertTrue(NotaCreditoCompletaUnicaSupport::esCompleta(10.0, 1500.0, 'S'));
        $this->assertFalse(NotaCreditoCompletaUnicaSupport::esCompleta(10.0, 1500.0, 'N'));
    }

    public function test_mensaje_nombra_factura_y_nota(): void
    {
        $texto = NotaCreditoCompletaUnicaSupport::mensaje('FAC A-00001-00000010', 'NC A-00001-00000003');

        $this->assertStringContainsString('FAC A-00001-00000010', $texto);
        $this->assertStringContainsString('NC A-00001-00000003', $texto);
        $this->assertStringContainsString('nota de crédito completa', $texto);
    }
}
