<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_precio_estacionamiento_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lista_precio_estacionamiento_id');
            $table->foreign('lista_precio_estacionamiento_id', 'fk_lp_est_item_lista')
                ->references('id')->on('lista_precio_estacionamiento')->onDelete('cascade')->onUpdate('restrict');
            $table->unsignedBigInteger('item_estacionamiento_id');
            $table->foreign('item_estacionamiento_id', 'fk_lp_est_item_item')
                ->references('id')->on('item_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('precio', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(
                ['lista_precio_estacionamiento_id', 'item_estacionamiento_id'],
                'uq_lp_est_item_lista_item'
            );
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_precio_estacionamiento_item');
    }
};
