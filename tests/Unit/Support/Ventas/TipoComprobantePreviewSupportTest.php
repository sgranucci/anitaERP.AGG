<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TipoComprobantePreviewSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): FAC/FCE según tipo pedido y preview.
 */
class TipoComprobantePreviewSupportTest extends TestCase
{
    public function test_detecta_fce_por_abreviatura_y_codigo(): void
    {
        $fce = (object) ['abreviatura' => 'FCE', 'codigo' => '201'];
        $fac = (object) ['abreviatura' => 'FAC', 'codigo' => '001'];
        $nc = (object) ['abreviatura' => 'NCB', 'codigo' => '003'];

        self::assertTrue(TipoComprobantePreviewSupport::esTipoFce($fce));
        self::assertFalse(TipoComprobantePreviewSupport::esTipoFce($fac));
        self::assertFalse(TipoComprobantePreviewSupport::esTipoFce($nc));
        self::assertTrue(TipoComprobantePreviewSupport::esTipoFce((object) ['abreviatura' => '', 'codigo' => '206']));
    }

    public function test_solo_rearma_factura_no_notas(): void
    {
        self::assertTrue(TipoComprobantePreviewSupport::esFacturaVentaFacOFce((object) ['abreviatura' => 'FAC', 'codigo' => '001']));
        self::assertTrue(TipoComprobantePreviewSupport::esFacturaVentaFacOFce((object) ['abreviatura' => 'FCE', 'codigo' => '201']));
        self::assertFalse(TipoComprobantePreviewSupport::esFacturaVentaFacOFce((object) ['abreviatura' => 'NCB', 'codigo' => '003']));
        self::assertFalse(TipoComprobantePreviewSupport::esFacturaVentaFacOFce((object) ['abreviatura' => 'NCE', 'codigo' => '203']));
    }

    public function test_elige_fac_cuando_el_default_quedo_en_fce(): void
    {
        $fce = (object) ['abreviatura' => 'FCE', 'codigo' => '201'];
        $id = TipoComprobantePreviewSupport::elegirId(99, [
            'tipotransaccion_sugerido_id' => 10,
            'es_fce' => false,
        ], $fce);

        self::assertSame(10, $id);
    }

    public function test_elige_fce_cuando_el_cliente_y_el_monto_califican(): void
    {
        $fac = (object) ['abreviatura' => 'FAC', 'codigo' => '001'];
        $id = TipoComprobantePreviewSupport::elegirId(10, [
            'tipotransaccion_sugerido_id' => 99,
            'es_fce' => true,
        ], $fac);

        self::assertSame(99, $id);
    }

    public function test_no_pisa_una_nota_de_credito(): void
    {
        $nc = (object) ['abreviatura' => 'NCB', 'codigo' => '003'];
        $id = TipoComprobantePreviewSupport::elegirId(55, [
            'tipotransaccion_sugerido_id' => 10,
            'es_fce' => false,
        ], $nc);

        self::assertSame(55, $id);
    }
}
