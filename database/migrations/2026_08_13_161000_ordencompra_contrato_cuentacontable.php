<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato sin recepción e imputación no por artículos: cuenta DEBE del neto
 * se indica en la OC y se usa al cargar cada factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'contrato_cuentacontable_id')) {
                $table->unsignedBigInteger('contrato_cuentacontable_id')->nullable()
                    ->after('contrato_imputacion_contable')
                    ->comment('Cuenta DEBE del neto cuando el contrato imputa sin artículos');
                $table->foreign('contrato_cuentacontable_id', 'fk_oc_contrato_cuentacontable')
                    ->references('id')->on('cuentacontable')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra') || ! Schema::hasColumn('ordencompra', 'contrato_cuentacontable_id')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            $table->dropForeign('fk_oc_contrato_cuentacontable');
            $table->dropColumn('contrato_cuentacontable_id');
        });
    }
};
