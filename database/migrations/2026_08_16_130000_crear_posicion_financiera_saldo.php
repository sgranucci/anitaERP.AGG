<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posicion_financiera_saldo')) {
            return;
        }

        Schema::create('posicion_financiera_saldo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha_cierre');
            $table->decimal('saldo_inicial', 20, 2)->nullable();
            $table->decimal('saldo_final', 20, 2);
            $table->string('origen', 30)->default('calculado_erp');
            $table->json('filtros_json')->nullable();
            $table->unsignedBigInteger('confirmado_por')->nullable();
            $table->timestamp('confirmado_at');
            $table->unsignedBigInteger('anulado_por')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha_cierre'], 'posfin_saldo_empresa_fecha_idx');
            $table->foreign('empresa_id')->references('id')->on('empresa');
            $table->foreign('confirmado_por')->references('id')->on('usuario');
            $table->foreign('anulado_por')->references('id')->on('usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posicion_financiera_saldo');
    }
};
