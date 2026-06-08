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

        Schema::table('gastronomia_cierre_jornada_config', function (Blueprint $table) {
            if (! Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_diferencia_caja_id')) {
                $table->unsignedBigInteger('cuenta_diferencia_caja_id')
                    ->nullable()
                    ->after('cuenta_fondo_fijo_maquinas_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        Schema::table('gastronomia_cierre_jornada_config', function (Blueprint $table) {
            if (Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_diferencia_caja_id')) {
                $table->dropColumn('cuenta_diferencia_caja_id');
            }
        });
    }
};
