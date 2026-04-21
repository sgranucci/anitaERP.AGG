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
        Schema::table('articulo_movimiento', function (Blueprint $table) {
            if (!Schema::hasColumn('articulo_movimiento', 'pedido_articulo_id')) {
                $table->unsignedBigInteger('pedido_articulo_id')->nullable()->after('loteimportacion_id');
                $table->foreign('pedido_articulo_id', 'fk_articulo_movimiento_pedido_articulo')
                    ->references('id')
                    ->on('pedido_articulo')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('articulo_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('articulo_movimiento', 'pedido_articulo_Id')) {
                $table->dropForeign('fk_articulo_movimiento_pedido_articulo');
                $table->dropColumn('pedido_articulo_id');
            }
        });
    }
};
