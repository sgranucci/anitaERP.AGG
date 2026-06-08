<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->boolean('waitry_habilitado')->default(true)->after('salida_factura_id');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_puntoventa_gastronomia', function (Blueprint $table) {
            $table->dropColumn('waitry_habilitado');
        });
    }
};
