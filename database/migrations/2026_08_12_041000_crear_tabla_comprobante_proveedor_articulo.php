<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de artículo del comprobante de proveedor (match SKU/precio vs COM/OC).
 * Persisten aunque los flags de control estén off; el match solo corre si se activan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobante_proveedor_articulo')) {
            return;
        }

        Schema::create('comprobante_proveedor_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->unsignedInteger('orden')->default(1);
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->string('sku', 80)->nullable();
            $table->string('codigo_proveedor', 80)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 18, 6)->default(0);
            $table->decimal('precio_unitario', 18, 6)->default(0);
            $table->timestamps();

            $table->foreign('comprobante_proveedor_id', 'cp_art_cp_fk')
                ->references('id')->on('comprobante_proveedor')->cascadeOnDelete();
            $table->foreign('articulo_id', 'cp_art_articulo_fk')
                ->references('id')->on('articulo')->nullOnDelete();
            $table->index(['comprobante_proveedor_id', 'orden'], 'cp_art_cp_orden_idx');
            $table->index(['comprobante_proveedor_id', 'sku'], 'cp_art_cp_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_proveedor_articulo');
    }
};
