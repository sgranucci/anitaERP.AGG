<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_gastronomia_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id')->primary();
            $table->foreign('venta_id', 'fk_vge_venta')
                ->references('id')->on('venta')
                ->onDelete('cascade')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('cuenta_gastronomia_id')->nullable();
            $table->foreign('cuenta_gastronomia_id', 'fk_vge_cuenta')
                ->references('id')->on('cuenta_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('identificador_pc', 100)->index();
            $table->unsignedBigInteger('configuracion_puntoventa_gastronomia_id')->nullable();
            $table->foreign('configuracion_puntoventa_gastronomia_id', 'fk_vge_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_gastronomia_emision');
    }
};
