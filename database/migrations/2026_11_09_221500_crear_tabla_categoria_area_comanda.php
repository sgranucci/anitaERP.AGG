<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror de subcategoria_area_comanda para Crown (Anita stkagr.stka_area → categoría).
 * La tabla puede existir en todos los entornos; el ABM y el sync se gatean a Crown.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categoria_area_comanda')) {
            return;
        }

        Schema::create('categoria_area_comanda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('area_comanda_gastronomia_id');
            $table->timestamps();

            $table->foreign('categoria_id', 'fk_cat_area_comanda_categoria')
                ->references('id')->on('categoria')
                ->cascadeOnDelete();
            $table->foreign('area_comanda_gastronomia_id', 'fk_cat_area_comanda_area')
                ->references('id')->on('area_comanda_gastronomia')
                ->cascadeOnDelete();
            $table->unique(
                ['categoria_id', 'area_comanda_gastronomia_id'],
                'uq_cat_area_comanda_cat_area'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_area_comanda');
    }
};
