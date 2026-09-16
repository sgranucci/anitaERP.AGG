<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorMenuRolSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecepcionProveedorMenuRolSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_slugs_extra_vacios_sin_menu_proceso(): void
    {
        $this->assertSame([], RecepcionProveedorMenuRolSupport::slugsExtraParaMenuIds([]));
        $this->assertSame([], RecepcionProveedorMenuRolSupport::slugsExtraParaMenuIds([999999]));
    }

    public function test_slugs_extra_cuando_menu_incluye_proceso(): void
    {
        $stockId = (int) DB::table('menu')->insertGetId([
            'menu_id' => 0,
            'nombre' => 'Módulo de Stock',
            'url' => '#',
            'orden' => 1,
            'icono' => 'fa-cubes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $procesoId = (int) DB::table('menu')->insertGetId([
            'menu_id' => $stockId,
            'nombre' => 'Recepción proveedores',
            'url' => RecepcionProveedorMenuRolSupport::URL_PROCESO,
            'orden' => 1,
            'icono' => 'fa-truck',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            RecepcionProveedorMenuRolSupport::SLUGS_CONFIG,
            RecepcionProveedorMenuRolSupport::slugsExtraParaMenuIds([$stockId, $procesoId])
        );
    }
}
