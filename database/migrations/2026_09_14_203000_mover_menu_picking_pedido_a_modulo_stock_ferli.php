<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Picking pedidos: mover del submenú Reportes Stock al Módulo de Stock (visible operativo).
 * Solo Calzados Ferli.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/picking-pedido';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $stockId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Stock')
            ->where('url', '#')
            ->value('id') ?? 10);

        if ($stockId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $stockId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $stockId,
            'orden' => $orden,
            'nombre' => 'Picking pedidos',
            'icono' => 'fa-clipboard-list',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $reportesId = (int) (DB::table('menu')
            ->where('nombre', 'Reportes Stock')
            ->where('url', '#')
            ->value('id') ?? 46);

        if ($reportesId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $reportesId,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
