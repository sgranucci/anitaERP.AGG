<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/tipotransaccion_stock';

    private const PERMISOS = [
        ['slug' => 'listar-tipos-transaccion-stock', 'nombre' => 'Listar tipos de transacción de stock'],
        ['slug' => 'crear-tipos-transaccion-stock', 'nombre' => 'Crear tipos de transacción de stock'],
        ['slug' => 'editar-tipos-transaccion-stock', 'nombre' => 'Editar tipos de transacción de stock'],
        ['slug' => 'actualizar-tipos-transaccion-stock', 'nombre' => 'Actualizar tipos de transacción de stock'],
        ['slug' => 'borrar-tipos-transaccion-stock', 'nombre' => 'Borrar tipos de transacción de stock'],
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('id') ?? 0);
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-movimientos-de-stock')->value('id') ?? 0);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos transacción stock',
                'url' => self::MENU_URL,
                'orden' => 5,
                'icono' => 'fa-tags',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos transacción stock',
                'orden' => 5,
                'icono' => 'fa-tags',
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $permiso) {
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

        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        }
        if ($refMenuId > 0) {
            $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsMenu)));
        }

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rid,
                ]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $permiso) {
            $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
