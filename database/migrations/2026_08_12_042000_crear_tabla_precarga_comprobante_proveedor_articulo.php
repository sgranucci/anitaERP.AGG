<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ítems de artículo detectados por OCR/IA en la precarga (puente a comprobante_proveedor_articulo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_proveedor_articulo')) {
            return;
        }

        Schema::create('precarga_comprobante_proveedor_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('precarga_comprobante_proveedor_id');
            $table->unsignedInteger('orden')->default(1);
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('codigo_proveedor', 80)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 18, 6)->default(0);
            $table->decimal('precio_unitario', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('precarga_comprobante_proveedor_id', 'pcp_art_precarga_fk')
                ->references('id')->on('precarga_comprobante_proveedor')->cascadeOnDelete();
            $table->foreign('articulo_id', 'pcp_art_articulo_fk')
                ->references('id')->on('articulo')->nullOnDelete();
            $table->index(['precarga_comprobante_proveedor_id', 'orden'], 'pcp_art_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precarga_comprobante_proveedor_articulo');
    }
};
