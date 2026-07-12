<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag de comportamiento: tipo de transacción que da de baja NPU al confirmar salida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (! Schema::hasColumn('tipotransaccion_stock', 'baja_npu')) {
                $table->boolean('baja_npu')->default(false)->after('origen_bien_uso');
            }
        });

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'NPUBJ')
            ->update([
                'baja_npu' => 1,
                'operacion' => 'S',
                'signo' => -1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'NPUBJ')
            ->update(['baja_npu' => 0]);

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            if (Schema::hasColumn('tipotransaccion_stock', 'baja_npu')) {
                $table->dropColumn('baja_npu');
            }
        });
    }
};
