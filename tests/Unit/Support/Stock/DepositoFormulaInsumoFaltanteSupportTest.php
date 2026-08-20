<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\DepositoFormulaInsumoFaltanteSupport;
use PHPUnit\Framework\TestCase;

class DepositoFormulaInsumoFaltanteSupportTest extends TestCase
{
    public function test_mensaje_articulo_sin_sku_alternativo(): void
    {
        $articulo = new Articulo;
        $articulo->id = 387;
        $articulo->sku = 'LIM0040';
        $articulo->descripcion = 'CESTO RECTO TAPA VAIVEN';
        $articulo->skualternativo = '';

        $msg = DepositoFormulaInsumoFaltanteSupport::mensajeArticulo($articulo);

        $this->assertStringContainsString('LIM0040', $msg);
        $this->assertStringContainsString('CESTO RECTO TAPA VAIVEN', $msg);
        $this->assertStringContainsString('falta SKU alternativo', $msg);
    }

    public function test_mensaje_listado_incluye_cantidad(): void
    {
        $msg = DepositoFormulaInsumoFaltanteSupport::mensajeListado([
            'LIM0040: falta SKU alternativo',
            'LIM0001: falta SKU alternativo',
        ]);

        $this->assertStringContainsString('2 artículos', $msg);
        $this->assertStringContainsString('LIM0040', $msg);
        $this->assertStringContainsString('LIM0001', $msg);
    }
}
