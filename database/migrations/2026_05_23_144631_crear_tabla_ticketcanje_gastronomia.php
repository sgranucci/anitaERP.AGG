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
        Schema::create('ticketcanje_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('numerocupon', 50);
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_ticketcanje_gastronomia_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntos');
            $table->float('cantidad', 8, 4);
            $table->datetime('fecha');
            $table->unsignedBigInteger('cliente_id');
            $table->string('apellido', 255);
            $table->string('nombre', 255);
            $table->string('numerodocumento', 20);
            $table->unsignedBigInteger('mozo_id');
            $table->foreign('mozo_id', 'fk_ticketcanje_gastronomia_mozo_gastronomia')->references('id')->on('mozo_gastronomia')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fechacanje');
            $table->unsignedBigInteger('usuariocanje_id');
            $table->foreign('usuariocanje_id', 'fk_ticketcanje_gastronomia_usuariocanje')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('renglon');
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_ticketcanje_gastronomia_venta')->references('id')->on('venta')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('costo', 22, 2);
            $table->decimal('precioventa', 22, 2);
            $table->timestamps();
            $table->unique(['numerocupon', 'renglon'], 'uq_ticketcanje_gastronomia_cupon_renglon');
            $table->index('venta_id', 'idx_ticketcanje_gastronomia_venta');
            $table->index('numerocupon', 'idx_ticketcanje_gastronomia_numerocupon');
            $table->index('usuariocanje_id', 'idx_ticketcanje_gastronomia_usuariocanje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticketcanje_gastronomia');
    }
};
