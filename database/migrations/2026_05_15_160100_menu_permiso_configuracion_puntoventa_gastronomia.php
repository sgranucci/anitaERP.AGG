<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/configuracion-puntoventa-gastronomia';

    private const MENU_URL_REF = 'stock/mozo-gastronomia';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_URL_REF)->value('id') ?? $parentMenuId);

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Config. punto de venta',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-desktop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Config. punto de venta',
                'orden' => $orden,
                'icono' => 'fa-desktop',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar config. PV gastronomía', 'slug' => 'listar-configuracion-puntoventa-gastronomia'],
            ['nombre' => 'Ingresar config. PV gastronomía', 'slug' => 'crear-configuracion-puntoventa-gastronomia'],
            ['nombre' => 'Editar config. PV gastronomía', 'slug' => 'editar-configuracion-puntoventa-gastronomia'],
            ['nombre' => 'Actualizar config. PV gastronomía', 'slug' => 'actualizar-configuracion-puntoventa-gastronomia'],
            ['nombre' => 'Borrar config. PV gastronomía', 'slug' => 'borrar-configuracion-puntoventa-gastronomia'],
        ];

        $refPermisoId = 0;

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            if ($refPermisoId > 0) {
                $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
                foreach ($rolIds as $rolId) {
                    $rid = (int) $rolId;
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rid,
                        ]);
                    }
                }
            }

            if ($refMenuId > 0) {
                $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            } else {
                $rolIdsMenu = $refPermisoId > 0
                    ? DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all()
                    : [];
            }

            foreach ($rolIdsMenu as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 51);

        return (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $slugs = [
            'listar-configuracion-puntoventa-gastronomia',
            'crear-configuracion-puntoventa-gastronomia',
            'editar-configuracion-puntoventa-gastronomia',
            'actualizar-configuracion-puntoventa-gastronomia',
            'borrar-configuracion-puntoventa-gastronomia',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
