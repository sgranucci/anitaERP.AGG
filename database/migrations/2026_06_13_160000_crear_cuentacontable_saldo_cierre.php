<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de saldos acumulados por cuenta al ejecutar cierre de período contable.
 * Complementa cuentacontable_saldo_mes (tabla viva) para consultas rápidas en períodos cerrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentacontable_saldo_cierre')) {
            return;
        }

        Schema::create('cuentacontable_saldo_cierre', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('periodo_cierre_id');
            $table->unsignedBigInteger('empresa_id');
            $table->date('fecha_hasta');
            $table->unsignedInteger('anio_mes');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('monto_acumulado', 24, 4)->default(0);
            $table->decimal('monto_local_acumulado', 24, 4)->default(0);
            $table->timestamps();

            $table->foreign('periodo_cierre_id', 'fk_ctasaldcierre_periodo')
                ->references('id')->on('contable_periodo_cierre')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('empresa_id', 'fk_ctasaldcierre_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_ctasaldcierre_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('centrocosto_id', 'fk_ctasaldcierre_cc')
                ->references('id')->on('centrocosto')
                ->onDelete('set null')->onUpdate('set null');
            $table->foreign('moneda_id', 'fk_ctasaldcierre_moneda')
                ->references('id')->on('moneda')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(
                ['periodo_cierre_id', 'cuentacontable_id', 'centrocosto_id', 'moneda_id'],
                'uk_ctasaldcierre_grano',
            );
            $table->index(['empresa_id', 'fecha_hasta'], 'ix_ctasaldcierre_empresa_fecha');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentacontable_saldo_cierre');
    }
};
