<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\CanonEntidades;

use App\Support\Contable\CanonEntidades\CanonEntidadesMayorSupport;
use PHPUnit\Framework\TestCase;

class CanonEntidadesMayorSupportTest extends TestCase
{
    public function test_suma_solo_haber_maq_y_bin(): void
    {
        $particion = CanonEntidadesMayorSupport::particionar([
            ['tipo' => 'MAQ', 'haber' => 100.50, 'debe' => 0],
            ['ctav_tipo_asiento' => 'BIN', 'haber' => 20.25, 'debe' => 0],
            ['tipo' => 'MAQ', 'haber' => 0, 'debe' => 50.00],
            ['tipo' => 'PAG', 'haber' => 5.00, 'debe' => 999.00],
        ]);

        $this->assertEquals(100.50, $particion['haber_maq']);
        $this->assertEquals(20.25, $particion['haber_bin']);
        $this->assertEquals(120.75, $particion['haber_total']);
        $this->assertEquals(5.00, $particion['haber_otros']);
        $this->assertEquals(1049.00, $particion['debe_total']);
        $this->assertCount(2, $particion['comparables']);
        $this->assertCount(1, $particion['otros']);
    }

    public function test_deduplica_mismo_asiento(): void
    {
        $a = [
            'fecha' => '2026-07-01',
            'asiento_id' => 10,
            'cuenta_codigo' => '215010003',
            'haber' => 80.00,
            'debe' => 0,
            'tipo' => 'MAQ',
        ];

        $out = CanonEntidadesMayorSupport::deduplicar([$a, $a]);

        $this->assertCount(1, $out);
    }
}
