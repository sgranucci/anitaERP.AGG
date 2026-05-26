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
        Schema::create('categoriafidelidad_entrega_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('categoriafidelidad_id');
            $table->foreign('categoriafidelidad_id', 'fk_categoriafidelidad_entrega_gastronomia_categoriafidelidad')->references('id')->on('categoriafidelidad_gastronomia')->onDelete('restrict')->onUpdate('restrict');
            $table->string('documento', 20);
            $table->string('tarjeta', 30);
            $table->datetime('fechacanje');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_categoriafidelidad_entrega_gastronomia_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_categoriafidelidad_entrega_gastronomia_venta')->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
            $table->string('apellido', 40);
            $table->string('nombre', 40);
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
        Schema::dropIfExists('categoriafidelidad_entrega_gastronomia');
    }
};
