<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita del menú «Sync canal Local»: se opera con
 * php artisan facturacion-local:sync-canal [--ejecutar]
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/facturacion-local/sync-canal';

    private const PERMISO_SLUG = 'sync-canal-facturacion-local';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        $permisoIds = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        // No recrea menú/permiso: el alta original está en 2026_09_12_140100.
        // Si hace falta restaurar, correr de nuevo esa migración en entorno limpio.
    }
};
