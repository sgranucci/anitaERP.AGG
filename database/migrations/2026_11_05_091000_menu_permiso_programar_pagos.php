<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pantalla para programar fecha de pago / urgentes sobre la proyección de pagos.
 * Menú genérico del ERP (todos los clientes), junto a Proyección de pagos.
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'compras/programar-pagos',
        'nombre' => 'Programar pagos',
        'icono' => 'fa-calendar-plus',
        'permisos' => [
            ['slug' => 'programar-pagos-proveedor', 'nombre' => 'Programar fecha y urgentes de pagos a proveedores'],
        ],
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'compras/proyeccion-pagos')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'compras/propuesta-pago')->value('menu_id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-reporte-proyeccion-pagos')->value('id') ?? 0);
        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->all();
        }
        $rolesPagos = DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->orWhere('nombre', 'administrador')
            ->pluck('id')
            ->all();
        $rolIds = array_values(array_unique(array_map('intval', array_merge($rolIds, $rolesPagos))));

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU['nombre'],
                'url' => self::MENU['url'],
                'orden' => $orden,
                'icono' => self::MENU['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU['nombre'],
                'icono' => self::MENU['icono'],
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::MENU['permisos'] as $permiso) {
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

        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }

        try {
            cache()->tags('Permiso')->flush();
        } catch (\Throwable) {
            cache()->flush();
        }
    }

    public function down(): void
    {
        foreach (self::MENU['permisos'] as $permiso) {
            $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
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
    }
};
