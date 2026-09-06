<?php

namespace Tests\Unit\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolTriggerCatalog;
use App\Support\Configuracion\ReArbolTriggerEvaluators\MontoMayorIgualEvaluator;
use App\Support\Configuracion\ReArbolTriggerEvaluators\MontoMenorEvaluator;
use App\Support\Configuracion\ReArbolTriggerEvaluators\ReArbolTriggerEvalContext;
use App\Support\Configuracion\ReArbolTriggerEvaluators\ReArbolTriggerEvaluatorRegistry;
use App\Support\Configuracion\ReArbolTriggerEvaluators\SiempreEvaluator;
use PHPUnit\Framework\TestCase;

class ReArbolTriggerEvaluatorsTest extends TestCase
{
    private function ctx(float $monto = 1000.0, int $monedaId = 1): ReArbolTriggerEvalContext
    {
        return new ReArbolTriggerEvalContext(
            new Arbolaprobacion,
            new Requisicion,
            1,
            $monto,
            $monedaId,
            '2026-09-06',
        );
    }

    public function test_registry_tiene_todos_los_evaluadores(): void
    {
        $registry = new ReArbolTriggerEvaluatorRegistry;
        foreach (ReArbolTriggerCatalog::evaluadores() as $codigo) {
            $this->assertContains($codigo, $registry->codigos());
        }
    }

    public function test_siempre_aplica(): void
    {
        $ev = new SiempreEvaluator;
        $this->assertTrue($ev->aplica($this->ctx(), new Arbolaprobacion_ReTrigger));
    }

    public function test_monto_mayor_igual(): void
    {
        $ev = new MontoMayorIgualEvaluator;
        $tr = new Arbolaprobacion_ReTrigger([
            'param_monto' => 5000,
            'param_moneda_id' => 1,
        ]);
        $this->assertTrue($ev->aplica($this->ctx(5000), $tr));
        $this->assertTrue($ev->aplica($this->ctx(5000.01), $tr));
        $this->assertFalse($ev->aplica($this->ctx(4999.99), $tr));
        $this->assertFalse($ev->aplica($this->ctx(9000, 2), $tr));
    }

    public function test_monto_menor(): void
    {
        $ev = new MontoMenorEvaluator;
        $tr = new Arbolaprobacion_ReTrigger(['param_monto' => 100, 'param_moneda_id' => null]);
        $this->assertTrue($ev->aplica($this->ctx(99.99), $tr));
        $this->assertFalse($ev->aplica($this->ctx(100), $tr));
    }

    public function test_monto_sin_umbral_no_aplica(): void
    {
        $ev = new MontoMayorIgualEvaluator;
        $tr = new Arbolaprobacion_ReTrigger(['param_monto' => 0]);
        $this->assertFalse($ev->aplica($this->ctx(999999), $tr));
    }
}
