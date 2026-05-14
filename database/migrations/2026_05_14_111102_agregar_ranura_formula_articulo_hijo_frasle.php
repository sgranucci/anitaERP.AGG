<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ranura solo para instalación FRASLE (evita columna en otros clientes).
     */
    public function up(): void
    {
        if (config('app.empresa') !== 'FRASLE') {
            return;
        }

        if (Schema::hasTable('formula_articulo_hijo') && ! Schema::hasColumn('formula_articulo_hijo', 'ranura')) {
            Schema::table('formula_articulo_hijo', function (Blueprint $table) {
                $table->unsignedBigInteger('ranura')->nullable()->after('deposito_id');
            });
        }
    }

    public function down(): void
    {
        if (config('app.empresa') !== 'FRASLE') {
            return;
        }

        if (Schema::hasTable('formula_articulo_hijo') && Schema::hasColumn('formula_articulo_hijo', 'ranura')) {
            Schema::table('formula_articulo_hijo', function (Blueprint $table) {
                $table->dropColumn('ranura');
            });
        }
    }
};
