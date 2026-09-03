<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorDepositoCoeficienteFormulaTest extends TestCase
{
    public function test_usa_coeficiente_de_compra_si_es_positivo(): void
    {
        $compra = new Articulo(['coeficienteconversion' => 198]);
        $insumo = new Articulo(['coeficienteconversion' => 196]);

        $this->assertSame(198.0, RecepcionProveedorDepositoSupport::coeficienteConversionFormula($compra, $insumo));
    }

    public function test_cae_al_coeficiente_del_insumo_si_compra_es_cero(): void
    {
        $compra = new Articulo(['coeficienteconversion' => 0]);
        $insumo = new Articulo(['coeficienteconversion' => 800]);

        $this->assertSame(800.0, RecepcionProveedorDepositoSupport::coeficienteConversionFormula($compra, $insumo));
    }

    public function test_uno_si_ambos_sin_coeficiente(): void
    {
        $compra = new Articulo(['coeficienteconversion' => 0]);
        $insumo = new Articulo(['coeficienteconversion' => 0]);

        $this->assertSame(1.0, RecepcionProveedorDepositoSupport::coeficienteConversionFormula($compra, $insumo));
    }
}
