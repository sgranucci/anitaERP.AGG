<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: restaura Requisiciones, Órdenes de compra y Listas de precio
 * de proveedores bajo Módulo de Compras (no estaban en el menú ni los permisos CRUD).
 */
return new class extends Migration
{
    /** @var list<array{url: string, nombre: string, icono: string, orden: int, permisos: list<array{nombre: string, slug: string}>}> */
    private const ITEMS = [
        [
            'url' => 'compras/requisicion',
            'nombre' => 'Requisiciones',
            'icono' => 'fa-list-alt',
            'orden' => 2,
            'permisos' => [
                ['nombre' => 'Listar requisiciones', 'slug' => 'listar-requisicion'],
                ['nombre' => 'Ingresar requisiciones', 'slug' => 'crear-requisicion'],
                ['nombre' => 'Editar requisiciones', 'slug' => 'editar-requisicion'],
                ['nombre' => 'Actualizar requisiciones', 'slug' => 'actualizar-requisicion'],
                ['nombre' => 'Borrar requisiciones', 'slug' => 'borrar-requisicion'],
                ['nombre' => 'Grabar requisición en provisorio', 'slug' => 'guardar-requisicion-provisorio'],
                ['nombre' => 'Confirmar requisición provisoria', 'slug' => 'confirmar-requisicion'],
                ['nombre' => 'Volver requisición a compras', 'slug' => 'volver-compras-requisicion'],
                ['nombre' => 'Tablero seguimiento aprobación req.', 'slug' => 'seguimiento-aprobacion-requisicion'],
            ],
        ],
        [
            'url' => 'compras/ordencompra',
            'nombre' => 'Órdenes de compra',
            'icono' => 'fa-file-text-o',
            'orden' => 3,
            'permisos' => [
                ['nombre' => 'Listar ordenes de compra', 'slug' => 'listar-ordencompra'],
                ['nombre' => 'Ingresar ordenes de compra', 'slug' => 'crear-ordencompra'],
                ['nombre' => 'Editar ordenes de compra', 'slug' => 'editar-ordencompra'],
                ['nombre' => 'Actualizar ordenes de compra', 'slug' => 'actualizar-ordencompra'],
                ['nombre' => 'Borrar ordenes de compra', 'slug' => 'borrar-ordencompra'],
            ],
        ],
        [
            'url' => 'compras/listaprecio_proveedor',
            'nombre' => 'Listas de precio proveedores',
            'icono' => 'fa-tags',
            'orden' => 4,
            'permisos' => [
                ['nombre' => 'Listar listas precio proveedor', 'slug' => 'listar-listaprecio-proveedor'],
                ['nombre' => 'Ingresar listas precio proveedor', 'slug' => 'crear-listaprecio-proveedor'],
                ['nombre' => 'Editar listas precio proveedor', 'slug' => 'editar-listaprecio-proveedor'],
                ['nombre' => 'Actualizar listas precio proveedor', 'slug' => 'actualizar-listaprecio-proveedor'],
                ['nombre' => 'Borrar listas precio proveedor', 'slug' => 'borrar-listaprecio-proveedor'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $comprasMenuId = $this->resolverMenuComprasId();
        if ($comprasMenuId <= 0) {
            return;
        }

        $nuevos = 0;
        foreach (self::ITEMS as $item) {
            if ((int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0) === 0) {
                $nuevos++;
            }
        }
        if ($nuevos > 0) {
            $ordenMin = min(array_column(self::ITEMS, 'orden'));
            DB::table('menu')
                ->where('menu_id', $comprasMenuId)
                ->where('orden', '>=', $ordenMin)
                ->whereNotIn('url', array_column(self::ITEMS, 'url'))
                ->increment('orden', $nuevos);
        }

        $menuIds = [];
        $permisoIds = [];
        foreach (self::ITEMS as $item) {
            $menuId = $this->upsertMenu($comprasMenuId, $item);
            $menuIds[] = $menuId;
            foreach ($item['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }
        }

        $ocMenuId = (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('id') ?? 0);
        if ($ocMenuId > 0) {
            $precioPermisoId = (int) (DB::table('permiso')->where('slug', 'modificar-precio-ordencompra')->value('id') ?? 0);
            if ($precioPermisoId > 0) {
                DB::table('permiso')->where('id', $precioPermisoId)->update([
                    'menu_id' => $ocMenuId,
                    'updated_at' => now(),
                ]);
            }
        }

        $rolIds = $this->resolverRolIds($comprasMenuId);
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($comprasMenuId, $rolId);
            foreach ($menuIds as $menuId) {
                $this->asegurarMenuRol($menuId, $rolId);
            }
            foreach ($permisoIds as $permisoId) {
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
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $slugs = [];
        foreach (self::ITEMS as $item) {
            foreach ($item['permisos'] as $perm) {
                $slugs[] = $perm['slug'];
            }
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $urls = array_column(self::ITEMS, 'url');
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
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

    /**
     * @param  array{url: string, nombre: string, icono: string, orden: int}  $item
     */
    private function upsertMenu(int $comprasMenuId, array $item): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0);
        $payload = [
            'menu_id' => $comprasMenuId,
            'nombre' => $item['nombre'],
            'url' => $item['url'],
            'orden' => $item['orden'],
            'icono' => $item['icono'],
            'updated_at' => now(),
        ];
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update($payload);

            return $menuId;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(int $comprasMenuId): array
    {
        $ids = collect();

        $admin = DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'administrador')
                    ->orWhere('nombre', 'like', '%dmin%');
            })
            ->pluck('id');
        $ids = $ids->merge($admin);

        foreach (['Enc-compras', 'Op-Compras'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids->push($id);
            }
        }

        $proveedorMenuId = (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('id') ?? 0);
        foreach ([$comprasMenuId, $proveedorMenuId] as $mid) {
            if ($mid > 0) {
                $ids = $ids->merge(DB::table('menu_rol')->where('menu_id', $mid)->pluck('rol_id'));
            }
        }

        return $ids->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
};
