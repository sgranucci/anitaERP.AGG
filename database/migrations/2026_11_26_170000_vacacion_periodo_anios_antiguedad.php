<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tramos de las tablas de vacaciones (Anita vacmov: vacv_desde / vacv_hasta) son
 * rangos de ANTIGÜEDAD en años (0-5, 5-10, 10-20, 20-99), no fechas. Se guardan aparte
 * para que el devengamiento use los días de la tabla del empleado.
 *
 * Tabla compartida por todos los clientes; columnas nullable: sin efecto en lo existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vacacion_periodo_sueldos')) {
            return;
        }

        Schema::table('vacacion_periodo_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('vacacion_periodo_sueldos', 'anios_desde')) {
                $table->unsignedSmallInteger('anios_desde')->nullable()->after('nro_linea')
                    ->comment('Antigüedad mínima en años (inclusive)');
            }
            if (! Schema::hasColumn('vacacion_periodo_sueldos', 'anios_hasta')) {
                $table->unsignedSmallInteger('anios_hasta')->nullable()->after('anios_desde')
                    ->comment('Antigüedad máxima en años (exclusive)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vacacion_periodo_sueldos')) {
            return;
        }

        Schema::table('vacacion_periodo_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('vacacion_periodo_sueldos', 'anios_hasta')) {
                $table->dropColumn('anios_hasta');
            }
            if (Schema::hasColumn('vacacion_periodo_sueldos', 'anios_desde')) {
                $table->dropColumn('anios_desde');
            }
        });
    }
};
