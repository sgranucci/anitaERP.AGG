<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta contable de ventas propia del local (distinta a fábrica).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('local_venta')) {
            return;
        }
        if (Schema::hasColumn('local_venta', 'cuentacontable_venta_id')) {
            return;
        }

        Schema::table('local_venta', function (Blueprint $table) {
            $table->unsignedBigInteger('cuentacontable_venta_id')->nullable()->after('cuentacaja_efectivo_id');
            $table->foreign('cuentacontable_venta_id', 'fk_local_venta_cta_venta')
                ->references('id')->on('cuentacontable')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('local_venta') || ! Schema::hasColumn('local_venta', 'cuentacontable_venta_id')) {
            return;
        }

        Schema::table('local_venta', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_local_venta_cta_venta');
            } catch (\Throwable $e) {
                // índice/fk puede no existir en installs parciales
            }
            $table->dropColumn('cuentacontable_venta_id');
        });
    }
};
