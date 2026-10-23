<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrón de consulta: se carga para identificar percepciones (precarga / IA)
 * sin nominar a la empresa como agente de percepción ni de retención.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return;
        }
        if (Schema::hasColumn('empresa_jurisdiccion_iibb', 'consulta_padron')) {
            return;
        }

        Schema::table('empresa_jurisdiccion_iibb', function (Blueprint $table) {
            $table->boolean('consulta_padron')->default(false)->after('es_agente_retencion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return;
        }
        if (! Schema::hasColumn('empresa_jurisdiccion_iibb', 'consulta_padron')) {
            return;
        }

        Schema::table('empresa_jurisdiccion_iibb', function (Blueprint $table) {
            $table->dropColumn('consulta_padron');
        });
    }
};
