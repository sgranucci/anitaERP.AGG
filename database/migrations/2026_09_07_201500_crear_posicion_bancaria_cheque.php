<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock de cheques propios para posición bancaria diaria.
 * Misma semántica CHP que conciliacion_bancaria_cheque_pendiente,
 * pero como maestro vivo (no atado a una ejecución de conciliación).
 * Fuente inicial: planilla Posición (BSA/KSA/RSA); no requiere Anita online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posicion_bancaria_cheque', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacaja_id')->nullable();
            $table->string('banco_canonico', 16);
            $table->string('tip', 8)->default('CHP');
            $table->string('numero_cheque', 20);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_cheque')->nullable()->comment('Vencimiento / F.Dev');
            $table->date('fecha_entrega')->nullable();
            $table->decimal('importe', 18, 2)->default(0);
            $table->string('estado', 4)->nullable()->comment('blank=diferido *=debitado A=anulado');
            $table->string('estado_banco', 4)->nullable();
            $table->string('entregado_a', 120)->nullable();
            $table->string('proveedor_codigo', 20)->nullable();
            $table->string('nro_op', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('origen', 32)->default('excel_posicion');
            $table->json('origen_json')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_pos_banc_chq_emp')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacaja_id', 'fk_pos_banc_chq_cc')
                ->references('id')->on('cuentacaja')
                ->onDelete('set null')->onUpdate('restrict');

            $table->unique(
                ['empresa_id', 'banco_canonico', 'numero_cheque'],
                'uk_pos_banc_chq_emp_banco_nro'
            );
            $table->index(['empresa_id', 'fecha_cheque'], 'idx_pos_banc_chq_venc');
            $table->index(['cuentacaja_id', 'numero_cheque'], 'idx_pos_banc_chq_cc_nro');
            $table->index(['activo', 'estado'], 'idx_pos_banc_chq_activo_est');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posicion_bancaria_cheque');
    }
};
