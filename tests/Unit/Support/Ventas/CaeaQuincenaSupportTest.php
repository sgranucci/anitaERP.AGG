<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class CaeaQuincenaSupportTest extends TestCase
{
    public function test_quincena_abierta_si_sigue_vigente(): void
    {
        $hoy = Carbon::parse('2026-09-18');

        self::assertTrue(CaeaQuincenaSupport::quincenaAbiertaEnPantalla(
            '2026-09-30',
            '2026-10-05',
            $hoy,
        ));
    }

    public function test_quincena_abierta_si_sigue_el_plazo_de_informe(): void
    {
        $hoy = Carbon::parse('2026-09-18');

        self::assertTrue(CaeaQuincenaSupport::quincenaAbiertaEnPantalla(
            '2026-09-15',
            '2026-09-20',
            $hoy,
        ));
    }

    public function test_quincena_cerrada_despues_del_tope(): void
    {
        $hoy = Carbon::parse('2026-09-18');

        self::assertFalse(CaeaQuincenaSupport::quincenaAbiertaEnPantalla(
            '2026-08-31',
            '2026-09-05',
            $hoy,
        ));
    }
}
