<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de contenido por suscripción del Flash Report AGG
 * (completa = plantilla oficial; finanzas = columnas acotadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flash_reporte_suscripcion')) {
            return;
        }
        if (Schema::hasColumn('flash_reporte_suscripcion', 'perfil_vista')) {
            return;
        }

        Schema::table('flash_reporte_suscripcion', function (Blueprint $table) {
            $table->string('perfil_vista', 20)->default('completa')->after('mes_fijo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('flash_reporte_suscripcion')) {
            return;
        }
        if (! Schema::hasColumn('flash_reporte_suscripcion', 'perfil_vista')) {
            return;
        }

        Schema::table('flash_reporte_suscripcion', function (Blueprint $table) {
            $table->dropColumn('perfil_vista');
        });
    }
};
