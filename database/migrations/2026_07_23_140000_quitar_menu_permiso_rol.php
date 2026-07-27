<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita del menú "Permiso Rol": la matriz completa (todos los permisos × todos los roles)
 * agota memoria (Allowed memory size exhausted). La asignación de permisos por rol
 * se hace desde "Menú Rol" (admin/menu-rol).
 */
return new class extends Migration
{
    private const URL = 'admin/permiso-rol';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $parentId = (int) (DB::table('menu')->where('nombre', 'Administrador')->where('menu_id', 0)->value('id')
            ?? DB::table('menu')->where('id', 8)->value('id')
            ?? 0);
        if ($parentId === 0) {
            return;
        }

        if (DB::table('menu')->where('url', self::URL)->exists()) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;
        $menuId = (int) DB::table('menu')->insertGetId([
            'nombre' => 'Permiso Rol',
            'url' => self::URL,
            'menu_id' => $parentId,
            'orden' => $orden,
            'icono' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1, 9] as $rolId) {
            if (DB::table('rol')->where('id', $rolId)->exists()
                && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
