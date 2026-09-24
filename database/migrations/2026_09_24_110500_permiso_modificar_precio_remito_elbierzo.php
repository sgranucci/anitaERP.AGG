<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso modificar-precio-remito (El Bierzo).
 * Asignado al rol Despacho (perfil de omard) y administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/remito';

    private const PERMISO_SLUG = 'modificar-precio-remito';

    private const PERMISO_NOMBRE = 'Modificar precio en remitos';

    /** @var list<string> */
    private const ROLES = [
        'Despacho',
        'administrador',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $now = now();

        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => $now,
            ]);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::ROLES as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
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
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
