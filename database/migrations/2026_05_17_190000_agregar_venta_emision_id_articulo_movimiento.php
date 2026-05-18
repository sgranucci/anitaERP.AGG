<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulo_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_movimiento', 'venta_emision_id')) {
                $table->unsignedBigInteger('venta_emision_id')->nullable()->after('venta_id');
                $table->foreign('venta_emision_id', 'fk_articulo_movimiento_venta_emision')
                    ->references('id')
                    ->on('venta_emision')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                $table->index('venta_emision_id', 'idx_articulo_movimiento_venta_emision');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('articulo_movimiento', 'venta_emision_id')) {
                $table->dropForeign('fk_articulo_movimiento_venta_emision');
                $table->dropIndex('idx_articulo_movimiento_venta_emision');
                $table->dropColumn('venta_emision_id');
            }
        });
    }
};
