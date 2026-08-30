<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAC/NC Villafranca → FAC original de El Bierzo.
 * Reparto 101 no usa esta columna: el vínculo es venta.remito_id / remito.venta_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta') || Schema::hasColumn('venta', 'venta_origen_id')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_origen_id')->nullable()->after('remito_id');
            $table->foreign('venta_origen_id', 'fk_venta_venta_origen')
                ->references('id')->on('venta')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->index('venta_origen_id', 'venta_venta_origen_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta') || ! Schema::hasColumn('venta', 'venta_origen_id')) {
            return;
        }

        Schema::table('venta', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_venta_venta_origen');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('venta_venta_origen_id_index');
            } catch (\Throwable $e) {
            }
            $table->dropColumn('venta_origen_id');
        });
    }
};
