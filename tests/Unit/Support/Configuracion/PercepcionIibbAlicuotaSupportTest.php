<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\PercepcionIibbAlicuotaSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): alícuota Tucumán desde coeficiente del padrón.
 */
class PercepcionIibbAlicuotaSupportTest extends TestCase
{
    public function test_tucuman_usa_coeficiente_si_no_hay_tasapercepcion(): void
    {
        $tasa = S::alicuotaDesdeCoeficienteTucuman(924, [
            'tasa' => null,
            'coeficiente' => 1.5,
            'excluido' => null,
        ]);

        self::assertSame(1.5, $tasa);
    }

    public function test_otras_jurisdicciones_no_toman_coeficiente(): void
    {
        $tasa = S::alicuotaDesdeCoeficienteTucuman(904, [
            'tasa' => null,
            'coeficiente' => 1.5,
        ]);

        self::assertNull($tasa);
    }

    public function test_tucuman_sin_coeficiente_sigue_vacio(): void
    {
        self::assertNull(S::alicuotaDesdeCoeficienteTucuman(924, [
            'tasa' => null,
            'coeficiente' => null,
            'excluido' => 'E',
        ]));
    }

    public function test_excluido_tucuman_anula_con_coeficiente_vacio(): void
    {
        $tasa = S::aplicarPoliticaTucumanAnita(924, [
            'excluido' => 'E',
            'coeficiente' => null,
        ], 5.0);

        self::assertSame(0.0, $tasa);
    }

    public function test_no_excluido_no_multiplica_coeficiente(): void
    {
        $tasa = S::aplicarPoliticaTucumanAnita(924, [
            'excluido' => null,
            'coeficiente' => 1.5,
        ], 1.5);

        self::assertSame(1.5, $tasa);
    }
}
