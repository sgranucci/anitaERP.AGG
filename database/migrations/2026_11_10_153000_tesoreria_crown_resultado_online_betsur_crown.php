<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloque Betsur online (sucursal Anita 68) junto a Casinos del Río online (67)
 * en tesoreria_crown_resultado_online. Las columnas existentes (apostado, pagado,
 * resultado, porcentaje, …) quedan para Del Río; slots no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_resultado_online')) {
            return;
        }
        if (Schema::hasColumn('tesoreria_crown_resultado_online', 'betsur_apostado')) {
            return;
        }

        Schema::table('tesoreria_crown_resultado_online', function (Blueprint $table) {
            $table->unsignedInteger('betsur_attendance')->default(0)->after('poder_publico');
            $table->decimal('betsur_apostado', 18, 2)->default(0)->after('betsur_attendance');
            $table->decimal('betsur_pagado', 18, 2)->default(0)->after('betsur_apostado');
            $table->decimal('betsur_drop', 18, 2)->default(0)->after('betsur_pagado');
            $table->decimal('betsur_resultado', 18, 2)->default(0)->after('betsur_drop');
            $table->decimal('betsur_porcentaje', 10, 4)->default(0)->after('betsur_resultado');
            $table->decimal('betsur_poder_publico', 18, 2)->default(0)->after('betsur_porcentaje');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_resultado_online')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_resultado_online', 'betsur_apostado')) {
            return;
        }

        Schema::table('tesoreria_crown_resultado_online', function (Blueprint $table) {
            $table->dropColumn([
                'betsur_attendance',
                'betsur_apostado',
                'betsur_pagado',
                'betsur_drop',
                'betsur_resultado',
                'betsur_porcentaje',
                'betsur_poder_publico',
            ]);
        });
    }
};
