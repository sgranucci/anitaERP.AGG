<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategoria_area_comanda', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('subcategoria_id');
            $table->unsignedBigInteger('area_comanda_gastronomia_id');
            $table->timestamps();

            $table->foreign('subcategoria_id', 'fk_subc_area_comanda_subcategoria')
                ->references('id')->on('subcategoria')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('area_comanda_gastronomia_id', 'fk_subc_area_comanda_area')
                ->references('id')->on('area_comanda_gastronomia')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->unique(
                ['subcategoria_id', 'area_comanda_gastronomia_id'],
                'uq_subc_area_comanda_subc_area'
            );

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcategoria_area_comanda');
    }
};
