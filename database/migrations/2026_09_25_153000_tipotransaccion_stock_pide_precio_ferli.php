<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag: el tipo exige precio unitario en movimientos de stock (UI Ferli + validación).
 * Seed COM solo en Ferli (en otras instalaciones el precio ya se muestra siempre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (! Schema::hasColumn('tipotransaccion_stock', 'pide_precio')) {
                $table->boolean('pide_precio')->default(false)->after('alta_npu');
            }
        });

        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'COM')
            ->whereNull('deleted_at')
            ->update([
                'pide_precio' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (EntornoEmpresaSupport::esFerli()) {
            DB::table('tipotransaccion_stock')
                ->where('abreviatura', 'COM')
                ->update([
                    'pide_precio' => 0,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (Schema::hasColumn('tipotransaccion_stock', 'pide_precio')) {
                $table->dropColumn('pide_precio');
            }
        });
    }
};
