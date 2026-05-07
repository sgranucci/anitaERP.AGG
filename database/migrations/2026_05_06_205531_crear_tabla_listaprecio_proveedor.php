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
        Schema::create('listaprecio_proveedor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_listaprecio_proveedor_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->date('fecha');
            $table->string('nombre', 255);
            $table->text('observaciones');
            $table->unsignedBigInteger('condicionpago_id')->nullable();
            $table->foreign('condicionpago_id', 'fk_listaprecio_proveedor_condicionpago')->references('id')->on('condicionpago')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('condicionentrega_id')->nullable();
            $table->foreign('condicionentrega_id', 'fk_listaprecio_proveedor_condicionentrega')->references('id')->on('condicionentrega')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('condicioncompra_id')->nullable();
            $table->foreign('condicioncompra_id', 'fk_listaprecio_proveedor_condicioncompra')->references('id')->on('condicioncompra')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->foreign('moneda_id', 'fk_listaprecio_proveedor_moneda')->references('id')->on('moneda')->onDelete('set null')->onUpdate('set null');
            $table->string('estado', 50)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_listaprecio_proveedor_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('listaprecio_proveedor');
    }
};
