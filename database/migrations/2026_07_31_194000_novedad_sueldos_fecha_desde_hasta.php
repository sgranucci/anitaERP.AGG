<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Vigencia tipo SAP 0014 / Workday Ongoing:
 * - fecha_desde + fecha_hasta null = se repite en cada corrida mientras esté vigente
 * - ambas fechas = rango cerrado
 * - ambas null + liquidacion_id/periodo = one-shot (comportamiento Anita/Tango)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novedad_sueldos', function (Blueprint $table) {
            $table->date('fecha_desde')->nullable()->after('fecha_vto');
            $table->date('fecha_hasta')->nullable()->after('fecha_desde');
            $table->index(['empleado_id', 'fecha_desde', 'fecha_hasta'], 'novedad_emp_vigencia_idx');
        });
    }

    public function down(): void
    {
        Schema::table('novedad_sueldos', function (Blueprint $table) {
            $table->dropIndex('novedad_emp_vigencia_idx');
            $table->dropColumn(['fecha_desde', 'fecha_hasta']);
        });
    }
};
