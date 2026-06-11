<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendicion_estacionamiento_movimiento_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicion_estacionamiento_caja_id');
            $table->foreign('rendicion_estacionamiento_caja_id', 'fk_rendicion_estacionamiento_mov_caja')->references('id')->on('rendicion_estacionamiento_caja')->onDelete('cascade')->onUpdate('restrict');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_rendicion_estacionamiento_mov_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('monto', 22, 2);
            $table->decimal('cotizacion', 22, 6)->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_estacionamiento_movimiento_caja');
    }
};
