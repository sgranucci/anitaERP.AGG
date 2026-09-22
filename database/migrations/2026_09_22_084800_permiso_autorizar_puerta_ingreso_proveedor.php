<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autorizar / rechazar en puerta (Control de ingreso), sin tocar la bandeja ni el correo.
 * Se asigna a todos los roles que ya tienen el menú seguridad/control-ingreso.
 */
return new class extends Migration
{
    private const MENU_URL = 'seguridad/control-ingreso';

    private const SLUG = 'autorizar-puerta-ingreso-proveedor';

    private const NOMBRE = 'Autorizar / rechazar en puerta (control de ingreso)';

    public function up(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol') || ! Schema::hasTable('menu')) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::NOMBRE,
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
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
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};
