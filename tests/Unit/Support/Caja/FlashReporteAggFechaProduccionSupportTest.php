<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashReporteAggFechaProduccionSupport;
use Carbon\Carbon;
use Tests\TestCase;

class FlashReporteAggFechaProduccionSupportTest extends TestCase
{
    public function test_fecha_es_ayer(): void
    {
        $ahora = Carbon::create(2026, 8, 31, 18, 30, 0);
        $fecha = FlashReporteAggFechaProduccionSupport::fecha($ahora);

        $this->assertSame('2026-08-30', $fecha->toDateString());
        $this->assertTrue($fecha->isStartOfDay());
    }

    public function test_periodo_mes_en_curso_anclado_a_produccion(): void
    {
        $periodo = FlashReporteAggFechaProduccionSupport::periodoMesEnCurso(
            Carbon::create(2026, 8, 31, 16, 0, 0)
        );

        $this->assertSame('2026-08-01', $periodo['desde']->toDateString());
        $this->assertSame('2026-08-30', $periodo['hasta']->toDateString());
    }
}
