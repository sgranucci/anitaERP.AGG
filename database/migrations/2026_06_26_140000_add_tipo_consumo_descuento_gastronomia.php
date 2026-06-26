<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuento_gastronomia', function (Blueprint $table) {
            $table->string('tipo_consumo', 20)->default('invitacion')->after('valor');
        });

        DB::table('descuento_gastronomia')
            ->where('codigo', '10')
            ->update(['tipo_consumo' => 'staff']);

        DB::table('descuento_gastronomia')
            ->where('codigo', '!=', '10')
            ->update(['tipo_consumo' => 'invitacion']);
    }

    public function down(): void
    {
        Schema::table('descuento_gastronomia', function (Blueprint $table) {
            $table->dropColumn('tipo_consumo');
        });
    }
};
