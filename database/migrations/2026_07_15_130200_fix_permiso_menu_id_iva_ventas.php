<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El permiso listar-iva-ventas se creó sin menu_id, por eso no aparece
 * en Admin → Menu-rol al seleccionar IVA ventas.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/iva-ventas';

    private const PERMISO_SLUG = 'listar-iva-ventas';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        DB::table('permiso')
            ->where('slug', self::PERMISO_SLUG)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        DB::table('permiso')
            ->where('slug', self::PERMISO_SLUG)
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
