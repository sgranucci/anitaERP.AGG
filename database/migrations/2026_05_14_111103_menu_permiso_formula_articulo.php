<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/formula-articulo';

    public function up(): void
    {
        // Padre: raíz "Módulo de Stock" (hijo directo del menú lateral, mismo nivel que Artículos, Precios, etc.)
        $parentMenuId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Stock')
                    ->orWhere('nombre', 'like', '%Módulo de Stock%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 0);
        }
        if ($parentMenuId === 0) {
            $parentMenuId = 10;
        }

        $articuloMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('id') ?? 0);
        $refMenuId = $articuloMenuId;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Fórmulas de artículos',
                'url' => self::MENU_URL,
                'orden' => 5,
                'icono' => 'fa-flask',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Fórmulas de artículos',
                'orden' => 5,
                'icono' => 'fa-flask',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar fórmulas de artículos', 'slug' => 'listar-formula-articulo'],
            ['nombre' => 'Ingresar fórmulas de artículos', 'slug' => 'crear-formula-articulo'],
            ['nombre' => 'Editar fórmulas de artículos', 'slug' => 'editar-formula-articulo'],
            ['nombre' => 'Actualizar fórmulas de artículos', 'slug' => 'actualizar-formula-articulo'],
            ['nombre' => 'Borrar fórmulas de artículos', 'slug' => 'borrar-formula-articulo'],
        ];

        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-articulos')->value('id') ?? 0);

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

    public function down(): void
    {
        $slugs = [
            'listar-formula-articulo',
            'crear-formula-articulo',
            'editar-formula-articulo',
            'actualizar-formula-articulo',
            'borrar-formula-articulo',
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
