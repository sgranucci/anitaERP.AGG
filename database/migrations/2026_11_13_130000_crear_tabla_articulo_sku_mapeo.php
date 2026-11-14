<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de recodificación de SKU (ej. no facturables con "M").
 * Sirve para imports/migraciones futuras que traigan el SKU viejo como dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_sku_mapeo')) {
            return;
        }

        Schema::create('articulo_sku_mapeo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->string('sku_anterior', 50);
            $table->string('sku_nuevo', 50);
            $table->string('motivo', 100)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->unique(['sku_anterior'], 'articulo_sku_mapeo_sku_anterior_uq');
            $table->unique(['articulo_id', 'sku_nuevo'], 'articulo_sku_mapeo_articulo_nuevo_uq');
            $table->index(['sku_nuevo'], 'articulo_sku_mapeo_sku_nuevo_idx');
            $table->foreign('articulo_id', 'fk_articulo_sku_mapeo_articulo')
                ->references('id')->on('articulo')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_sku_mapeo');
    }
};
