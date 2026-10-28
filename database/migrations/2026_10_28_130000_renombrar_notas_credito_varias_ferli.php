<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: la pantalla de NC de facturas anteriores se llama Notas de crédito Varias.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/facturacion-local/nota-credito-factura-vieja';

    private const PERMISO_SLUG = 'emitir-nc-factura-vieja-local';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('menu')->where('url', self::MENU_URL)->update([
            'nombre' => 'Notas de crédito Varias',
            'updated_at' => now(),
        ]);

        DB::table('permiso')->where('slug', self::PERMISO_SLUG)->update([
            'nombre' => 'Emitir notas de crédito varias',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('menu')->where('url', self::MENU_URL)->update([
            'nombre' => 'NC facturas anteriores',
            'updated_at' => now(),
        ]);

        DB::table('permiso')->where('slug', self::PERMISO_SLUG)->update([
            'nombre' => 'Emitir NC facturas anteriores',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
