<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plus (1) / Adelanto (2) al cargar vacaciones en el ledger de ausencias.
 * Alinea con Anita LVAC / Liquidacion_Sueldos::TIPO_VACACIONES_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empleado_ausencia_sueldos')) {
            return;
        }
        if (Schema::hasColumn('empleado_ausencia_sueldos', 'modalidad_vacaciones')) {
            return;
        }

        Schema::table('empleado_ausencia_sueldos', function (Blueprint $table) {
            $table->unsignedTinyInteger('modalidad_vacaciones')->nullable()->after('tipo_dias')
                ->comment('1=plus 422, 2=adelanto 420; solo vacaciones');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empleado_ausencia_sueldos')) {
            return;
        }
        if (! Schema::hasColumn('empleado_ausencia_sueldos', 'modalidad_vacaciones')) {
            return;
        }

        Schema::table('empleado_ausencia_sueldos', function (Blueprint $table) {
            $table->dropColumn('modalidad_vacaciones');
        });
    }
};
