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
        // Crear tabla listaprecio_proveedor_articulo
        Schema::create('listaprecio_proveedor_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listaprecio_proveedor_id');
            $table->foreign('listaprecio_proveedor_id', 'fk_listaprecio_proveedor_articulo_listaprecio_proveedor')->references('id')->on('listaprecio_proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_listaprecio_proveedor_articulo_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('precio', 20, 6);
            $table->string('articulo_proveedor', 100);
            $table->decimal('descuento', 5, 2);
            $table->date('fechavigencia');
            $table->unsignedBigInteger('usuarioultcambio_id');
            $table->foreign('usuarioultcambio_id', 'fk_listaprecio_proveedor_articulo_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('listaprecio_proveedor_articulo');
    }
};
