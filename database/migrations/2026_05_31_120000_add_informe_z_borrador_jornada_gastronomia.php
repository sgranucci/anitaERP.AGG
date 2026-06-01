<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jornada_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('jornada_gastronomia', 'informe_z_borrador_json')) {
                $table->json('informe_z_borrador_json')->nullable()->after('observacion_cierre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jornada_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('jornada_gastronomia', 'informe_z_borrador_json')) {
                $table->dropColumn('informe_z_borrador_json');
            }
        });
    }
};
