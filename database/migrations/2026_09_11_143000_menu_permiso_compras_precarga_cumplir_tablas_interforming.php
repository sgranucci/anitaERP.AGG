<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: Precarga, Cumplir requisición, Tipo servicio proveedor
 * y Sectores de legajo (menú + permisos CRUD).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $comprasId = $this->resolverMenuComprasId();
        $tablasId = $this->menuHijoPorNombre($comprasId, 'Tablas de compras');
        $cuentasPagarId = $this->menuHijoPorNombre($comprasId, 'Cuentas a pagar');
        if ($comprasId <= 0 || $tablasId <= 0 || $cuentasPagarId <= 0) {
            return;
        }

        $this->correrHermanos($comprasId, 3, 'compras/cumplir-requisicion-compra');

        $items = [
            [
                'padre' => $comprasId,
                'url' => 'compras/cumplir-requisicion-compra',
                'nombre' => 'Cumplir requisición de compra',
                'icono' => 'fa-truck',
                'orden' => 3,
                'permisos' => [
                    ['nombre' => 'Cumplir requisiciones de compra', 'slug' => 'cumplir-requisicion-compra'],
                    ['nombre' => 'Cambiar artículo al cumplir requisición de compra', 'slug' => 'cambiar-articulo-cumplir-requisicion-compra'],
                ],
            ],
            [
                'padre' => $cuentasPagarId,
                'url' => 'compras/precarga_comprobante_proveedor',
                'nombre' => 'Precarga',
                'icono' => 'fa-upload',
                'orden' => 1,
                'permisos' => [
                    ['nombre' => 'Listar precarga proveedores', 'slug' => 'listar-precarga-proveedores'],
                    ['nombre' => 'Ingresar precarga proveedores', 'slug' => 'crear-precarga-proveedores'],
                    ['nombre' => 'Editar precarga proveedores', 'slug' => 'editar-precarga-proveedores'],
                    ['nombre' => 'Actualizar precarga proveedores', 'slug' => 'actualizar-precarga-proveedores'],
                    ['nombre' => 'Borrar precarga proveedores', 'slug' => 'borrar-precarga-proveedores'],
                ],
            ],
            [
                'padre' => $tablasId,
                'url' => 'compras/tiposervicio_proveedor',
                'nombre' => 'Tipo servicio proveedor',
                'icono' => 'fa-briefcase',
                'orden' => 8,
                'permisos' => [
                    ['nombre' => 'Listar tipo de servicio de proveedores', 'slug' => 'listar-tipo-servicio-proveedor'],
                    ['nombre' => 'Ingresar tipo de servicio de proveedores', 'slug' => 'crear-tipo-servicio-proveedor'],
                    ['nombre' => 'Editar tipo de servicio de proveedores', 'slug' => 'editar-tipo-servicio-proveedor'],
                    ['nombre' => 'Actualizar tipo de servicio de proveedores', 'slug' => 'actualizar-tipo-servicio-proveedor'],
                    ['nombre' => 'Borrar tipo de servicio de proveedores', 'slug' => 'borrar-tipo-servicio-proveedor'],
                ],
            ],
            [
                'padre' => $tablasId,
                'url' => 'compras/sector_legajocompra',
                'nombre' => 'Sectores de legajo',
                'icono' => 'fa-sitemap',
                'orden' => 9,
                'permisos' => [
                    ['nombre' => 'Listar sector legajo compra', 'slug' => 'listar-sector-legajocompra'],
                    ['nombre' => 'Ingresar sector legajo compra', 'slug' => 'crear-sector-legajocompra'],
                    ['nombre' => 'Editar sector legajo compra', 'slug' => 'editar-sector-legajocompra'],
                    ['nombre' => 'Actualizar sector legajo compra', 'slug' => 'actualizar-sector-legajocompra'],
                    ['nombre' => 'Borrar sector legajo compra', 'slug' => 'borrar-sector-legajocompra'],
                ],
            ],
        ];

        $menuIds = [$comprasId, $tablasId, $cuentasPagarId];
        $permisoIds = [];
        $sectorMenuId = 0;

        foreach ($items as $item) {
            $menuId = $this->upsertMenu($item);
            $menuIds[] = $menuId;
            if ($item['url'] === 'compras/sector_legajocompra') {
                $sectorMenuId = $menuId;
            }
            foreach ($item['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }
        }

        if ($sectorMenuId > 0) {
            $todosId = (int) (DB::table('permiso')->where('slug', 'listar-todos-sector-legajo-compra')->value('id') ?? 0);
            if ($todosId > 0) {
                DB::table('permiso')->where('id', $todosId)->update([
                    'menu_id' => $sectorMenuId,
                    'updated_at' => now(),
                ]);
                $permisoIds[] = $todosId;
            }
        }

        $rolIds = $this->resolverRolIds($comprasId);
        $menuIds = array_values(array_unique(array_filter($menuIds)));
        $permisoIds = array_values(array_unique(array_filter($permisoIds)));

        foreach ($rolIds as $rolId) {
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

        $slugsNuevos = [
            'cumplir-requisicion-compra',
            'cambiar-articulo-cumplir-requisicion-compra',
            'listar-precarga-proveedores',
            'crear-precarga-proveedores',
            'editar-precarga-proveedores',
            'actualizar-precarga-proveedores',
            'borrar-precarga-proveedores',
            'listar-sector-legajocompra',
            'crear-sector-legajocompra',
            'editar-sector-legajocompra',
            'actualizar-sector-legajocompra',
            'borrar-sector-legajocompra',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugsNuevos)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $legajosId = (int) (DB::table('menu')->where('url', 'compras/legajos')->value('id') ?? 0);
        DB::table('permiso')
            ->where('slug', 'listar-todos-sector-legajo-compra')
            ->update([
                'menu_id' => $legajosId > 0 ? $legajosId : null,
                'updated_at' => now(),
            ]);
        DB::table('permiso')
            ->whereIn('slug', [
                'listar-tipo-servicio-proveedor',
                'crear-tipo-servicio-proveedor',
                'editar-tipo-servicio-proveedor',
                'actualizar-tipo-servicio-proveedor',
                'borrar-tipo-servicio-proveedor',
            ])
            ->update(['menu_id' => null, 'updated_at' => now()]);

        $urls = [
            'compras/cumplir-requisicion-compra',
            'compras/precarga_comprobante_proveedor',
            'compras/tiposervicio_proveedor',
            'compras/sector_legajocompra',
        ];
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  array{padre: int, url: string, nombre: string, icono: string, orden: int}  $item
     */
    private function upsertMenu(array $item): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $item['url'])->value('id') ?? 0);
        $payload = [
            'menu_id' => $item['padre'],
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

    private function correrHermanos(int $padreId, int $ordenDesde, string $urlNueva): void
    {
        if ((int) (DB::table('menu')->where('url', $urlNueva)->value('id') ?? 0) > 0) {
            return;
        }

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $ordenDesde)
            ->where('url', '!=', $urlNueva)
            ->increment('orden');
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

    private function menuHijoPorNombre(int $padreId, string $nombre): int
    {
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
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
