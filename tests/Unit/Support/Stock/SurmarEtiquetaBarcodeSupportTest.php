<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\Surmar\SurmarEtiquetaBarcodeSupport;
use PHPUnit\Framework\TestCase;

/**
 * Parseo puro (sin BD): ID ERP, Code39, QR v2 y barcode Anita.
 */
class SurmarEtiquetaBarcodeSupportTest extends TestCase
{
    public function test_id_erp_solo_digitos(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear('16688');
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);
    }

    public function test_code39_con_asteriscos(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear('*16688*');
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);
    }

    public function test_aim_code39_y_code128(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear(']A016688');
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);

        $p2 = SurmarEtiquetaBarcodeSupport::parsear(']C116688');
        $this->assertSame('id', $p2['modo']);
        $this->assertSame(16688, $p2['etiqueta_id']);
    }

    public function test_aim_qr_json(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear(']Q3{"v":2,"etiqueta_id":16688}');
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);
    }

    public function test_qr_v2_json(): void
    {
        $qr = '{"v":2,"etiqueta_id":16688,"codigo_articulo":"Y108","nombre":"PALETA FRESCO","cantidad":1,"tipo_unidad":"BIN","nro_apertura":1,"kilos":427,"peso_bruto":462,"lote":"A-38141/1","vencimiento":"20/11/2026","fecha":"17/11/2026","peso_promedio":7.12}';
        $p = SurmarEtiquetaBarcodeSupport::parsear($qr);
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);
    }

    public function test_qr_con_prefijo_zpl_ma(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear('MA,{"v":2,"etiqueta_id":16688}');
        $this->assertSame('id', $p['modo']);
        $this->assertSame(16688, $p['etiqueta_id']);
    }

    public function test_anita_sku_nint_nap(): void
    {
        $p = SurmarEtiquetaBarcodeSupport::parsear('Y108-38141-1-COM-1');
        $this->assertSame('anita', $p['modo']);
        $this->assertSame('Y108', $p['sku']);
        $this->assertSame(38141, $p['nro_interno']);
        $this->assertSame(1, $p['nro_apertura']);
    }

    public function test_vacio(): void
    {
        $this->assertSame('vacio', SurmarEtiquetaBarcodeSupport::parsear('')['modo']);
        $this->assertSame('vacio', SurmarEtiquetaBarcodeSupport::parsear('   ')['modo']);
    }
}
