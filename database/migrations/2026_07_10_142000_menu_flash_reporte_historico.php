<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/flash/reporte-historico';

    private const MENU_PADRE_FLASH = 'caja/flash';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::MENU_PADRE_FLASH)->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $padreFlashId = (int) (DB::table('menu')->where('id', $padreId)->value('menu_id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreFlashId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreFlashId,
                'nombre' => 'Reporte histórico',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-line-chart',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreFlashId,
                'nombre' => 'Reporte histórico',
                'orden' => $orden,
                'icono' => 'fa-line-chart',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            if ($padreFlashId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreFlashId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreFlashId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
