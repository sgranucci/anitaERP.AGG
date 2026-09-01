<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Sueldos\Lsd;

use App\Support\Sueldos\Lsd\LsdTipoLiquidacionSupport;
use PHPUnit\Framework\TestCase;

class LsdTipoLiquidacionSupportTest extends TestCase
{
    public function test_parse_periodo_acepta_yyyymm_y_mes_anio(): void
    {
        $this->assertSame(202607, LsdTipoLiquidacionSupport::parsePeriodo('202607'));
        $this->assertSame(202607, LsdTipoLiquidacionSupport::parsePeriodo('2026-07'));
        $this->assertSame(202607, LsdTipoLiquidacionSupport::parsePeriodo(null, 7, 2026));
        $this->assertSame(0, LsdTipoLiquidacionSupport::parsePeriodo('202613'));
        $this->assertSame(0, LsdTipoLiquidacionSupport::parsePeriodo(''));
    }

    public function test_label_periodo_es_legible(): void
    {
        $this->assertSame('Julio 2026', LsdTipoLiquidacionSupport::labelPeriodo(202607));
        $this->assertSame('07/2026', LsdTipoLiquidacionSupport::labelPeriodoCorto('2026-07'));
        $this->assertSame('', LsdTipoLiquidacionSupport::labelPeriodo(null));
    }
}
