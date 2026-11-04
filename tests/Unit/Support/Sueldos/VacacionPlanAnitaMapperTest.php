<?php

namespace Tests\Unit\Support\Sueldos;

use App\Support\Sueldos\VacacionPlanAnitaMapper;
use PHPUnit\Framework\TestCase;

class VacacionPlanAnitaMapperTest extends TestCase
{
    public function test_plan_sin_tomar_queda_planificado_desde_la_salida(): void
    {
        $evento = VacacionPlanAnitaMapper::evento(202601, 20270106, 0, 14, false);

        $this->assertSame(2026, $evento['anio']);
        $this->assertSame('2027-01-06', $evento['fecha_desde']);
        $this->assertSame('2027-01-19', $evento['fecha_hasta']);
        $this->assertSame(14.0, $evento['dias']);
        $this->assertSame('planificada', $evento['estado']);
    }

    public function test_dias_tomados_sin_liquidar_queda_tomada(): void
    {
        $evento = VacacionPlanAnitaMapper::evento(202601, 20261103, 14, 14, false);

        $this->assertSame('tomada', $evento['estado']);
        $this->assertSame('2026-11-03', $evento['fecha_desde']);
        $this->assertSame('2026-11-16', $evento['fecha_hasta']);
    }

    public function test_liquidada_en_anita_queda_liquidada(): void
    {
        $evento = VacacionPlanAnitaMapper::evento(202601, 20261103, 14, 14, true);

        $this->assertSame('liquidada', $evento['estado']);
    }

    public function test_sin_dias_o_fecha_imposible_no_arma_tramo(): void
    {
        $this->assertNull(VacacionPlanAnitaMapper::evento(202601, 20270106, 0, 0, false));
        $this->assertNull(VacacionPlanAnitaMapper::evento(202601, 19521201, 0, 14, false));
        $this->assertNull(VacacionPlanAnitaMapper::evento(202601, 0, 7, 7, false));
    }
}
