<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: los permisos CRUD de precios ya existen (opción vieja de Stock),
 * pero en este entorno quedaron sin menu_id y Menú Rol no los lista en Precios.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/precio';

    /** @var list<string> */
    private const SLUGS = [
        'listar-precios',
        'crear-precios',
        'editar-precios',
        'actualizar-precios',
        'borrar-precios',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
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
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        DB::table('permiso')
            ->where('menu_id', $menuId)
            ->whereIn('slug', self::SLUGS)
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
