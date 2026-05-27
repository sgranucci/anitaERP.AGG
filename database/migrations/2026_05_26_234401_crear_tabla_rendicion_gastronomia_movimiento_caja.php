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
        Schema::create('rendicion_gastronomia_movimiento_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicion_gastronomia_caja_id');
            $table->foreign('rendicion_gastronomia_caja_id', 'fk_rg_mov_rendicion')->references('id')->on('rendicion_gastronomia_caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_rg_mov_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('monto', 22, 2);
            $table->decimal('cotizacion', 22, 2);
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
        Schema::dropIfExists('rendicion_gastronomia_movimiento_caja');
    }
};
