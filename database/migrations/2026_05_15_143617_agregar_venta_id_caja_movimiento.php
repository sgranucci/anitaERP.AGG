<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->after('cobranza_id')->nullable();
            $table->foreign('venta_id', 'fk_caja_movimiento_venta')->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->dropForeign('fk_caja_movimiento_venta');
            $table->dropColumn('venta_id');
        });
    }
};
