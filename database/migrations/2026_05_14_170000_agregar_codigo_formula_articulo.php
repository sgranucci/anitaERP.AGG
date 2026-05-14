<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código/número de fórmula en Anita (stkcmae.stkcm_formula), texto para listados y CRUD.
     */
    public function up(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        if (Schema::hasColumn('formula_articulo', 'codigo')) {
            return;
        }

        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->after('articulo_id');
        });

        if (Schema::hasColumn('formula_articulo', 'anita_stkcm_formula')) {
            DB::table('formula_articulo')
                ->whereNull('codigo')
                ->whereNotNull('anita_stkcm_formula')
                ->update(['codigo' => DB::raw('CAST(anita_stkcm_formula AS CHAR)')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('formula_articulo')) {
            return;
        }
        if (! Schema::hasColumn('formula_articulo', 'codigo')) {
            return;
        }

        Schema::table('formula_articulo', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
