<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa la caja final del Anexo VIII del cache de “caja final por variación” del Anexo I.
 *
 * - caja_final_dia / caja_final_mes = inicial + variación (Anexo I)
 * - caja_final_viii = total l-estboveda (ítem 130 / caja inicial del día siguiente)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_jornada')) {
            return;
        }

        Schema::table('tesoreria_crown_jornada', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_viii')) {
                $table->decimal('caja_final_viii', 16, 2)->nullable()->after('caja_final_mes');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_jornada')) {
            return;
        }

        Schema::table('tesoreria_crown_jornada', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_viii')) {
                $table->dropColumn('caja_final_viii');
            }
        });
    }
};
