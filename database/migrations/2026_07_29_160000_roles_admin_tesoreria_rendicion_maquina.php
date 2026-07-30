<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajusta roles del módulo Rendición de máquinas:
 * administrador + todos los roles de tesorería (sin contaduría/finanzas heredados de usocuentacaja).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SLUGS = [
        'listar-rendicion-maquina',
        'crear-rendicion-maquina',
        'editar-rendicion-maquina',
        'actualizar-rendicion-maquina',
        'borrar-rendicion-maquina',
        'imprimir-rendicion-maquina',
        'ajustar-wigos-rendicion-maquina',
        'listar-ajustes-wigos-rendicion-maquina',
        'listar-apertura-gasto',
        'crear-apertura-gasto',
        'editar-apertura-gasto',
        'actualizar-apertura-gasto',
        'borrar-apertura-gasto',
    ];

    /** @var list<string> */
    private const MENU_URLS = [
        'caja/rendicion-maquina',
        'caja/apertura-gasto',
    ];

    private const MENU_PADRE_NOMBRE = 'Rendición de máquinas';

    /** @var list<string> */
    private const ROLES_OBJETIVO = [
        'administrador',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolIdsObjetivo();
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($permisoIds as $permisoId) {
            // Quitar roles ajenos (contaduría/finanzas/etc. heredados del menú ref).
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereNotIn('rol_id', $rolIds)
                ->delete();

            foreach ($rolIds as $rolId) {
                $exists = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $exists) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $menuIds = DB::table('menu')->whereIn('url', self::MENU_URLS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($padreId > 0) {
            $menuIds[] = $padreId;
        }
        $menuIds = array_values(array_unique(array_filter($menuIds)));

        foreach ($menuIds as $menuId) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereNotIn('rol_id', $rolIds)
                ->delete();

            foreach ($rolIds as $rolId) {
                $exists = DB::table('menu_rol')
                    ->where('menu_id', $menuId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $exists) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsObjetivo(): array
    {
        $porNombre = DB::table('rol')
            ->whereIn('nombre', self::ROLES_OBJETIVO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Cualquier otro rol con "tesorer" en el nombre (cubre variantes futuras).
        $porPatron = DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%tesorer%')
                    ->orWhere('nombre', 'like', '%Tesorer%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($porNombre, $porPatron)));
    }

    public function down(): void
    {
        // No revierte el recorte de roles ajenos (sería reintroducir contaduría/finanzas).
    }
};
