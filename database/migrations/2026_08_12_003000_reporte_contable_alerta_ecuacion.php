<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validaciones contables cruzadas: alerta de tipo "ecuacion" con expresión entre
 * códigos de línea (ej. R001-(R050+R080)) que debe dar cero dentro del umbral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_contable_alerta', function (Blueprint $table) {
            if (! Schema::hasColumn('reporte_contable_alerta', 'etiqueta')) {
                $table->string('etiqueta', 120)->nullable()->after('tipo');
            }
            if (! Schema::hasColumn('reporte_contable_alerta', 'expresion')) {
                $table->string('expresion', 255)->nullable()->after('etiqueta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reporte_contable_alerta', function (Blueprint $table) {
            if (Schema::hasColumn('reporte_contable_alerta', 'expresion')) {
                $table->dropColumn('expresion');
            }
            if (Schema::hasColumn('reporte_contable_alerta', 'etiqueta')) {
                $table->dropColumn('etiqueta');
            }
        });
    }
};
