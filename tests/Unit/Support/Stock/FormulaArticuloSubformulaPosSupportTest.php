<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Support\Stock\FormulaArticuloSubformulaPosSupport;
use Tests\TestCase;

final class FormulaArticuloSubformulaPosSupportTest extends TestCase
{
    public function test_usa_detalle_cuando_subformula_sin_articulo(): void
    {
        $sub = new Formula_Articulo([
            'codigo' => '2061',
            'detalle' => 'POLLO RELLENO',
            'articulo_id' => null,
        ]);
        $sub->id = 2154;

        $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional($sub, 2154);

        $this->assertSame('2061', $etiqueta['sku']);
        $this->assertSame('POLLO RELLENO', $etiqueta['descripcion']);
    }

    public function test_usa_articulo_vinculado_cuando_existe(): void
    {
        $sub = new Formula_Articulo([
            'codigo' => '361',
            'detalle' => 'BOTELLA DE COCA',
            'articulo_id' => 5617,
        ]);
        $sub->id = 1444;
        $sub->setRelation('articulos', (object) [
            'sku' => 'V0361',
            'descripcion' => 'BOTELLA COCA COLA - 600 CC',
        ]);

        $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional($sub, 1444);

        $this->assertSame('V0361', $etiqueta['sku']);
        $this->assertSame('BOTELLA COCA COLA - 600 CC', $etiqueta['descripcion']);
    }

    public function test_fallback_sin_formula(): void
    {
        $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional(null, 99);

        $this->assertSame('F#99', $etiqueta['sku']);
        $this->assertSame('Subfórmula', $etiqueta['descripcion']);
    }
}
