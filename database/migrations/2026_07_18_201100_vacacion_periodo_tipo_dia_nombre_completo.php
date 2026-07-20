<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vacacion_periodo_sueldos')) {
            return;
        }

        Schema::table('vacacion_periodo_sueldos', function (Blueprint $table) {
            $table->string('tipo_dia', 20)->nullable()->change();
        });

        $mapa = [
            'H' => 'habil',
            'C' => 'corrido',
            'N' => 'no_habil',
            'F' => 'feriado',
        ];
        foreach ($mapa as $codigo => $nombre) {
            DB::table('vacacion_periodo_sueldos')
                ->where('tipo_dia', $codigo)
                ->update(['tipo_dia' => $nombre]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vacacion_periodo_sueldos')) {
            return;
        }

        $mapa = [
            'habil' => 'H',
            'corrido' => 'C',
            'no_habil' => 'N',
            'feriado' => 'F',
        ];
        foreach ($mapa as $nombre => $codigo) {
            DB::table('vacacion_periodo_sueldos')
                ->where('tipo_dia', $nombre)
                ->update(['tipo_dia' => $codigo]);
        }

        Schema::table('vacacion_periodo_sueldos', function (Blueprint $table) {
            $table->char('tipo_dia', 1)->nullable()->change();
        });
    }
};
