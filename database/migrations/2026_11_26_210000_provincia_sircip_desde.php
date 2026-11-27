<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de SIRCIP por provincia: fecha desde la cual la percepción de esa
 * jurisdicción se calcula con el padrón único SIRCIP en lugar de la tasa de
 * descarte / padrón propio.
 *
 * Nula = la provincia no usa SIRCIP. Sin datos: la fecha se carga desde
 * Configuración general (Agentes IIBB).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provincia') || Schema::hasColumn('provincia', 'sircip_desde')) {
            return;
        }

        Schema::table('provincia', function (Blueprint $table) {
            $table->date('sircip_desde')->nullable()->after('tiene_padron');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('provincia', 'sircip_desde')) {
            return;
        }

        Schema::table('provincia', function (Blueprint $table) {
            $table->dropColumn('sircip_desde');
        });
    }
};
