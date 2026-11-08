<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de fichas ya vive en Valores (FVAL/FCOL). Se retira el ABM
 * duplicado caja/tesoreria-crown/ficha (menú + permisos). La conversión del día
 * queda bajo Valores. Las tablas tesoreria_crown_ficha* (ledger PP) se conservan.
 */
return new class extends Migration
{
    private const URL = 'caja/tesoreria-crown/ficha';

    /** @var list<string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-ficha',
        'crear-tesoreria-crown-ficha',
        'editar-tesoreria-crown-ficha',
        'actualizar-tesoreria-crown-ficha',
        'eliminar-tesoreria-crown-ficha',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS)
            ->when($menuId, fn ($q) => $q->orWhere('menu_id', $menuId))
            ->pluck('id');

        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            // permiso.menu_id → menu.id: borrar permisos antes del menú
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        // No se restaura el ABM duplicado: el catálogo canónico es Valores.
    }
};
