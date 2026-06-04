<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turno_operativo_gastronomia', function (Blueprint $table) {
            $table->json('medios_contado_cierre_json')->nullable()->after('sobrante_faltante');
        });
    }

    public function down(): void
    {
        Schema::table('turno_operativo_gastronomia', function (Blueprint $table) {
            $table->dropColumn('medios_contado_cierre_json');
        });
    }
};
