<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Clasificacion normativa del motivo de egreso (renuncia / despido_sc / etc.)
 * para que la liquidacion final decida los conceptos por causa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motivoegreso_sueldos', function (Blueprint $table) {
            $table->string('clase', 20)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('motivoegreso_sueldos', function (Blueprint $table) {
            $table->dropColumn('clase');
        });
    }
};
