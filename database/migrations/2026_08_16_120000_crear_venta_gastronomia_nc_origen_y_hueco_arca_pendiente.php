<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_gastronomia_nc_origen')) {
            Schema::create('venta_gastronomia_nc_origen', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('venta_nc_id');
                $table->unsignedBigInteger('venta_factura_id');
                $table->timestamps();

                $table->unique(['venta_nc_id', 'venta_factura_id'], 'uq_vge_nc_origen_par');
                $table->index('venta_factura_id', 'idx_vge_nc_origen_factura');
                $table->foreign('venta_nc_id', 'fk_vge_nc_origen_nc')
                    ->references('id')->on('venta')
                    ->cascadeOnDelete();
                $table->foreign('venta_factura_id', 'fk_vge_nc_origen_fac')
                    ->references('id')->on('venta')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('gastronomia_hueco_arca_pendiente')) {
            Schema::create('gastronomia_hueco_arca_pendiente', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha_jornada');
                $table->unsignedBigInteger('puntoventa_id');
                $table->unsignedInteger('numero_comprobante');
                $table->unsignedBigInteger('turno_operativo_gastronomia_id')->nullable();
                $table->string('identificador_pc', 120)->nullable();
                $table->string('estado', 32)->default('pendiente');
                $table->string('ultimo_error', 500)->nullable();
                $table->unsignedBigInteger('venta_factura_id')->nullable();
                $table->unsignedBigInteger('venta_nc_id')->nullable();
                $table->timestamp('diagnosticado_en')->nullable();
                $table->timestamp('resuelto_en')->nullable();
                $table->timestamps();

                $table->unique(
                    ['empresa_id', 'puntoventa_id', 'numero_comprobante', 'fecha_jornada'],
                    'uq_gastro_hueco_arca_clave'
                );
                $table->index(['estado', 'fecha_jornada'], 'idx_gastro_hueco_arca_estado_fecha');
                $table->foreign('empresa_id', 'fk_gastro_hueco_arca_empresa')
                    ->references('id')->on('empresa')
                    ->cascadeOnDelete();
                $table->foreign('puntoventa_id', 'fk_gastro_hueco_arca_pv')
                    ->references('id')->on('puntoventa')
                    ->cascadeOnDelete();
                $table->foreign('turno_operativo_gastronomia_id', 'fk_gastro_hueco_arca_turno')
                    ->references('id')->on('turno_operativo_gastronomia')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gastronomia_hueco_arca_pendiente');
        Schema::dropIfExists('venta_gastronomia_nc_origen');
    }
};
