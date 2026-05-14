<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orden de presentación/facturación para ítems opcionales (gastronomía: AGG, CROWN).
     */
    public function up(): void
    {
        if (! Schema::hasTable('formula_articulo_hijo')) {
            return;
        }
        if (Schema::hasColumn('formula_articulo_hijo', 'ordenopcional')) {
            return;
        }
        Schema::table('formula_articulo_hijo', function (Blueprint $table) {
            $table->unsignedSmallInteger('ordenopcional')->nullable()->after('esopcional');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('formula_articulo_hijo')) {
            return;
        }
        if (Schema::hasColumn('formula_articulo_hijo', 'ordenopcional')) {
            Schema::table('formula_articulo_hijo', function (Blueprint $table) {
                $table->dropColumn('ordenopcional');
            });
        }
    }
};
