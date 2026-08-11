<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos CRUD del ABM Artículos (stock/articulo) — solo EL BIERZO.
 *
 * Slugs tomados del index activo y ArticuloController (sin extras de compras/reportes):
 * listar / crear / editar / actualizar / borrar / imprimir-articulos-qr.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/articulo';

    /** @var list<string> */
    private const ROLES_NOMBRE = [
        'administrador',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar articulos', 'slug' => 'listar-articulos'],
        ['nombre' => 'Ingresar articulos', 'slug' => 'crear-articulos'],
        ['nombre' => 'Editar articulos', 'slug' => 'editar-articulos'],
        ['nombre' => 'Actualizar articulos', 'slug' => 'actualizar-articulos'],
        ['nombre' => 'Borrar articulos', 'slug' => 'borrar-articulos'],
        ['nombre' => 'Imprimir articulos QR', 'slug' => 'imprimir-articulos-qr'],
    ];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoIds = $this->upsertPermisos($menuId);
        $rolIds = $this->resolverRolIds($menuId);
        $this->asignarRoles($menuId, (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0), $permisoIds, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        // Solo quita roles/asignaciones creadas para slugs que esta migración asegura;
        // no borra permisos históricos que puedan usarse en otros módulos.
        $slugsNuevos = ['crear-articulos', 'editar-articulos', 'actualizar-articulos'];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugsNuevos)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    /** @return list<int> */
    private function upsertPermisos(int $menuId): array
    {
        $ids = [];
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }
            $ids[] = $permisoId;
        }

        return $ids;
    }

    /** @return list<int> */
    private function resolverRolIds(int $menuId): array
    {
        $rolIds = [];

        foreach (self::ROLES_NOMBRE as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        $fromMenu = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id')->all();
        $rolIds = array_merge($rolIds, array_map('intval', $fromMenu));

        $listarId = (int) (DB::table('permiso')->where('slug', 'listar-articulos')->value('id') ?? 0);
        if ($listarId > 0) {
            $fromPerm = DB::table('permiso_rol')->where('permiso_id', $listarId)->pluck('rol_id')->all();
            $rolIds = array_merge($rolIds, array_map('intval', $fromPerm));
        }

        $rolIds = array_values(array_unique(array_filter($rolIds, fn ($id) => $id > 0)));

        return DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $permisoIds
     * @param  list<int>  $rolIds
     */
    private function asignarRoles(int $menuId, int $padreMenuId, array $permisoIds, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $menus = [$menuId];
            if ($padreMenuId > 0) {
                $menus[] = $padreMenuId;
            }
            foreach ($menus as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget("Permiso.rolid.$rolId");
            } catch (\Throwable) {
            }
        }
    }
};
