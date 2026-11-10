<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_PERSONAL = 'Personal';

    private const MENU_REPORTES = 'Reportes de Sueldos';

    private const URL = 'sueldos/sancion-reporte';

    public function up(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        $personalId = $moduloId > 0
            ? (int) (DB::table('menu')->where('nombre', self::MENU_PERSONAL)->where('menu_id', $moduloId)->value('id') ?? 0)
            : 0;
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($personalId === 0 || $menuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $personalId)->max('orden') ?? 0) + 1;
        DB::table('menu')->where('id', $menuId)->update([
            'nombre' => 'Consultas de sanciones',
            'menu_id' => $personalId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = (int) (DB::table('menu')->where('nombre', self::MENU_MODULO)->where('menu_id', 0)->value('id') ?? 0);
        $reportesId = $moduloId > 0
            ? (int) (DB::table('menu')->where('nombre', self::MENU_REPORTES)->where('menu_id', $moduloId)->value('id') ?? 0)
            : 0;
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($reportesId === 0 || $menuId === 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'nombre' => 'Sanciones de empleados',
            'menu_id' => $reportesId,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
