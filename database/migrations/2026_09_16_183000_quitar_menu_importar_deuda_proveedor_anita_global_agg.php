<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: saca el menú global "Importar deuda Anita".
 * La herramienta queda dentro del formulario de Pago a proveedores.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/importar-deuda-proveedor-anita';

    /** @var list<string> */
    private const PERMISOS = [
        'listar-importar-deuda-proveedor-anita',
        'ejecutar-importar-deuda-proveedor-anita',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No recrea el menú global: la importación quedó integrada en OP.
    }
};
