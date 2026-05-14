<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identificador de fórmula en Anita (stkcmae.stkcm_formula / stkmae.stkm_formula) para reimportar sin duplicar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        if (Schema::hasColumn('formula_articulo', 'anita_stkcm_formula')) {
            return;
        }
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->unsignedInteger('anita_stkcm_formula')->nullable()->after('articulo_id');
            $table->unique('anita_stkcm_formula', 'uq_formula_articulo_anita_stkcm_formula');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        if (! Schema::hasColumn('formula_articulo', 'anita_stkcm_formula')) {
            return;
        }
        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->dropUnique('uq_formula_articulo_anita_stkcm_formula');
            $table->dropColumn('anita_stkcm_formula');
        });
    }
};
