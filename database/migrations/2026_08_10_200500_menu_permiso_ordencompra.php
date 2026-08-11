<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restaura el ítem de menú «Órdenes de compra» y los permisos CRUD del ABM.
 *
 * Solo corre en EL BIERZO (config app.empresa). En AGG y otros no tiene efecto.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/ordencompra';

    private const MENU_NOMBRE = 'Órdenes de compra';

    private const MENU_ICONO = 'fa-file-text-o';

    /** Orden relativo bajo Módulo de Compras (después de Proveedores). */
    private const MENU_ORDEN = 2;

    /** @var list<string> */
    private const ROLES_NOMBRE = [
        'administrador',
        'Enc-compras',
        'Op-Compras',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar ordenes de compra', 'slug' => 'listar-ordencompra'],
        ['nombre' => 'Ingresar ordenes de compra', 'slug' => 'crear-ordencompra'],
        ['nombre' => 'Editar ordenes de compra', 'slug' => 'editar-ordencompra'],
        ['nombre' => 'Actualizar ordenes de compra', 'slug' => 'actualizar-ordencompra'],
        ['nombre' => 'Borrar ordenes de compra', 'slug' => 'borrar-ordencompra'],
    ];

    public function up(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $comprasMenuId = $this->resolverMenuComprasId();
        if ($comprasMenuId <= 0) {
            return;
        }

        $menuId = $this->upsertMenu($comprasMenuId);
        $permisoIds = $this->upsertPermisos($menuId);

        $precioPermisoId = (int) (DB::table('permiso')->where('slug', 'modificar-precio-ordencompra')->value('id') ?? 0);
        if ($precioPermisoId > 0) {
            DB::table('permiso')->where('id', $precioPermisoId)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds($comprasMenuId);
        $this->asignarRoles($comprasMenuId, $menuId, $permisoIds, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        if (! $this->esElBierzo()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function esElBierzo(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }

    private function resolverMenuComprasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Compras%')
                    ->orWhere('nombre', 'Módulo de Compras');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('menu_id') ?? 0);
    }

    private function upsertMenu(int $comprasMenuId): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = self::MENU_ORDEN;

        if ($menuId === 0) {
            DB::table('menu')
                ->where('menu_id', $comprasMenuId)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $comprasMenuId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => self::MENU_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $comprasMenuId,
            'nombre' => self::MENU_NOMBRE,
            'orden' => $orden,
            'icono' => self::MENU_ICONO,
            'updated_at' => now(),
        ]);

        return $menuId;
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
    private function resolverRolIds(int $comprasMenuId): array
    {
        $rolIds = [];

        foreach (self::ROLES_NOMBRE as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        $proveedorMenuId = (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('id') ?? 0);
        foreach ([$comprasMenuId, $proveedorMenuId] as $mid) {
            if ($mid <= 0) {
                continue;
            }
            $fromMenu = DB::table('menu_rol')->where('menu_id', $mid)->pluck('rol_id')->all();
            $rolIds = array_merge($rolIds, array_map('intval', $fromMenu));
        }

        $reportePermisoId = (int) (DB::table('permiso')->where('slug', 'listar-reporte-ordencompra')->value('id') ?? 0);
        if ($reportePermisoId > 0) {
            $fromPerm = DB::table('permiso_rol')->where('permiso_id', $reportePermisoId)->pluck('rol_id')->all();
            $rolIds = array_merge($rolIds, array_map('intval', $fromPerm));
        }

        $rolIds = array_values(array_unique(array_filter($rolIds, fn ($id) => $id > 0)));

        return DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $permisoIds
     * @param  list<int>  $rolIds
     */
    private function asignarRoles(int $comprasMenuId, int $menuId, array $permisoIds, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            foreach ([$comprasMenuId, $menuId] as $mid) {
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
