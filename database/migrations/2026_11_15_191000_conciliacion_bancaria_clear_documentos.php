<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oleada 2: FK de clearing bancario en cobranza e IE (caja_movimiento).
 * OP ya tiene pagoproveedor.interbanking_movimiento_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cobranza') && ! Schema::hasColumn('cobranza', 'interbanking_movimiento_id')) {
            Schema::table('cobranza', function (Blueprint $table) {
                $table->unsignedBigInteger('interbanking_movimiento_id')->nullable()->after('cobranza_origen_id');
                $table->unique('interbanking_movimiento_id', 'cobranza_interbanking_movimiento_uk');
            });
        }

        if (Schema::hasTable('caja_movimiento') && ! Schema::hasColumn('caja_movimiento', 'interbanking_movimiento_id')) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                $table->unsignedBigInteger('interbanking_movimiento_id')->nullable()->after('cbu_pago');
                $table->unique('interbanking_movimiento_id', 'caja_movimiento_interbanking_movimiento_uk');
            });
        }

        if (Schema::hasTable('conciliacion_bancaria_par')
            && ! Schema::hasColumn('conciliacion_bancaria_par', 'clearing_json')
        ) {
            Schema::table('conciliacion_bancaria_par', function (Blueprint $table) {
                $table->json('clearing_json')->nullable()->after('score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conciliacion_bancaria_par')
            && Schema::hasColumn('conciliacion_bancaria_par', 'clearing_json')
        ) {
            Schema::table('conciliacion_bancaria_par', function (Blueprint $table) {
                $table->dropColumn('clearing_json');
            });
        }

        if (Schema::hasTable('caja_movimiento')
            && Schema::hasColumn('caja_movimiento', 'interbanking_movimiento_id')
        ) {
            Schema::table('caja_movimiento', function (Blueprint $table) {
                $table->dropUnique('caja_movimiento_interbanking_movimiento_uk');
                $table->dropColumn('interbanking_movimiento_id');
            });
        }

        if (Schema::hasTable('cobranza')
            && Schema::hasColumn('cobranza', 'interbanking_movimiento_id')
        ) {
            Schema::table('cobranza', function (Blueprint $table) {
                $table->dropUnique('cobranza_interbanking_movimiento_uk');
                $table->dropColumn('interbanking_movimiento_id');
            });
        }
    }
};
