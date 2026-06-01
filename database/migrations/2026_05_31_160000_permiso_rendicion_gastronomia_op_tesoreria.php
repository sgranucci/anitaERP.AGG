<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendiciongastronomia';

    /** Perfiles cajeros / tesorería operativa. */
    private const ROLES_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
    ];

    /** Permisos mínimos para listar, rendir y consultar cierres. */
    private const PERMISOS = [
        'listar-rendicion-gastronomia-caja',
        'crear-rendicion-gastronomia-caja',
        'editar-rendicion-gastronomia-caja',
        'actualizar-rendicion-gastronomia-caja',
        'actualizar-rendicion-gastronomia-caja-dia',
        'ver-pdf-rendicion-gastronomia-caja',
        'ver-comprobante-cierre-turno-gastronomia',
        'listar-cierres-turno-gastronomia',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        if ($rolIds === []) {
            return;
        }

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }

            foreach (self::PERMISOS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
            }
            foreach (self::PERMISOS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId > 0) {
                    DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
