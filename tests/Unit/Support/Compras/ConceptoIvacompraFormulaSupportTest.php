<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use PHPUnit\Framework\TestCase;

class ConceptoIvacompraFormulaSupportTest extends TestCase
{
    public function test_parse_acepta_coeficiente_sin_cero_anita(): void
    {
        $parsed = ConceptoIvacompraFormulaSupport::parse('con(73)*.21');
        $this->assertNotNull($parsed);
        $this->assertSame('73', $parsed['codigo_base']);
        $this->assertEqualsWithDelta(0.21, $parsed['coeficiente'], 0.00001);
        $this->assertSame(21.0, ConceptoIvacompraFormulaSupport::tasaPorcentajeDesdeFormula('con(73)*.21'));
    }

    public function test_parse_formas_habituales(): void
    {
        $a = ConceptoIvacompraFormulaSupport::parse('con(51)*0.21');
        $this->assertSame('51', $a['codigo_base']);
        $this->assertEqualsWithDelta(0.21, $a['coeficiente'], 0.00001);

        $b = ConceptoIvacompraFormulaSupport::parse('CON(11) * 0,27');
        $this->assertSame('11', $b['codigo_base']);
        $this->assertEqualsWithDelta(0.27, $b['coeficiente'], 0.00001);

        $this->assertNull(ConceptoIvacompraFormulaSupport::parse('.'));
        $this->assertNull(ConceptoIvacompraFormulaSupport::parse(''));
    }
}
