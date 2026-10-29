<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Kilos por artículo (SENASA) para los roles que ya operan Surmar.
 * Solo El Bierzo.
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'stock/kilos-articulo-surmar',
        'nombre' => 'Kilos por artículo',
        'icono' => 'fa-balance-scale',
        'permisos' => [
            ['slug' => 'listar-kilos-articulo-surmar', 'nombre' => 'Listar kilos por artículo Surmar'],
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $stockMenuId = $this->resolverMenuStockId();
        if ($stockMenuId <= 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/movimiento-surmar')->value('id')
            ?: DB::table('menu')->where('url', 'stock/recepcion-proveedor-surmar')->value('id')
            ?: 0);

        $rolIds = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];

        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
        $def = self::MENU;
        $menuId = (int) (DB::table('menu')->where('url', $def['url'])->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $stockMenuId,
                'nombre' => $def['nombre'],
                'url' => $def['url'],
                'orden' => $orden,
                'icono' => $def['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $stockMenuId,
                'nombre' => $def['nombre'],
                'icono' => $def['icono'],
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach ($def['permisos'] as $perm) {
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
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
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
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        foreach (self::MENU['permisos'] as $perm) {
            $permisoId = DB::table('permiso')->where('slug', $perm['slug'])->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
        $menuId = DB::table('menu')->where('url', self::MENU['url'])->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuStockId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Stock')->orWhere('nombre', 'like', '%Stock%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'stock/movimiento-surmar')->value('menu_id')
            ?: DB::table('menu')->where('url', 'stock/articulo')->value('menu_id')
            ?: 0);
    }
};
