<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El remito interno (RIN) no imputa en el mayor. La marca vive en el tipo
 * de comprobante para que la emisión no genere asiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tipotransaccion')
            && ! Schema::hasColumn('tipotransaccion', 'genera_asiento')) {
            Schema::table('tipotransaccion', function (Blueprint $table) {
                $table->boolean('genera_asiento')->default(true)->after('iva_ventas');
            });
        }

        if (! Schema::hasColumn('tipotransaccion', 'genera_asiento')) {
            return;
        }

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('tipotransaccion')
            ->where('abreviatura', 'RIN')
            ->update([
                'genera_asiento' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tipotransaccion')
            && Schema::hasColumn('tipotransaccion', 'genera_asiento')) {
            Schema::table('tipotransaccion', function (Blueprint $table) {
                $table->dropColumn('genera_asiento');
            });
        }
    }
};
