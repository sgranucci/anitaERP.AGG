<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instrumentos de tesorería en cabecera de propuesta (caja + cuenta egreso).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('propuesta_pago')) {
            return;
        }

        Schema::table('propuesta_pago', function (Blueprint $table) {
            if (! Schema::hasColumn('propuesta_pago', 'caja_id')) {
                $table->unsignedBigInteger('caja_id')->nullable()->after('usuario_id');
                $table->index('caja_id');
            }
            if (! Schema::hasColumn('propuesta_pago', 'cuentacaja_id')) {
                $table->unsignedBigInteger('cuentacaja_id')->nullable()->after('caja_id');
                $table->index('cuentacaja_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('propuesta_pago')) {
            return;
        }

        Schema::table('propuesta_pago', function (Blueprint $table) {
            foreach (['caja_id', 'cuentacaja_id'] as $col) {
                if (Schema::hasColumn('propuesta_pago', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
