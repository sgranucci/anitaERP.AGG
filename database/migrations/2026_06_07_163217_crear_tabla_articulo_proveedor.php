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
        Schema::create('articulo_proveedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_articulo_proveedor_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_articulo_proveedor_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->string('nombre_articulo_proveedor', 255)->nullable();
            $table->string('codigobarra', 50)->nullable();
            $table->string('codigo_articulo_proveedor', 100)->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->foreign('moneda_id', 'fk_articulo_proveedor_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('precio', 20, 6)->default(0);
            $table->unsignedBigInteger('unidadmedida_compra_id')->nullable();
            $table->foreign('unidadmedida_compra_id', 'fk_articulo_proveedor_unidadmedida')->references('id')->on('unidadmedida')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('coeficiente_conversion', 20, 6)->default(1);
            $table->unsignedBigInteger('listaprecio_proveedor_articulo_id')->nullable();
            $table->foreign('listaprecio_proveedor_articulo_id', 'fk_articulo_proveedor_lpa')->references('id')->on('listaprecio_proveedor_articulo')->onDelete('set null')->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['articulo_id', 'proveedor_id'], 'uk_articulo_proveedor_art_prov');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articulo_proveedor');
    }
};
