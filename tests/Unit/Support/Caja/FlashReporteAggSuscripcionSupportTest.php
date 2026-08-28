<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Flash\FlashReporteSuscripcion;
use App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport;
use Carbon\Carbon;
use Tests\TestCase;

class FlashReporteAggSuscripcionSupportTest extends TestCase
{
    public function test_periodo_mes_actual_corta_hoy(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'periodo_relativo' => FlashReporteSuscripcion::PERIODO_MES_ACTUAL,
        ]);
        $ahora = Carbon::create(2026, 8, 19, 16, 0, 0);
        $periodo = $support->periodoEfectivo($s, $ahora);

        $this->assertSame('2026-08-01', $periodo['desde']->toDateString());
        $this->assertSame('2026-08-19', $periodo['hasta']->toDateString());
    }

    public function test_periodo_mes_anterior_cierra_el_mes(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'periodo_relativo' => FlashReporteSuscripcion::PERIODO_MES_ANTERIOR,
        ]);
        $ahora = Carbon::create(2026, 8, 5, 8, 0, 0);
        $periodo = $support->periodoEfectivo($s, $ahora);

        $this->assertSame('2026-07-01', $periodo['desde']->toDateString());
        $this->assertSame('2026-07-31', $periodo['hasta']->toDateString());
    }

    public function test_destinatarios_deduplica_y_valida(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'destinatarios' => 'uno@agg.com; dos@agg.com, uno@agg.com malo',
        ]);

        $this->assertSame(['uno@agg.com', 'dos@agg.com'], $support->destinatariosResueltos($s));
    }

    public function test_corresponde_enviar_diaria_despues_de_la_hora(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'activo' => true,
            'periodicidad' => FlashReporteSuscripcion::PERIODICIDAD_DIARIA,
            'hora' => '16:00',
        ]);
        $s->ultima_ejecucion = null;

        $this->assertTrue($support->correspondeEnviar($s, Carbon::create(2026, 8, 19, 16, 5)));
        $this->assertFalse($support->correspondeEnviar($s, Carbon::create(2026, 8, 19, 15, 59)));
    }

    public function test_reintenta_si_office365_fallo_hace_rato(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'activo' => true,
            'periodicidad' => FlashReporteSuscripcion::PERIODICIDAD_DIARIA,
            'hora' => '16:00',
            'ultimo_estado' => FlashReporteSuscripcion::ESTADO_ERROR,
            'ultimo_mensaje' => 'No se pudo enviar el mail: Connection could not be established with host "smtp.office365.com:587" (Network is unreachable). Se reintentará automáticamente en unos minutos.',
        ]);
        $s->ultima_ejecucion = Carbon::create(2026, 8, 19, 16, 54, 0);

        $this->assertFalse($support->correspondeEnviar($s, Carbon::create(2026, 8, 19, 17, 0)));
        $this->assertTrue($support->correspondeEnviar($s, Carbon::create(2026, 8, 19, 17, 10)));
        $this->assertFalse($support->correspondeEnviar($s, Carbon::create(2026, 8, 20, 10, 0)));
    }

    public function test_no_reintenta_error_que_no_es_smtp(): void
    {
        $support = new FlashReporteAggSuscripcionSupport;
        $s = new FlashReporteSuscripcion([
            'activo' => true,
            'periodicidad' => FlashReporteSuscripcion::PERIODICIDAD_DIARIA,
            'hora' => '16:00',
            'ultimo_estado' => FlashReporteSuscripcion::ESTADO_ERROR,
            'ultimo_mensaje' => 'Sin destinatarios válidos: revisá los mails del envío.',
        ]);
        $s->ultima_ejecucion = Carbon::create(2026, 8, 19, 16, 5, 0);

        $this->assertFalse($support->correspondeEnviar($s, Carbon::create(2026, 8, 19, 17, 30)));
    }
}
