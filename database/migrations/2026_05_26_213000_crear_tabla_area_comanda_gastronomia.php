<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_comanda_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->string('codigo', 255);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_area_comanda_gastronomia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unique(['empresa_id', 'codigo'], 'uq_area_comanda_gastronomia_empresa_codigo');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_comanda_gastronomia');
    }
};
