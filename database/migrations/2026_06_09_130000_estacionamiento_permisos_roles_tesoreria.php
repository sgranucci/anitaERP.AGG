<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos de estacionamiento → Gerencia, Supervisor y Operador de Tesorería.
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Estacionamiento';

    /** Gerencia tesorería */
    private const ROLES_GERENCIA = [
        'Ger-Tesoreria',
    ];

    /** Supervisor / encargado tesorería (variantes históricas del ERP) */
    private const ROLES_SUPERVISOR = [
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    /** Operador tesorería */
    private const ROLES_OPERADOR = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $menuIds = $this->menuIdsEstacionamiento();
        $permisoIds = DB::table('permiso')
            ->where('slug', 'like', '%estacionamiento%')
            ->pluck('id')
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ($menuIds as $menuId) {
                $menuId = (int) $menuId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $permisoId) {
                $permisoId = (int) $permisoId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $nombres = array_values(array_unique(array_merge(
            self::ROLES_GERENCIA,
            self::ROLES_SUPERVISOR,
            self::ROLES_OPERADOR,
        )));

        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function menuIdsEstacionamiento(): array
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        $ids = [];
        if ($padreId > 0) {
            $ids[] = $padreId;
            $hijos = DB::table('menu')->where('menu_id', $padreId)->pluck('id')->all();
            $ids = array_merge($ids, $hijos);
        }

        $porUrl = DB::table('menu')
            ->where('url', 'like', 'caja/estacionamiento/%')
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($ids, $porUrl))));
    }

    public function down(): void
    {
        // Sin revertir: otros módulos pueden compartir los mismos roles.
    }
};
