<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;

/**
 * Tras PR1 color/talle quedó el unique viejo uk_artsalddep_articulo_deposito
 * junto al nuevo uk_artsalddep_art_dep_col_tal. El rebuild agrupa por
 * articulo+deposito+color+talle y choca con el índice de 2 columnas.
 *
 * En PostgreSQL dropUnique sobre un nombre inexistente aborta la transacción
 * (CI postgres-lab lo logueaba aunque el try/catch marcara DONE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('articulo_saldo_deposito')) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(
            'articulo_saldo_deposito',
            'uk_artsalddep_articulo_deposito'
        );
    }

    public function down(): void
    {
        // No recrear el unique de 2 columnas: vuelve a romper reconstruir().
    }
};
