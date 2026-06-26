<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos propios INTERFORMING (stkmae: stkm_subrubro, stkm_lineamaterial, stkm_grupoproducto).
     */
    public function up(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo', 'subrubro')) {
                $table->string('subrubro', 50)->nullable()->after('tipoarticulo_id');
            }
            if (! Schema::hasColumn('articulo', 'lineamaterial')) {
                $table->string('lineamaterial', 50)->nullable()->after('subrubro');
            }
            if (! Schema::hasColumn('articulo', 'grupoproducto')) {
                $table->string('grupoproducto', 50)->nullable()->after('lineamaterial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            foreach (['grupoproducto', 'lineamaterial', 'subrubro'] as $columna) {
                if (Schema::hasColumn('articulo', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
