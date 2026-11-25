<?php

namespace Tests\Unit\Sueldos;

use App\Support\Sueldos\ConceptoAnitaMapeo;
use App\Support\Sueldos\Formula\Anita\AnitaFormulaTraductor;
use App\Support\Sueldos\Formula\EntornoArray;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use PHPUnit\Framework\TestCase;

/**
 * Concepto 3000 Anita: `_d:=REDON(tg)` → ajuste al peso entero.
 */
class AjusteRedondeoFormulaTest extends TestCase
{
    private function motor(): EvaluadorFormula
    {
        return new EvaluadorFormula;
    }

    private function entornoConNeto(float $neto): EntornoArray
    {
        return new EntornoArray(
            [],
            [
                'acum' => function ($cod) use ($neto) {
                    if (strtoupper((string) $cod) === 'NETO') {
                        return $neto;
                    }

                    return 0.0;
                },
            ],
        );
    }

    public function test_traductor_redon_emite_ajuste_al_techo(): void
    {
        $trad = (new AnitaFormulaTraductor)->traducirConcepto(['_d:=REDON(tg)']);

        $this->assertTrue($trad->traducible);
        $this->assertNotNull($trad->formula);
        $this->assertStringContainsString('techo(', $trad->formula);
        $this->assertStringContainsString('acum("NETO")', $trad->formula);

        $this->assertEqualsWithDelta(
            0.36,
            $this->motor()->evaluar($trad->formula, $this->entornoConNeto(19366.64)),
            1e-9
        );
    }

    public function test_ajuste_redondeo_sube_al_peso_siguiente(): void
    {
        $f = ConceptoAnitaMapeo::FORMULA_AJUSTE_REDONDEO;

        $this->assertEqualsWithDelta(
            0.36,
            $this->motor()->evaluar($f, $this->entornoConNeto(19366.64)),
            1e-9
        );
        $this->assertEqualsWithDelta(
            0.81,
            $this->motor()->evaluar($f, $this->entornoConNeto(2138795.19)),
            1e-9
        );
        $this->assertEqualsWithDelta(
            0.0,
            $this->motor()->evaluar($f, $this->entornoConNeto(1000.00)),
            1e-9
        );
    }

    public function test_neto_mas_ajuste_queda_entero(): void
    {
        $f = ConceptoAnitaMapeo::FORMULA_AJUSTE_REDONDEO;
        foreach ([19366.64, 2138795.19, 95663.11, 100.01, 99.99] as $neto) {
            $ajuste = (float) $this->motor()->evaluar($f, $this->entornoConNeto($neto));
            $final = round($neto + $ajuste, 2);
            $this->assertSame(
                0.0,
                fmod($final, 1.0),
                "neto={$neto} ajuste={$ajuste} final={$final}"
            );
        }
    }
}
