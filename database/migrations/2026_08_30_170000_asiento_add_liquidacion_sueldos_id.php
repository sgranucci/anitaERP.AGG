<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad asiento ↔ corrida de sueldos (fase 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento', 'liquidacion_sueldos_id')) {
                $table->unsignedBigInteger('liquidacion_sueldos_id')->nullable()->after('comprobante_proveedor_id');
                $table->foreign('liquidacion_sueldos_id')
                    ->references('id')
                    ->on('liquidacion_sueldos')
                    ->nullOnDelete();
                $table->index('liquidacion_sueldos_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            if (Schema::hasColumn('asiento', 'liquidacion_sueldos_id')) {
                $table->dropForeign(['liquidacion_sueldos_id']);
                $table->dropColumn('liquidacion_sueldos_id');
            }
        });
    }
};
