<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos maestros SIFAB (INTERFORMING) bajo Tablas de stock.
 * Asigna a todos los roles existentes.
 */
return new class extends Migration
{
    private const MENUS = [
        [
            'url' => 'stock/rubro',
            'nombre' => 'Rubros compra',
            'icono' => 'fa-folder-open',
            'permisos' => [
                ['slug' => 'listar-rubros', 'nombre' => 'Listar rubros compra'],
                ['slug' => 'crear-rubros', 'nombre' => 'Crear rubros compra'],
                ['slug' => 'editar-rubros', 'nombre' => 'Editar rubros compra'],
                ['slug' => 'actualizar-rubros', 'nombre' => 'Actualizar rubros compra'],
                ['slug' => 'borrar-rubros', 'nombre' => 'Borrar rubros compra'],
            ],
        ],
        [
            'url' => 'stock/subrubro',
            'nombre' => 'Subrubros',
            'icono' => 'fa-sitemap',
            'permisos' => [
                ['slug' => 'listar-subrubros', 'nombre' => 'Listar subrubros'],
                ['slug' => 'crear-subrubros', 'nombre' => 'Crear subrubros'],
                ['slug' => 'editar-subrubros', 'nombre' => 'Editar subrubros'],
                ['slug' => 'actualizar-subrubros', 'nombre' => 'Actualizar subrubros'],
                ['slug' => 'borrar-subrubros', 'nombre' => 'Borrar subrubros'],
            ],
        ],
        [
            'url' => 'stock/grupoproducto',
            'nombre' => 'Grupos producto',
            'icono' => 'fa-cubes',
            'permisos' => [
                ['slug' => 'listar-grupos-producto', 'nombre' => 'Listar grupos producto'],
                ['slug' => 'crear-grupos-producto', 'nombre' => 'Crear grupos producto'],
                ['slug' => 'editar-grupos-producto', 'nombre' => 'Editar grupos producto'],
                ['slug' => 'actualizar-grupos-producto', 'nombre' => 'Actualizar grupos producto'],
                ['slug' => 'borrar-grupos-producto', 'nombre' => 'Borrar grupos producto'],
            ],
        ],
        [
            'url' => 'stock/centroemisor',
            'nombre' => 'Centros emisores',
            'icono' => 'fa-building',
            'permisos' => [
                ['slug' => 'listar-centros-emisores', 'nombre' => 'Listar centros emisores'],
                ['slug' => 'crear-centros-emisores', 'nombre' => 'Crear centros emisores'],
                ['slug' => 'editar-centros-emisores', 'nombre' => 'Editar centros emisores'],
                ['slug' => 'actualizar-centros-emisores', 'nombre' => 'Actualizar centros emisores'],
                ['slug' => 'borrar-centros-emisores', 'nombre' => 'Borrar centros emisores'],
            ],
        ],
    ];

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        $tablasStockId = $this->resolverMenuTablasStockId();
        if ($tablasStockId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($rolIds === []) {
            return;
        }

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $tablasStockId)->max('orden') ?? 0);

        foreach (self::MENUS as $i => $def) {
            $orden = $ordenBase + $i + 1;
            $menuId = (int) (DB::table('menu')->where('url', $def['url'])->value('id') ?? 0);
            if ($menuId === 0) {
                $menuId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $tablasStockId,
                    'nombre' => $def['nombre'],
                    'url' => $def['url'],
                    'orden' => $orden,
                    'icono' => $def['icono'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('menu')->where('id', $menuId)->update([
                    'menu_id' => $tablasStockId,
                    'nombre' => $def['nombre'],
                    'icono' => $def['icono'],
                    'updated_at' => now(),
                ]);
            }

            $permisoIds = [];
            foreach ($def['permisos'] as $permiso) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
                if ($permisoId === 0) {
                    $permisoId = (int) DB::table('permiso')->insertGetId([
                        'nombre' => $permiso['nombre'],
                        'slug' => $permiso['slug'],
                        'menu_id' => $menuId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('permiso')->where('id', $permisoId)->update([
                        'menu_id' => $menuId,
                        'nombre' => $permiso['nombre'],
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
        }

        try {
            SuitecrmPermiso::flushCachePermisos();
        } catch (\Throwable) {
            // Cache tags no disponibles: no bloquear la migración
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        foreach (self::MENUS as $def) {
            foreach ($def['permisos'] as $permiso) {
                $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
                if ($permisoId) {
                    DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                    DB::table('permiso')->where('id', $permisoId)->delete();
                }
            }
            $menuId = DB::table('menu')->where('url', $def['url'])->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        try {
            SuitecrmPermiso::flushCachePermisos();
        } catch (\Throwable) {
            // Cache tags no disponibles: no bloquear la migración
        }
    }

    private function resolverMenuTablasStockId(): int
    {
        $moduloStockId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Stock')
                    ->orWhere('nombre', 'like', '%Módulo de Stock%')
                    ->orWhere('nombre', 'like', '%Modulo de Stock%');
            })
            ->where(function ($q) {
                $q->where('menu_id', 0)->orWhereNull('menu_id');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Tablas de stock')
                    ->orWhere('nombre', 'like', '%Tablas de stock%');
            })
            ->when($moduloStockId > 0, fn ($q) => $q->where('menu_id', $moduloStockId))
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'stock/categoria')->value('menu_id') ?? 0);
    }
};
