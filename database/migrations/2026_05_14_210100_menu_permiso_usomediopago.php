<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/usomediopago';

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'caja/cuentacaja')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('menu_id', '>', 0)
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%Tablas%tesorer%')
                        ->orWhere('nombre', 'like', '%tablas%tesorer%')
                        ->orWhere('nombre', 'like', '%Tablas de tesorer%');
                })
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('nombre', 'Módulo de Caja')
                ->where('menu_id', 0)
                ->value('id') ?? 104);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'caja/cuentacaja')->value('id') ?? 0);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Usos de medio de pago',
                'url' => self::MENU_URL,
                'orden' => 12,
                'icono' => 'fa-tags',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Usos de medio de pago',
                'orden' => 12,
                'icono' => 'fa-tags',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar usos de medio de pago', 'slug' => 'listar-usomediopago'],
            ['nombre' => 'Ingresar usos de medio de pago', 'slug' => 'crear-usomediopago'],
            ['nombre' => 'Editar usos de medio de pago', 'slug' => 'editar-usomediopago'],
            ['nombre' => 'Actualizar usos de medio de pago', 'slug' => 'actualizar-usomediopago'],
            ['nombre' => 'Borrar usos de medio de pago', 'slug' => 'borrar-usomediopago'],
        ];

        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-cuentas-de-caja')->value('id') ?? 0);

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
            } elseif ($refPermisoId > 0) {
                $refMenuFromPermiso = (int) (DB::table('permiso')->where('id', $refPermisoId)->value('menu_id') ?? 0);
                $rolIdsMenu = $refMenuFromPermiso > 0
                    ? DB::table('menu_rol')->where('menu_id', $refMenuFromPermiso)->pluck('rol_id')->unique()->all()
                    : [];
            } else {
                $rolIdsMenu = [];
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
            'listar-usomediopago',
            'crear-usomediopago',
            'editar-usomediopago',
            'actualizar-usomediopago',
            'borrar-usomediopago',
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
