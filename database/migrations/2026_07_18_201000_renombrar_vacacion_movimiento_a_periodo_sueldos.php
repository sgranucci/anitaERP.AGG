<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vacacion_movimiento_sueldos') && ! Schema::hasTable('vacacion_periodo_sueldos')) {
            Schema::rename('vacacion_movimiento_sueldos', 'vacacion_periodo_sueldos');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vacacion_periodo_sueldos') && ! Schema::hasTable('vacacion_movimiento_sueldos')) {
            Schema::rename('vacacion_periodo_sueldos', 'vacacion_movimiento_sueldos');
        }
    }
};
