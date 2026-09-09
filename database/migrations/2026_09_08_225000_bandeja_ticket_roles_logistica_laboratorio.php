<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende la bandeja de tickets a Logística y Laboratorio
 * (listar + menú; sin tomar/liberar — esas áreas usan asignación por admin).
 */
return new class extends Migration
{
    private const MENU_URL = 'ticket/bandeja';

    private const SLUG_LISTAR = 'listar-bandeja-ticket';

    /** @var list<string> */
    private const ROLES = [
        'Enc-logistica',
        'op-Logistica',
        'Enc-Laboratorio',
        'Op-Laboratorio',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_LISTAR)->value('id') ?? 0);
        if ($menuId === 0 || $permisoId === 0) {
            return;
        }

        foreach (self::ROLES as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }

            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_LISTAR)->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');

        if ($permisoId > 0 && $rolIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        if ($menuId > 0 && $rolIds->isNotEmpty()) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
