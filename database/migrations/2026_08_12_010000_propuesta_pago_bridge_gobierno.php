<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge bancario + chequera en propuesta + snapshot monto autorizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagoproveedor') && ! Schema::hasColumn('pagoproveedor', 'interbanking_transferencia_id')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->unsignedBigInteger('interbanking_transferencia_id')->nullable()->after('propuesta_pago_id');
                $table->index('interbanking_transferencia_id');
            });
        }

        if (Schema::hasTable('propuesta_pago')) {
            Schema::table('propuesta_pago', function (Blueprint $table) {
                if (! Schema::hasColumn('propuesta_pago', 'chequera_id')) {
                    $table->unsignedBigInteger('chequera_id')->nullable()->after('cuentacaja_id');
                    $table->index('chequera_id');
                }
                if (! Schema::hasColumn('propuesta_pago', 'monto_autorizado')) {
                    $table->decimal('monto_autorizado', 18, 4)->nullable()->after('monto_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagoproveedor') && Schema::hasColumn('pagoproveedor', 'interbanking_transferencia_id')) {
            Schema::table('pagoproveedor', function (Blueprint $table) {
                $table->dropColumn('interbanking_transferencia_id');
            });
        }
        if (Schema::hasTable('propuesta_pago')) {
            Schema::table('propuesta_pago', function (Blueprint $table) {
                foreach (['chequera_id', 'monto_autorizado'] as $col) {
                    if (Schema::hasColumn('propuesta_pago', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
