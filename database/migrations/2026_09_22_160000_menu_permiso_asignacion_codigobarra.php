<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asignación de códigos de barras por depósito (cámara).
 * Menú Stock + permiso solo para rol administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/asignacion-codigobarra';

    private const MENU_NOMBRE = 'Asignar código de barras';

    private const MENU_ICONO = 'fa-barcode';

    private const PERMISO_SLUG = 'asignar-codigobarra-articulo';

    private const PERMISO_NOMBRE = 'Asignar código de barras a artículos';

    private const ROL_ADMIN = 'administrador';

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/transferencia-mercaderia')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('menu_id') ?? 0);
        }
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        }

        $refOrden = (int) (DB::table('menu')->where('url', 'stock/transferencia-mercaderia')->value('orden') ?? 6);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $refOrden + 1,
                'icono' => self::MENU_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => $refOrden + 1,
                'icono' => self::MENU_ICONO,
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => mb_substr(self::PERMISO_NOMBRE, 0, 50),
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => mb_substr(self::PERMISO_NOMBRE, 0, 50),
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $adminId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);
        if ($adminId <= 0) {
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', '<>', $adminId)->delete();
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminId,
            ]);
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', '<>', $adminId)->delete();
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $adminId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $adminId,
            ]);
        }

        // Asegurar que el padre Stock también sea visible para admin.
        if ($parentMenuId > 0
            && ! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $adminId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $parentMenuId,
                'rol_id' => $adminId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
