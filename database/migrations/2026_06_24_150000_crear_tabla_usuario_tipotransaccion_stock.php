<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de transacción de stock que un usuario puede operar (opcional por usuario).
 * Sin filas = sin restricción (todas las transacciones activas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuario_tipotransaccion_stock')) {
            return;
        }

        Schema::create('usuario_tipotransaccion_stock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('tipotransaccion_stock_id');

            $table->foreign('usuario_id', 'fk_usuario_tts_usuario')
                ->references('id')->on('usuario')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('tipotransaccion_stock_id', 'fk_usuario_tts_tipotransaccion')
                ->references('id')->on('tipotransaccion_stock')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['usuario_id', 'tipotransaccion_stock_id'], 'uk_usuario_tipotransaccion_stock');
            $table->index('tipotransaccion_stock_id', 'ix_usuario_tts_tipo');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_tipotransaccion_stock');
    }
};
