<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ítem de menú «Tablero de compras» (réplica del Power BI de compras).
 * Usa el permiso existente `listar-kpi-compras`: cada rol que ve «KPIs de Compras»
 * (compras/kpi) ve también el tablero.
 */
return new class extends Migration
{
    private const URL = 'compras/tablero-compras';

    private const URL_KPI = 'compras/kpi';

    public function up(): void
    {
        $kpiId = (int) (DB::table('menu')->where('url', self::URL_KPI)->value('id') ?? 0);
        $comprasMenuId = (int) (DB::table('menu')->where('id', $kpiId)->value('menu_id') ?? 0);
        if ($kpiId <= 0 || $comprasMenuId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $ordenKpi = (int) (DB::table('menu')->where('id', $kpiId)->value('orden') ?? 0);
            $orden = $ordenKpi + 1;
            DB::table('menu')
                ->where('menu_id', $comprasMenuId)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $comprasMenuId,
                'nombre' => 'Tablero de compras',
                'url' => self::URL,
                'orden' => $orden,
                'icono' => 'fa-chart-pie',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')->where('menu_id', $kpiId)->pluck('rol_id')->all();
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
