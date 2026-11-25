<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidacion_error_sueldos', function (Blueprint $table) {
            $table->unsignedBigInteger('concepto_id')->nullable()->after('apellido_nombre');
            $table->integer('formula_pos')->nullable()->after('concepto_descripcion');
            $table->string('formula_campo', 30)->nullable()->after('formula_pos');
            $table->index(['liquidacion_id', 'concepto_codigo'], 'liqerr_liq_conc_idx');
        });
    }

    public function down(): void
    {
        Schema::table('liquidacion_error_sueldos', function (Blueprint $table) {
            $table->dropIndex('liqerr_liq_conc_idx');
            $table->dropColumn(['concepto_id', 'formula_pos', 'formula_campo']);
        });
    }
};
