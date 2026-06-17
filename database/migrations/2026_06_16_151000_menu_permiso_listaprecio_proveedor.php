<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/listaprecio_proveedor';

    /** Slugs de Listaprecio_ProveedorController y vistas del ABM. */
    private const SLUGS = [
        'listar-listaprecio-proveedor',
        'crear-listaprecio-proveedor',
        'editar-listaprecio-proveedor',
        'actualizar-listaprecio-proveedor',
        'borrar-listaprecio-proveedor',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuRequisicionId = (int) (DB::table('menu')->where('url', 'compras/requisicion')->value('id') ?? 0);

        if ($menuRequisicionId === 0) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->update([
                'menu_id' => $menuRequisicionId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
