<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grupos liq. final cuelga de Tablas de liquidación (conceptos/grupos),
 * no de Tablas de Sueldos (padrón: categorías, sindicatos, etc.).
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_ORIGEN = 'Tablas de Sueldos';

    private const MENU_DESTINO = 'Tablas de liquidación';

    private const HIJO_URL = 'sueldos/grupo-liq-final';

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        $destinoId = $moduloId > 0
            ? (int) (DB::table('menu')->where('nombre', self::MENU_DESTINO)->where('menu_id', $moduloId)->value('id') ?? 0)
            : 0;
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($destinoId === 0 || $menuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $destinoId)->max('orden') ?? 0) + 1;
        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $destinoId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        $origenId = $moduloId > 0
            ? (int) (DB::table('menu')->where('nombre', self::MENU_ORIGEN)->where('menu_id', $moduloId)->value('id') ?? 0)
            : 0;
        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($origenId === 0 || $menuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $origenId)->max('orden') ?? 0) + 1;
        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $origenId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
