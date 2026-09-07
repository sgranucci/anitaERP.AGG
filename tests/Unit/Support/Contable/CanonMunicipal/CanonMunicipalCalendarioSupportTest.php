<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\CanonMunicipal;

use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalFichaSupport;
use PHPUnit\Framework\TestCase;

class CanonMunicipalCalendarioSupportTest extends TestCase
{
    public function test_texto_rango_mismo_mes(): void
    {
        $txt = CanonMunicipalCalendarioSupport::textoRangoPeriodo(
            '2026-08-03',
            '2026-08-09',
            'biyemas',
        );

        self::assertSame('03 al 09 de agosto de 2026', $txt);
    }

    public function test_texto_rango_cruza_meses(): void
    {
        $txt = CanonMunicipalCalendarioSupport::textoRangoPeriodo(
            '2026-08-31',
            '2026-09-06',
            'biyemas',
        );

        self::assertSame('31 de agosto al 06 de septiembre de 2026', $txt);
    }

    public function test_texto_rango_kandiko_capitaliza_mes(): void
    {
        $txt = CanonMunicipalCalendarioSupport::textoRangoPeriodo(
            '2026-08-31',
            '2026-09-06',
            'kandiko',
        );

        self::assertSame('31 de Agosto al 06 de Septiembre de 2026', $txt);
    }

    public function test_texto_rango_rebisco_usa_conector_a(): void
    {
        $txt = CanonMunicipalCalendarioSupport::textoRangoPeriodo(
            '2026-08-01',
            '2026-08-15',
            'rebisco',
        );

        self::assertSame('01 A 15 de agosto de 2026', $txt);
    }

    public function test_componer_direccion_extra(): void
    {
        $txt = CanonMunicipalFichaSupport::componerDireccionExtra('1870', 'AVELLANEDA', 'Buenos Aires');

        self::assertSame('1870, Avellaneda, Buenos Aires', $txt);
    }
}
