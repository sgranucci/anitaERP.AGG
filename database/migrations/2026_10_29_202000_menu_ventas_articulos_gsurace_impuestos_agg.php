<?php

use App\Models\Admin\Menu_Rol;
use App\Models\Admin\Permiso_Rol;
use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: el informe Ventas de artículos (y su conciliación, misma pantalla de menú)
 * queda para gsurace en su rol de impuestos, con los permisos de ese menú.
 * No corre en Crown ni en el resto de las instalaciones.
 */
return new class extends Migration
{
    private const USUARIO = 'gsurace';

    private const MENU_URL = 'ventas/gastronomia/ventas-articulos-reporte';

    private const ROL_RESPALDO = 'Op-impuestos';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $rolIds = $this->rolesImpuestosDeGsurace();
        if ($rolIds === []) {
            return;
        }

        foreach ($rolIds as $rolId) {
            $this->asignarCadenaMenu($menuId, $rolId);
            $this->asignarPermisosDelMenu($menuId, $rolId);
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = $this->rolesImpuestosDeGsurace();
        if ($menuId <= 0 || $rolIds === []) {
            return;
        }

        if (Schema::hasTable('menu_rol')) {
            EloquentAuditDeleteSupport::each(
                Menu_Rol::query()->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)
            );
        }

        if (Schema::hasTable('permiso_rol') && Schema::hasColumn('permiso', 'menu_id')) {
            $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                EloquentAuditDeleteSupport::each(
                    Permiso_Rol::query()
                        ->whereIn('permiso_id', $permisoIds)
                        ->whereIn('rol_id', $rolIds)
                );
            }
        }

        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Rol de impuestos que ya tiene gsurace. Si todavía no tiene ninguno,
     * se lo deja en Op-impuestos y se usa ese rol.
     *
     * @return list<int>
     */
    private function rolesImpuestosDeGsurace(): array
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        $roles = [];

        if ($usuarioId > 0 && Schema::hasTable('usuario_rol')) {
            $roles = DB::table('usuario_rol as ur')
                ->join('rol as r', 'r.id', '=', 'ur.rol_id')
                ->where('ur.usuario_id', $usuarioId)
                ->where('r.nombre', 'like', '%impuesto%')
                ->pluck('r.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($roles !== []) {
            return array_values(array_unique($roles));
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_RESPALDO)->value('id') ?? 0);
        if ($rolId <= 0) {
            $rolId = (int) (DB::table('rol')->where('nombre', 'like', '%impuesto%')->orderBy('id')->value('id') ?? 0);
        }
        if ($rolId <= 0) {
            return [];
        }

        if ($usuarioId > 0 && Schema::hasTable('usuario_rol')) {
            $ya = DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $ya) {
                DB::table('usuario_rol')->insert([
                    'usuario_id' => $usuarioId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        return [$rolId];
    }

    private function asignarCadenaMenu(int $menuId, int $rolId): void
    {
        $actual = $menuId;
        $vistos = [];
        while ($actual > 0 && ! isset($vistos[$actual])) {
            $vistos[$actual] = true;
            if (! DB::table('menu_rol')->where('menu_id', $actual)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $actual,
                    'rol_id' => $rolId,
                ]);
            }
            $actual = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
        }
    }

    private function asignarPermisosDelMenu(int $menuId, int $rolId): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasColumn('permiso', 'menu_id') || ! Schema::hasTable('permiso_rol')) {
            return;
        }

        $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id');
        foreach ($permisoIds as $permisoId) {
            $permisoId = (int) $permisoId;
            if ($permisoId <= 0) {
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
    }
};
