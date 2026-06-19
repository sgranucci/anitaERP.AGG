<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacion_bancaria_ejecucion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('saldo_banco', 18, 2)->nullable();
            $table->decimal('saldo_contable', 18, 2)->nullable();
            $table->decimal('diferencia', 18, 2)->nullable();
            $table->json('resumen_json')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_conc_banc_ejec_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacaja_id', 'fk_conc_banc_ejec_cuentacaja')
                ->references('id')->on('cuentacaja')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->index(['cuentacaja_id', 'anio', 'mes'], 'idx_conc_banc_ejec_cuenta_periodo');
        });

        Schema::create('conciliacion_bancaria_par', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->string('hash_contable', 64);
            $table->string('hash_banco', 64);
            $table->json('contable_json');
            $table->json('banco_json')->nullable();
            $table->date('fecha_contable')->nullable();
            $table->date('fecha_banco')->nullable();
            $table->decimal('importe', 18, 2)->default(0);
            $table->unsignedBigInteger('conciliado_por_usuario_id')->nullable();
            $table->timestamp('conciliado_at')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_conc_banc_par_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacaja_id', 'fk_conc_banc_par_cuentacaja')
                ->references('id')->on('cuentacaja')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->unique(['cuentacaja_id', 'hash_contable'], 'uq_conc_banc_par_contable');
            $table->unique(['cuentacaja_id', 'hash_banco'], 'uq_conc_banc_par_banco');
            $table->index(['cuentacaja_id', 'fecha_contable'], 'idx_conc_banc_par_fecha_cont');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_bancaria_par');
        Schema::dropIfExists('conciliacion_bancaria_ejecucion');
    }
};
