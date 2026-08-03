<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubro Anexo III + unidad de medida (presentación recibo Dto. 407).
 * La reclasificación papo (contribucion vs informativo) se ejecuta vía
 * ConceptoPapoReclasificarService tras migrate (lee Anita hab_va_recibo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepto_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('concepto_sueldos', 'rubro_costo_laboral')) {
                $table->string('rubro_costo_laboral', 32)->nullable()->after('concepto_afip');
            }
            if (! Schema::hasColumn('concepto_sueldos', 'unidad_medida')) {
                $table->string('unidad_medida', 4)->nullable()->after('rubro_costo_laboral');
            }
        });
    }

    public function down(): void
    {
        Schema::table('concepto_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('concepto_sueldos', 'unidad_medida')) {
                $table->dropColumn('unidad_medida');
            }
            if (Schema::hasColumn('concepto_sueldos', 'rubro_costo_laboral')) {
                $table->dropColumn('rubro_costo_laboral');
            }
        });
    }
};
