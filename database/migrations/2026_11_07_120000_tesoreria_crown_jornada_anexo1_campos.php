<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja final del Anexo I (para encadenar caja inicial del día siguiente)
 * y cantidad de clientes informativa (antes solo Anita ingreso).
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
            if (! Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_dia')) {
                $table->decimal('caja_final_dia', 16, 2)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_mes')) {
                $table->decimal('caja_final_mes', 16, 2)->nullable()->after('caja_final_dia');
            }
            if (! Schema::hasColumn('tesoreria_crown_jornada', 'clientes')) {
                $table->unsignedInteger('clientes')->default(0)->after('caja_final_mes');
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
            foreach (['clientes', 'caja_final_mes', 'caja_final_dia'] as $col) {
                if (Schema::hasColumn('tesoreria_crown_jornada', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
