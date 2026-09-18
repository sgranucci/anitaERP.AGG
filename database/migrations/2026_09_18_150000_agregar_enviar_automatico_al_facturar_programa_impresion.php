<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            $table->boolean('enviar_automatico_al_facturar')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_impresion_programa', function (Blueprint $table) {
            $table->dropColumn('enviar_automatico_al_facturar');
        });
    }
};
