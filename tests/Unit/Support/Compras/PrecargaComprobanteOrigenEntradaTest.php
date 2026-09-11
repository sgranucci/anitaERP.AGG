<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Tests\TestCase;

class PrecargaComprobanteOrigenEntradaTest extends TestCase
{
    public function test_scan_y_legajo_sin_importes_esperados(): void
    {
        $this->assertTrue(PrecargaComprobanteOrigenEntrada::sinImportesEsperados(
            PrecargaComprobanteOrigenEntrada::SCAN_ANITA
        ));
        $this->assertTrue(PrecargaComprobanteOrigenEntrada::sinImportesEsperados(
            PrecargaComprobanteOrigenEntrada::LEGAJO
        ));
        $this->assertFalse(PrecargaComprobanteOrigenEntrada::sinImportesEsperados(
            PrecargaComprobanteOrigenEntrada::PDF_IA
        ));
        $this->assertFalse(PrecargaComprobanteOrigenEntrada::sinImportesEsperados(null));
    }

    public function test_aviso_fila_solo_para_origenes_sin_ocr(): void
    {
        $this->assertStringContainsString(
            'Scan sin OCR',
            (string) PrecargaComprobanteOrigenEntrada::avisoFilaSinImportes(
                PrecargaComprobanteOrigenEntrada::SCAN_ANITA
            )
        );
        $this->assertStringContainsString(
            'legajo',
            (string) PrecargaComprobanteOrigenEntrada::avisoFilaSinImportes(
                PrecargaComprobanteOrigenEntrada::LEGAJO
            )
        );
        $this->assertNull(PrecargaComprobanteOrigenEntrada::avisoFilaSinImportes(
            PrecargaComprobanteOrigenEntrada::MAIL
        ));
        $this->assertStringContainsString('$0', PrecargaComprobanteOrigenEntrada::leyendaSinImportes());
    }

    public function test_conservar_origen_al_adjuntar_pdf(): void
    {
        $this->assertTrue(PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            PrecargaComprobanteOrigenEntrada::API
        ));
        $this->assertTrue(PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            PrecargaComprobanteOrigenEntrada::MAIL
        ));
        $this->assertFalse(PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            PrecargaComprobanteOrigenEntrada::LEGAJO
        ));
        $this->assertFalse(PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            PrecargaComprobanteOrigenEntrada::SCAN_ANITA
        ));
    }
}
