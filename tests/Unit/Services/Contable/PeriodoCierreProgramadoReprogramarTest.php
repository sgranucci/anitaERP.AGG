<?php

namespace Tests\Unit\Services\Contable;

use App\Services\Contable\PeriodoCierreProgramadoService;
use Carbon\Carbon;
use Tests\TestCase;

class PeriodoCierreProgramadoReprogramarTest extends TestCase
{
    public function test_misma_fecha_sigue_bloqueada_si_el_cierre_esta_registrado(): void
    {
        $this->assertFalse(PeriodoCierreProgramadoService::fechaHastaPuedeReprogramarse(
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-31'),
        ));
    }

    public function test_misma_fecha_se_puede_repetir_si_el_cierre_fue_anulado(): void
    {
        $this->assertTrue(PeriodoCierreProgramadoService::fechaHastaPuedeReprogramarse(
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-09-30'),
        ));

        $this->assertTrue(PeriodoCierreProgramadoService::fechaHastaPuedeReprogramarse(
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-10-31'),
            null,
        ));
    }

    public function test_una_fecha_posterior_siempre_avanza(): void
    {
        $this->assertTrue(PeriodoCierreProgramadoService::fechaHastaPuedeReprogramarse(
            Carbon::parse('2026-10-31'),
            Carbon::parse('2026-11-15'),
            Carbon::parse('2026-10-31'),
        ));
    }
}
