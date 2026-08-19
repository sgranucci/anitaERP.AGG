<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pérdidas de personal: solo Administrador + Tesorería + Capital Humano.
 * Quita Contaduría / Finanzas (heredados por error desde caja/usocuentacaja).
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Pérdidas de personal';

    private const MENU_PADRE_URL = '#';

    /** @var list<string> */
    private const MENU_HIJO_URLS = [
        'caja/concepto-perdida',
        'caja/imputacion-perdida',
        'caja/perdida-personal',
    ];

    /** @var list<string> */
    private const ROLES_PERMITIDOS = [
        'administrador',
        // Tesorería (supervisión / gerencia; no operadores)
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
        // Capital humano
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
    ];

    /** Roles que había que sacar explícitamente (además de cualquier otro no allowlisted). */
    private const ROLES_QUITAR = [
        'Enc-contaduría',
        'Enc-finanzas',
        'Op-Finanzas',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        $menuIds = $this->resolverMenuIds();
        if ($menuIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('menu_id', $menuIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $permitidos = $this->resolverRolIds(self::ROLES_PERMITIDOS);
        $quitar = $this->resolverRolIds(self::ROLES_QUITAR);

        // 1) Quitar Contaduría / Finanzas y cualquier rol fuera del allowlist
        $rolesAQuitar = DB::table('menu_rol')
            ->whereIn('menu_id', $menuIds)
            ->pluck('rol_id')
            ->merge(
                $permisoIds === []
                    ? collect()
                    : DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->pluck('rol_id')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $rolId) => in_array($rolId, $permitidos, true))
            ->merge($quitar)
            ->unique()
            ->values()
            ->all();

        if ($rolesAQuitar !== []) {
            DB::table('menu_rol')
                ->whereIn('menu_id', $menuIds)
                ->whereIn('rol_id', $rolesAQuitar)
                ->delete();

            if ($permisoIds !== []) {
                DB::table('permiso_rol')
                    ->whereIn('permiso_id', $permisoIds)
                    ->whereIn('rol_id', $rolesAQuitar)
                    ->delete();
            }
        }

        // 2) Asegurar allowlist (tesorería + capital humano + admin)
        foreach ($permitidos as $rolId) {
            foreach ($menuIds as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No restauramos Contaduría/Finanzas: el vínculo era incorrecto.
        // Solo quitamos Capital Humano agregado por esta migración.
        $menuIds = $this->resolverMenuIds();
        if ($menuIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('menu_id', $menuIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $capitalHumano = $this->resolverRolIds([
            'enc-Capital Humano',
            'op-Capital Humano',
            'ger-capitalhumano',
        ]);

        if ($capitalHumano !== []) {
            DB::table('menu_rol')
                ->whereIn('menu_id', $menuIds)
                ->whereIn('rol_id', $capitalHumano)
                ->delete();

            if ($permisoIds !== []) {
                DB::table('permiso_rol')
                    ->whereIn('permiso_id', $permisoIds)
                    ->whereIn('rol_id', $capitalHumano)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverMenuIds(): array
    {
        $ids = DB::table('menu')
            ->whereIn('url', self::MENU_HIJO_URLS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id') ?? 0);

        if ($padreId > 0) {
            $ids[] = $padreId;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
