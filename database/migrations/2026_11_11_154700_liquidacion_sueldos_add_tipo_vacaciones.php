<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anita LVAC: 1 = plus vacacional, 2 = adelanto de vacaciones.
 * Solo aplica a corridas tipo=vacaciones; en el resto queda null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            $table->unsignedTinyInteger('tipo_vacaciones')->nullable()->after('tipo');
        });

        // Backfill: vacaciones existentes → plus (1); si el detalle dice adelanto → 2.
        DB::table('liquidacion_sueldos')
            ->where('tipo', 'vacaciones')
            ->whereNull('tipo_vacaciones')
            ->update(['tipo_vacaciones' => 1]);

        DB::table('liquidacion_sueldos')
            ->where('tipo', 'vacaciones')
            ->where(function ($q) {
                $q->where('descripcion', 'like', '%ADELANT%')
                    ->orWhere('descripcion', 'like', '%Adelant%')
                    ->orWhere('descripcion', 'like', '%adelant%');
            })
            ->update(['tipo_vacaciones' => 2]);
    }

    public function down(): void
    {
        Schema::table('liquidacion_sueldos', function (Blueprint $table) {
            $table->dropColumn('tipo_vacaciones');
        });
    }
};
