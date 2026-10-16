<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_comprobante_proveedor')) {
            return;
        }

        if (! Schema::hasColumn('configuracion_comprobante_proveedor', 'tolerancia_tc_pct')) {
            Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
                $table->decimal('tolerancia_tc_pct', 8, 4)->default(3)->after('tolerancia_precio_pct');
            });
        }

        if (Schema::hasTable('parametro_sistema')) {
            DB::table('parametro_sistema')->where('clave', 'factura_proveedor_tolerancia_tc_pct')->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuracion_comprobante_proveedor')) {
            return;
        }

        if (Schema::hasColumn('configuracion_comprobante_proveedor', 'tolerancia_tc_pct')) {
            Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('tolerancia_tc_pct');
            });
        }
    }
};
