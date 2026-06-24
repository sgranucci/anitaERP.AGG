<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/cot-electronico';

    private const PERMISO_SLUG = 'procesar-cot-electronico';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => null,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
