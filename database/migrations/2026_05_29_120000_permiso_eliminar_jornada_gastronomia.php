<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/jornada';

    private const SLUG = 'eliminar-jornada-gastronomia';

    private const SLUG_REF_ROLES = 'gestionar-jornada-gastronomia';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Eliminar jornada gastronomía',
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Eliminar jornada gastronomía',
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF_ROLES)->value('id') ?? 0);
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();
    }
};
