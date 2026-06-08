<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_ANTIGUA = 'stock/configuracion-recepcion-proveedor';
    private const MENU_URL = 'configuracion/recepcion-proveedor';

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('nombre', 'like', '%Configuraci%')
                ->where('url', '#')
                ->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL_ANTIGUA)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        }

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Recepción proveedores',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-truck',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Recepción proveedores',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-truck',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            'editar-configuracion-recepcion-proveedor',
            'actualizar-configuracion-recepcion-proveedor',
        ];

        foreach ($slugs as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        if ($refMenuId > 0) {
            $rolIdsRef = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            foreach ($rolIdsRef as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $stockMenuId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Stock')->orWhere('nombre', 'like', '%Stock%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($stockMenuId === 0) {
            $stockMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $stockMenuId,
            'nombre' => 'Config. recepción proveedores',
            'url' => self::MENU_URL_ANTIGUA,
            'orden' => $orden,
            'icono' => 'fa-cog',
            'updated_at' => now(),
        ]);

        $slugs = [
            'editar-configuracion-recepcion-proveedor',
            'actualizar-configuracion-recepcion-proveedor',
        ];

        foreach ($slugs as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }
    }
};
