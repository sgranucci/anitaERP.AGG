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
        Schema::table('venta', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id')->after('ordenventa_id')->nullable();
            $table->foreign('pedido_id', 'fk_venta_pedido')->references('id')->on('pedido')->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropForeign('fk_venta_pedido');
            $table->dropColumn('pedido_id');
        });
    }
};
