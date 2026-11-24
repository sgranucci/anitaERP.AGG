<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Op-Pagos: menú y permiso de Comprobantes en PDF.
 * Reportes (479) y Módulo de Compras (110) ya están en el rol.
 */
return new class extends Migration
{
    private const ROL = 'Op-Pagos';

    private const MENU_URL = 'compras/comprobantes-pdf-lote';

    private const PERMISO_SLUG = 'listar-comprobantes-pdf-lote';

    public function up(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($rolId === 0 || $menuId === 0 || $permisoId === 0) {
            return;
        }

        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        // Cadena de padres por si algún ambiente no la tenía.
        $actual = $menuId;
        $vistos = [];
        while ($actual > 0 && ! isset($vistos[$actual])) {
            $vistos[$actual] = true;
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            if (! DB::table('menu_rol')->where('menu_id', $padre)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padre, 'rol_id' => $rolId]);
            }
            $actual = $padre;
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($rolId > 0 && $menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
        }

        if ($rolId > 0 && $permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
