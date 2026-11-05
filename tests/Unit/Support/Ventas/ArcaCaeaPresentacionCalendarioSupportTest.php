<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaPresentacionCalendarioSupport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaPresentacionCalendarioSupportTest extends TestCase
{
    public function test_el_dia_siguiente_habil_de_un_jueves_es_el_viernes(): void
    {
        $dia = ArcaCaeaPresentacionCalendarioSupport::primerDiaHabilSiguiente(
            Carbon::parse('2026-10-15'),
        );

        self::assertSame('2026-10-16', $dia->toDateString());
    }

    public function test_el_sabado_de_fin_de_mes_pasa_al_lunes(): void
    {
        $dia = ArcaCaeaPresentacionCalendarioSupport::primerDiaHabilSiguiente(
            Carbon::parse('2026-10-31'),
        );

        self::assertSame('2026-11-02', $dia->toDateString());
    }

    public function test_un_feriado_corre_la_presentacion_al_dia_habil_siguiente(): void
    {
        $dia = ArcaCaeaPresentacionCalendarioSupport::primerDiaHabilSiguiente(
            Carbon::parse('2026-10-31'),
            ['2026-11-02'],
        );

        self::assertSame('2026-11-03', $dia->toDateString());
    }

    public function test_la_segunda_quincena_de_octubre_2026_se_presenta_el_2_de_noviembre(): void
    {
        $quincenas = ArcaCaeaPresentacionCalendarioSupport::quincenasDelDia(
            Carbon::parse('2026-11-02'),
            [],
        );

        self::assertSame([['periodo' => 202610, 'orden' => 2]], $quincenas);
    }

    public function test_un_dia_intermedio_no_presenta(): void
    {
        $quincenas = ArcaCaeaPresentacionCalendarioSupport::quincenasDelDia(
            Carbon::parse('2026-11-05'),
            [],
        );

        self::assertSame([], $quincenas);
    }
}
