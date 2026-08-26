<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Stock\RecuentoImportColumnasSupport;
use Tests\TestCase;

class RecuentoImportColumnasSupportTest extends TestCase
{
    public function test_reconoce_encabezado_del_excel_exportado(): void
    {
        $encabezados = ['SKU', 'Descripción', 'Color', 'Talle', 'UM', 'Saldo sistema', 'Contado', 'Diferencia a ajustar'];

        $this->assertTrue(RecuentoImportColumnasSupport::pareceFilaEncabezado($encabezados));

        $sku = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            RecuentoImportColumnasSupport::COL_SKU_DEFAULT,
            RecuentoImportColumnasSupport::COL_SKU_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_SKU
        );
        $cantidad = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            RecuentoImportColumnasSupport::COL_CANTIDAD_DEFAULT,
            RecuentoImportColumnasSupport::COL_CANTIDAD_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_CANTIDAD
        );

        $this->assertNotNull($sku);
        $this->assertSame('SKU', $sku['titulo']);
        $this->assertNotNull($cantidad);
        $this->assertSame('Contado', $cantidad['titulo']);
    }

    public function test_ignora_titulo_del_export_como_encabezado(): void
    {
        $this->assertFalse(RecuentoImportColumnasSupport::pareceFilaEncabezado(['Recuento RC-000123']));
        $this->assertFalse(RecuentoImportColumnasSupport::pareceFilaEncabezado(['Fecha: 26/08/2026 | Estado: Pendiente']));
    }

    public function test_normaliza_sku_numerico_de_excel(): void
    {
        $this->assertSame('203620', RecuentoImportColumnasSupport::normalizarSkuCelda(203620.0));
        $this->assertSame('203620', RecuentoImportColumnasSupport::normalizarSkuCelda(203620));
        $this->assertSame('0000000203620', RecuentoImportColumnasSupport::normalizarSkuCelda('0000000203620'));
    }

    public function test_candidatos_sku_incluyen_padding_y_sin_ceros(): void
    {
        $this->assertContains('203620', RecuentoImportColumnasSupport::candidatosSku('0000000203620'));
        $this->assertContains('0000000203620', RecuentoImportColumnasSupport::candidatosSku('203620'));
    }

    public function test_guion_largo_de_export_es_color_talle_vacio(): void
    {
        $this->assertTrue(RecuentoImportColumnasSupport::esValorVacioColorTalle('—'));
        $this->assertTrue(RecuentoImportColumnasSupport::esValorVacioColorTalle('-'));
        $this->assertTrue(RecuentoImportColumnasSupport::esValorVacioColorTalle('n/a'));
        $this->assertFalse(RecuentoImportColumnasSupport::esValorVacioColorTalle('Negro'));
    }

    public function test_normaliza_cantidad_con_coma(): void
    {
        $this->assertSame(12.5, RecuentoImportColumnasSupport::normalizarCantidad('12,5'));
        $this->assertSame(1500.0, RecuentoImportColumnasSupport::normalizarCantidad('1.500,00'));
        $this->assertNull(RecuentoImportColumnasSupport::normalizarCantidad(''));
    }

    public function test_reusa_normalizacion_de_precios(): void
    {
        $this->assertSame('cantidad_contada', PrecioImportColumnasSupport::normalizarNombreColumna('Cantidad contada'));
        $this->assertSame('contado', PrecioImportColumnasSupport::normalizarNombreColumna('Contado'));
    }
}
