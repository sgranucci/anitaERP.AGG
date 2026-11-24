<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía tipo_movimiento para registrar baja provisoria (BP) además de I/B.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empleado_ingreso_sueldos')) {
            return;
        }

        Schema::table('empleado_ingreso_sueldos', function (Blueprint $table) {
            $table->string('tipo_movimiento', 2)->default('I')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empleado_ingreso_sueldos')) {
            return;
        }

        Schema::table('empleado_ingreso_sueldos', function (Blueprint $table) {
            $table->char('tipo_movimiento', 1)->default('I')->change();
        });
    }
};
