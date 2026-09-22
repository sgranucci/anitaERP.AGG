<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tras PR1 color/talle quedó el unique viejo uk_artsalddep_articulo_deposito
 * junto al nuevo uk_artsalddep_art_dep_col_tal. El rebuild agrupa por
 * articulo+deposito+color+talle y choca con el índice de 2 columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('articulo_saldo_deposito', function (Blueprint $table) {
                $table->dropUnique('uk_artsalddep_articulo_deposito');
            });
        } catch (\Throwable $e) {
            // Ya no existe o nombre distinto.
        }
    }

    public function down(): void
    {
        // No recrear el unique de 2 columnas: vuelve a romper reconstruir().
    }
};
