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
        Schema::create('recepcion_proveedor_articulo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->foreign('recepcion_proveedor_id', 'fk_recepcion_proveedor_articulo_recepcion_proveedor')->references('id')->on('recepcion_proveedor')->onDelete('cascade');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_recepcion_proveedor_articulo_articulo')->references('id')->on('articulo')->onDelete('cascade');
            $table->decimal('cantidad', 22, 6);
            $table->unsignedBigInteger('unidadmedida_id');
            $table->foreign('unidadmedida_id', 'fk_recepcion_proveedor_articulo_unidadmedida')->references('id')->on('unidadmedida')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('coeficienteconversion', 22, 6);
            $table->decimal('precio', 22, 6);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_recepcion_proveedor_articulo_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 6);
            $table->decimal('descuento', 22, 6);
            $table->unsignedBigInteger('deposito_id');
            $table->foreign('deposito_id', 'fk_recepcion_proveedor_articulo_depmae')->references('id')->on('depmae')->onDelete('restrict')->onUpdate('restrict');
            $table->text('detalle')->nullable();
            $table->text('motivorechazo')->nullable();
            $table->string('estado', 50);
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id', 'fk_recepcion_proveedor_articulo_impuesto')->references('id')->on('impuesto')->onDelete('restrict')->onUpdate('restrict');
            $table->string('incluyeimpuesto', 1);
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_recepcion_proveedor_articulo_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->foreign('lote_id', 'fk_recepcion_proveedor_articulo_lote')->references('id')->on('lote')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepcion_proveedor_articulo');
    }
};
