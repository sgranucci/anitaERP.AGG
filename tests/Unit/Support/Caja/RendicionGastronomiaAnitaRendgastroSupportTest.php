<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use PHPUnit\Framework\TestCase;

final class RendicionGastronomiaAnitaRendgastroSupportTest extends TestCase
{
    private RendicionGastronomiaAnitaRendgastroSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new RendicionGastronomiaAnitaRendgastroSupport;
    }

    public function test_total_bruto_usa_z_cuando_ya_incluye_caea(): void
    {
        $total = $this->support->resolverTotalBrutoHost(
            1139100.57,
            52400.03,
            1086700.54,
            52400.03,
            0.02,
        );

        self::assertSame(1139100.57, $total);
    }

    public function test_total_bruto_suma_caea_cuando_z_es_solo_cae(): void
    {
        $total = $this->support->resolverTotalBrutoHost(
            1086700.54,
            52400.03,
            1086700.54,
            52400.03,
            0.02,
        );

        self::assertSame(1139100.57, $total);
    }

    public function test_total_bruto_sin_caea_devuelve_z(): void
    {
        $total = $this->support->resolverTotalBrutoHost(
            1072800.80,
            0.0,
            1072800.80,
            0.0,
            0.02,
        );

        self::assertSame(1072800.80, $total);
    }
}
