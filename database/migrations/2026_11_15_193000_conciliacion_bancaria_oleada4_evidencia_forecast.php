<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oleada 4: evidencia de cierre/aprobación (adjuntos en ejecución).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_bancaria_ejecucion_archivo')) {
            Schema::create('conciliacion_bancaria_ejecucion_archivo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ejecucion_id');
                $table->string('nombrearchivo', 255);
                $table->string('nombre_original', 255)->nullable();
                $table->string('etapa', 20)->default('cierre');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('ejecucion_id', 'fk_conc_banc_ej_arch_ej')
                    ->references('id')->on('conciliacion_bancaria_ejecucion')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->index(['ejecucion_id', 'etapa'], 'idx_conc_banc_ej_arch_etapa');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_ejecucion_archivo');
    }
};
