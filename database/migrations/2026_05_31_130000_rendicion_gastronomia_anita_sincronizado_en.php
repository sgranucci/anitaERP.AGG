<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->dateTime('anita_sincronizado_en')->nullable()->after('fuente_nro_oper');
        });
    }

    public function down(): void
    {
        Schema::table('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->dropColumn('anita_sincronizado_en');
        });
    }
};
