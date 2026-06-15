<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operadores de tesorería y contaduría operativa pueden solicitar aperturas programadas.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'enc-Tesoreria Operativa',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Op-contaduria',
        'Op-impuestos',
    ];

    /** @var list<string> */
    private const PERMISOS = [
        'listar-apertura-periodo-contable',
        'solicitar-apertura-periodo-contable',
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', 'contable/apertura-periodo')->value('id') ?? 0);
        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function down(): void
    {
        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }

            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', 'contable/apertura-periodo')->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
