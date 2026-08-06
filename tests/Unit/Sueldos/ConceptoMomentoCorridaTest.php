<?php

namespace Tests\Unit\Sueldos;

use App\Support\Sueldos\ConceptoMomentoCorrida;
use PHPUnit\Framework\TestCase;

/**
 * Tests puros (sin BD) del espejo cheq_momento Anita.
 */
class ConceptoMomentoCorridaTest extends TestCase
{
    public function test_siempre_aplica_en_cualquier_corrida(): void
    {
        foreach (['mensual', 'vacaciones', 'sac', 'final', 'ajuste', 'quincena_1'] as $tipo) {
            $this->assertTrue(
                ConceptoMomentoCorrida::aplica('mensual', $tipo),
                "SIEMPRE debe aplicar en {$tipo}"
            );
        }
    }

    public function test_no_liquida_nunca(): void
    {
        $this->assertFalse(ConceptoMomentoCorrida::aplica('no_liquida', 'mensual'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('no_liquida', 'vacaciones'));
    }

    public function test_vacaciones_solo_en_corrida_vacaciones(): void
    {
        $this->assertTrue(ConceptoMomentoCorrida::aplica('vacaciones', 'vacaciones'));
        $this->assertTrue(ConceptoMomentoCorrida::aplica('vacaciones_1q', 'vacaciones'));
        $this->assertTrue(ConceptoMomentoCorrida::aplica('vacaciones_2q', 'vacaciones'));

        $this->assertFalse(ConceptoMomentoCorrida::aplica('vacaciones', 'mensual'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('vacaciones', 'final'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('vacaciones', 'sac'));
    }

    public function test_mensual_2q_en_mensual_final_y_2da_quincena(): void
    {
        $this->assertTrue(ConceptoMomentoCorrida::aplica('mensual_2q', 'mensual'));
        $this->assertTrue(ConceptoMomentoCorrida::aplica('mensual_2q', 'final'));
        $this->assertTrue(ConceptoMomentoCorrida::aplica('mensual_2q', 'quincena_2'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('mensual_2q', 'vacaciones'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('mensual_2q', 'quincena_1'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('mensual_2q', 'sac'));
    }

    public function test_quincena_1_solo_primera(): void
    {
        $this->assertTrue(ConceptoMomentoCorrida::aplica('quincena_1', 'quincena_1'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('quincena_1', 'mensual'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('quincena_1', 'quincena_2'));
    }

    public function test_grupo_vacaciones_restringe_momentos(): void
    {
        $this->assertSame(
            ['vacaciones', 'vacaciones_1q', 'vacaciones_2q'],
            ConceptoMomentoCorrida::momentosPermitidosEnGrupo('vacaciones')
        );
        $this->assertNull(ConceptoMomentoCorrida::momentosPermitidosEnGrupo('mensual'));
        $this->assertNull(ConceptoMomentoCorrida::momentosPermitidosEnGrupo('final'));
    }

    public function test_sac_momento_solo_corrida_sac(): void
    {
        $this->assertTrue(ConceptoMomentoCorrida::aplica('sac', 'sac'));
        $this->assertFalse(ConceptoMomentoCorrida::aplica('sac', 'mensual'));
    }
}
