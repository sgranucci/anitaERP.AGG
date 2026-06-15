<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregados mensuales por (empresa, cuenta, centro de costo, moneda) derivados de
 * asiento_movimiento. Mantenidos on-line por Asiento_MovimientoObserver (contable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentacontable_saldo_mes')) {
            return;
        }

        Schema::create('cuentacontable_saldo_mes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->unsignedInteger('anio_mes');
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('monto', 24, 4)->default(0);
            $table->decimal('monto_local', 24, 4)->default(0);
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_ctasaldmes_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_ctasaldmes_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('centrocosto_id', 'fk_ctasaldmes_cc')
                ->references('id')->on('centrocosto')
                ->onDelete('set null')->onUpdate('set null');
            $table->foreign('moneda_id', 'fk_ctasaldmes_moneda')
                ->references('id')->on('moneda')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(
                ['empresa_id', 'cuentacontable_id', 'centrocosto_id', 'anio_mes', 'moneda_id'],
                'uk_ctasaldmes_grano',
            );
            $table->index(['empresa_id', 'anio_mes'], 'ix_ctasaldmes_empresa_mes');
            $table->index(['cuentacontable_id', 'anio_mes'], 'ix_ctasaldmes_cuenta_mes');

            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentacontable_saldo_mes');
    }
};
