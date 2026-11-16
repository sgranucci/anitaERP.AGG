<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de workspace de conciliación por empresa (Contaduría vs Tesorería).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_bancaria_empresa_config')) {
            return;
        }

        Schema::create('conciliacion_bancaria_empresa_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('modo', 20);
            $table->timestamps();

            $table->unique('empresa_id', 'uq_conc_banc_emp_cfg_empresa');
            $table->foreign('empresa_id', 'fk_conc_banc_emp_cfg_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_empresa_config');
    }
};
