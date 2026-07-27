<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contable_periodo_cierre_programado')
            && ! Schema::hasColumn('contable_periodo_cierre_programado', 'hora_ejecucion')) {
            Schema::table('contable_periodo_cierre_programado', function (Blueprint $table) {
                $table->string('hora_ejecucion', 5)
                    ->default('24:00')
                    ->after('fecha_ejecucion')
                    ->comment('HH:MM; 24:00 = fin del día');
            });

            DB::table('contable_periodo_cierre_programado')
                ->whereNull('hora_ejecucion')
                ->orWhere('hora_ejecucion', '')
                ->update(['hora_ejecucion' => '24:00']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contable_periodo_cierre_programado')
            && Schema::hasColumn('contable_periodo_cierre_programado', 'hora_ejecucion')) {
            Schema::table('contable_periodo_cierre_programado', function (Blueprint $table) {
                $table->dropColumn('hora_ejecucion');
            });
        }
    }
};
