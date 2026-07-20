<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla `talle` de anitaERP solo tenía id + nombre. Anita usa tall_talle (entero)
 * como clave, imprescindible para mapear prendas (prendart) e importar el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talle', function (Blueprint $table) {
            if (! Schema::hasColumn('talle', 'codigo')) {
                $table->integer('codigo')->nullable()->after('nombre');
                $table->unique('codigo', 'talle_codigo_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talle', function (Blueprint $table) {
            if (Schema::hasColumn('talle', 'codigo')) {
                $table->dropUnique('talle_codigo_unique');
                $table->dropColumn('codigo');
            }
        });
    }
};
