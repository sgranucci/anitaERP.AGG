<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacion_bancaria_cheque_pendiente', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ejecucion_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->string('tip', 8)->default('CHP');
            $table->string('numero_cheque', 20);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_cheque')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->date('fecha_conciliacion')->nullable();
            $table->decimal('importe', 18, 2)->default(0);
            $table->string('estado', 4)->nullable();
            $table->string('estado_banco', 4)->nullable();
            $table->string('entregado_a', 120)->nullable();
            $table->string('proveedor_codigo', 20)->nullable();
            $table->string('nro_op', 20)->nullable();
            $table->string('para_dep', 4)->nullable();
            $table->boolean('incluye_caratula')->default(false);
            $table->json('origen_json')->nullable();
            $table->timestamps();

            $table->foreign('ejecucion_id', 'fk_conc_banc_chq_pend_ejec')
                ->references('id')->on('conciliacion_bancaria_ejecucion')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('empresa_id', 'fk_conc_banc_chq_pend_emp')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacaja_id', 'fk_conc_banc_chq_pend_cc')
                ->references('id')->on('cuentacaja')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->index(['cuentacaja_id', 'numero_cheque'], 'idx_conc_banc_chq_pend_nro');
            $table->index(['ejecucion_id', 'incluye_caratula'], 'idx_conc_banc_chq_pend_car');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_cheque_pendiente');
    }
};
