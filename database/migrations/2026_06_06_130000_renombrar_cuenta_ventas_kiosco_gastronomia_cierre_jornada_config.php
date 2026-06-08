<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        if (Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_impuesto_interno_id')
            && ! Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_ventas_kiosco_id')) {
            Schema::table('gastronomia_cierre_jornada_config', function (Blueprint $table) {
                $table->renameColumn('cuenta_impuesto_interno_id', 'cuenta_ventas_kiosco_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        if (Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_ventas_kiosco_id')
            && ! Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_impuesto_interno_id')) {
            Schema::table('gastronomia_cierre_jornada_config', function (Blueprint $table) {
                $table->renameColumn('cuenta_ventas_kiosco_id', 'cuenta_impuesto_interno_id');
            });
        }
    }
};
