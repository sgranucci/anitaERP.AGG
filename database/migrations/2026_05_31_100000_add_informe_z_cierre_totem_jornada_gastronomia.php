<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cierre_totem_jornada_gastronomia')) {
            return;
        }

        Schema::table('cierre_totem_jornada_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('cierre_totem_jornada_gastronomia', 'informe_z_json')) {
                $table->json('informe_z_json')->nullable()->after('detalle_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cierre_totem_jornada_gastronomia')) {
            return;
        }

        Schema::table('cierre_totem_jornada_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cierre_totem_jornada_gastronomia', 'informe_z_json')) {
                $table->dropColumn('informe_z_json');
            }
        });
    }
};
