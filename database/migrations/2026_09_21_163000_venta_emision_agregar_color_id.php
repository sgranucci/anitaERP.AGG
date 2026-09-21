<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * color_id en venta_emision: Facturación Local (modo color/talle) y reportes por variante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_emision') || Schema::hasColumn('venta_emision', 'color_id')) {
            return;
        }

        Schema::table('venta_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('color_id')->nullable()->after('talle_id');
            $table->index('color_id', 'venta_emision_color_id_idx');
        });

        if (Schema::hasTable('color')) {
            Schema::table('venta_emision', function (Blueprint $table) {
                $table->foreign('color_id', 'fk_venta_emision_color')
                    ->references('id')->on('color')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_emision') || ! Schema::hasColumn('venta_emision', 'color_id')) {
            return;
        }

        Schema::table('venta_emision', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_venta_emision_color');
            } catch (\Throwable) {
                // índice/FK puede no existir en installs parciales
            }
            try {
                $table->dropIndex('venta_emision_color_id_idx');
            } catch (\Throwable) {
            }
            $table->dropColumn('color_id');
        });
    }
};
