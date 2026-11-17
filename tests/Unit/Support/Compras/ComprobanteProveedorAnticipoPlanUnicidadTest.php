<?php

namespace Tests\Unit\Support\Compras;

use PHPUnit\Framework\TestCase;

/**
 * Regla de unicidad de anticipo según plan «Comprobantes a venir».
 * Lógica pura (sin DB): tope = max(1, cantidad del plan); plan vacío → 1.
 */
class ComprobanteProveedorAnticipoPlanUnicidadTest extends TestCase
{
    public function test_sin_plan_solo_una_anticipo(): void
    {
        $max = $this->maxDesdePlan(0);
        $this->assertSame(1, $max);
        $this->assertTrue($this->okUnicidad(0, $max));
        $this->assertFalse($this->okUnicidad(1, $max));
    }

    public function test_plan_de_14_cuotas_admite_hasta_14(): void
    {
        $max = $this->maxDesdePlan(14);
        $this->assertSame(14, $max);
        $this->assertTrue($this->okUnicidad(0, $max));
        $this->assertTrue($this->okUnicidad(2, $max));
        $this->assertTrue($this->okUnicidad(13, $max));
        $this->assertFalse($this->okUnicidad(14, $max));
    }

    public function test_acumulado_bajo_esperado_no_es_desvio_en_multi(): void
    {
        $esperado = 276892.77;
        $acumulado = 19919.96 * 3;
        $tol = 1.0;
        $tope = round($esperado * (1 + $tol / 100.0), 2);

        $this->assertTrue($acumulado <= $tope + 0.05);
    }

    public function test_acumulado_sobre_esperado_es_desvio_en_multi(): void
    {
        $esperado = 10000.0;
        $acumulado = 12000.0;
        $tol = 1.0;
        $tope = round($esperado * (1 + $tol / 100.0), 2);

        $this->assertFalse($acumulado <= $tope + 0.05);
    }

    private function maxDesdePlan(int $cantidadPlan): int
    {
        if ($cantidadPlan <= 0) {
            return 1;
        }

        return max(1, $cantidadPlan);
    }

    private function okUnicidad(int $otras, int $max): bool
    {
        return $otras < $max;
    }
}
