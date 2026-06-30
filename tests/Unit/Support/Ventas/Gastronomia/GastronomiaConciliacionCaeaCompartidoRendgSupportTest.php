<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaConciliacionCaeaCompartidoRendgSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaConciliacionCaeaCompartidoRendgSupportTest extends TestCase
{
    private GastronomiaConciliacionCaeaCompartidoRendgSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new GastronomiaConciliacionCaeaCompartidoRendgSupport;
    }

    public function test_ajuste_resta_caea_estacionamiento_ajena_del_z_salon(): void
    {
        $total = $this->support->ajustarTotalSiArrastraCaeaAjena(
            2421100.28,
            2421100.28,
            2416600.25,
            0.03,
            4500.0,
        );

        self::assertSame(2416600.28, $total);
    }

    public function test_ajuste_sin_monto_ajeno_no_modifica_total(): void
    {
        $total = $this->support->ajustarTotalSiArrastraCaeaAjena(
            1139100.57,
            1139100.57,
            1086700.54,
            52400.03,
            0.0,
        );

        self::assertSame(1139100.57, $total);
    }
}
