<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const URL = 'compras/cumplir-requisicion-compra';

    private const NOMBRE = 'Cumplir requisición de compra';

    private const ICONO = 'fa-truck-loading';

    private const PERMISO_SLUG = 'cumplir-requisicion-compra';

    private const PERMISO_NOMBRE = 'Cumplir requisiciones de compra';

    public function up(): void
    {
        $refMenu = DB::table('menu')->where('url', 'compras/requisicion')->first();
        if (! $refMenu) {
            return;
        }
        $parentMenuId = (int) $refMenu->menu_id;

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => self::NOMBRE,
                'url' => self::URL,
                'orden' => $orden,
                'icono' => self::ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => self::NOMBRE,
                'icono' => self::ICONO,
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => self::PERMISO_NOMBRE,
                'updated_at' => now(),
            ]);
        }

        // Heredar roles del listado de requisiciones de compra.
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-requisicion')->value('id') ?? 0);
        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        }
        $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenu->id)->pluck('rol_id')->unique()->all();
        $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsMenu)));

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
            }
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
