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
            if (! Schema::hasColumn('gastronomia_cierre_jornada_config', 'puntoventa_id')) {
                $table->unsignedBigInteger('puntoventa_id')->nullable()->after('empresa_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')) {
            return;
        }

        Schema::table('gastronomia_cierre_jornada_config', function (Blueprint $table) {
            if (Schema::hasColumn('gastronomia_cierre_jornada_config', 'puntoventa_id')) {
                $table->dropColumn('puntoventa_id');
            }
        });
    }
};
