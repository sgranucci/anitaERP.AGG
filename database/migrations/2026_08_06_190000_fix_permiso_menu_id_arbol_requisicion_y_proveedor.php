<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos de filtro por tipo de árbol mal vinculados al menú Requisiciones (225).
 * Solo los usa ArbolaprobacionRepository → menú Carga de árbol.
 * listar-requisicion-proveedor lo usa ProveedorController → menú Proveedores.
 */
return new class extends Migration
{
    private const MENU_ARBOL_URL = 'configuracion/arbolaprobacion';

    private const MENU_REQUISICION_URL = 'compras/requisicion';

    private const MENU_PROVEEDOR_URL = 'compras/proveedor';

    /** Filtro de tipo en ArbolaprobacionRepository (no CRUD de requisición). */
    private const SLUGS_ARBOL_REQUISICION = [
        'actualiza-arbol-requisicion',
        'consulta-arbol-requisicion',
    ];

    /** Solapa / listado desde ABM proveedor. */
    private const SLUGS_PROVEEDOR = [
        'listar-requisicion-proveedor',
    ];

    public function up(): void
    {
        $menuArbolId = (int) (DB::table('menu')->where('url', self::MENU_ARBOL_URL)->value('id') ?? 0);
        if ($menuArbolId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::SLUGS_ARBOL_REQUISICION)
                ->update([
                    'menu_id' => $menuArbolId,
                    'updated_at' => now(),
                ]);
        }

        $menuProveedorId = (int) (DB::table('menu')->where('url', self::MENU_PROVEEDOR_URL)->value('id') ?? 0);
        if ($menuProveedorId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::SLUGS_PROVEEDOR)
                ->update([
                    'menu_id' => $menuProveedorId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuRequisicionId = (int) (DB::table('menu')->where('url', self::MENU_REQUISICION_URL)->value('id') ?? 0);
        if ($menuRequisicionId > 0) {
            DB::table('permiso')
                ->whereIn('slug', array_merge(self::SLUGS_ARBOL_REQUISICION, self::SLUGS_PROVEEDOR))
                ->update([
                    'menu_id' => $menuRequisicionId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
