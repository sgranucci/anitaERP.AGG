<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos maestros SIFAB clase/línea/gestión (INTERFORMING).
 */
return new class extends Migration
{
    private const MENUS = [
        [
            'url' => 'stock/clasematerial',
            'nombre' => 'Clases material',
            'icono' => 'fa-tags',
            'permisos' => [
                ['slug' => 'listar-clases-material', 'nombre' => 'Listar clases de material'],
                ['slug' => 'crear-clases-material', 'nombre' => 'Crear clases de material'],
                ['slug' => 'editar-clases-material', 'nombre' => 'Editar clases de material'],
                ['slug' => 'actualizar-clases-material', 'nombre' => 'Actualizar clases de material'],
                ['slug' => 'borrar-clases-material', 'nombre' => 'Borrar clases de material'],
            ],
        ],
        [
            'url' => 'stock/lineamaterial',
            'nombre' => 'Líneas material',
            'icono' => 'fa-stream',
            'permisos' => [
                ['slug' => 'listar-lineas-material', 'nombre' => 'Listar líneas de material'],
                ['slug' => 'crear-lineas-material', 'nombre' => 'Crear líneas de material'],
                ['slug' => 'editar-lineas-material', 'nombre' => 'Editar líneas de material'],
                ['slug' => 'actualizar-lineas-material', 'nombre' => 'Actualizar líneas de material'],
                ['slug' => 'borrar-lineas-material', 'nombre' => 'Borrar líneas de material'],
            ],
        ],
        [
            'url' => 'stock/gestioncompra',
            'nombre' => 'Gestiones compra',
            'icono' => 'fa-handshake',
            'permisos' => [
                ['slug' => 'listar-gestiones-compra', 'nombre' => 'Listar gestiones de compra'],
                ['slug' => 'crear-gestiones-compra', 'nombre' => 'Crear gestiones de compra'],
                ['slug' => 'editar-gestiones-compra', 'nombre' => 'Editar gestiones de compra'],
                ['slug' => 'actualizar-gestiones-compra', 'nombre' => 'Actualizar gestiones de compra'],
                ['slug' => 'borrar-gestiones-compra', 'nombre' => 'Borrar gestiones de compra'],
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
