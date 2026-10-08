<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sin grupos de conceptos: elegibilidad (catálogo) o esperar novedades.
 * El valor lo elige la ficha; no depende de otras empresas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('empleado_sueldos', 'sin_grupo_modo')) {
                $table->string('sin_grupo_modo', 20)->default('elegibilidad')->after('grupo_concepto_3_codigo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('empleado_sueldos', 'sin_grupo_modo')) {
                $table->dropColumn('sin_grupo_modo');
            }
        });
    }
};
