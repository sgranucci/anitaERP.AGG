<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaSucursalesInformeSupport;
use Tests\TestCase;

final class ArcaCaeaSucursalesInformeSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'app.empresa' => 'AGG',
            'anita.bdd_path' => '/usr2/biyemas',
        ]);

        parent::tearDown();
    }

    public function test_el_bierzo_solo_sucursal_5(): void
    {
        config(['app.empresa' => 'EL BIERZO']);

        self::assertSame([5], ArcaCaeaSucursalesInformeSupport::sucursalesPermitidas(1));
        self::assertTrue(ArcaCaeaSucursalesInformeSupport::esSucursalPermitida(5, 1));
        self::assertFalse(ArcaCaeaSucursalesInformeSupport::esSucursalPermitida(8, 1));
        self::assertFalse(ArcaCaeaSucursalesInformeSupport::esSucursalPermitida(15, 1));
        self::assertSame([], ArcaCaeaSucursalesInformeSupport::sucursalesPermitidas(2));
    }

    public function test_el_bierzo_no_usa_path_villafranca(): void
    {
        config([
            'app.empresa' => 'EL BIERZO',
            'anita.bdd_path' => '/usr2/bierzo',
        ]);

        self::assertSame('/usr2/bierzo', ArcaCaeaSucursalesInformeSupport::pathAnitaInforme());
        self::assertFalse(ArcaCaeaSucursalesInformeSupport::esPathVillafranca('/usr2/bierzo'));
        self::assertTrue(ArcaCaeaSucursalesInformeSupport::esPathVillafranca('/usr2/villafranca'));

        $payload = ArcaCaeaSucursalesInformeSupport::mergePathAnita(['acc' => 'list']);
        self::assertSame('/usr2/bierzo', $payload['path_sistema'] ?? null);
        self::assertStringNotContainsString('villafranca', (string) ($payload['path_sistema'] ?? ''));
    }

    public function test_agg_no_restringe_sucursal(): void
    {
        config(['app.empresa' => 'AGG']);

        self::assertNull(ArcaCaeaSucursalesInformeSupport::sucursalesPermitidas(1));
        self::assertTrue(ArcaCaeaSucursalesInformeSupport::esSucursalPermitida(20, 1));
    }
}
