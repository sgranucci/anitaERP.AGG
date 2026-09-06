<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice materializado del tracking de facturas.
 *
 * Resuelve por adelantado los tres datos que el listado no puede calcular con
 * columnas propias del ERP y que viven en el puente Anita (PDF escaneado y
 * estado de pago) o que la importación no trajo (fecha de carga real).
 * La grilla lee esta tabla; el puente sólo interviene en el backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobante_tracking_indice')) {
            return;
        }

        Schema::create('comprobante_tracking_indice', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');

            // --- PDF ---
            $table->string('pdf_origen', 20)->nullable();
            $table->unsignedBigInteger('pdf_documento_id')->nullable();
            $table->unsignedBigInteger('pdf_archivo_id')->nullable();
            $table->string('pdf_ruta', 500)->nullable();
            $table->boolean('pdf_disponible')->default(false);

            // --- Fecha de carga efectiva ---
            $table->date('fechacarga_efectiva')->nullable();
            $table->string('fechacarga_origen', 20)->nullable();

            // --- Pago (promov Anita o cuenta corriente ERP) ---
            $table->string('pago_estado', 20)->nullable();
            $table->string('pago_origen', 20)->nullable();
            $table->decimal('pago_monto', 18, 4)->nullable();
            $table->decimal('pago_pagado', 18, 4)->nullable();
            $table->decimal('pago_saldo', 18, 4)->nullable();
            $table->date('pago_fecha')->nullable();

            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->unique('comprobante_proveedor_id', 'uq_tracking_indice_comprobante');
            $table->index('pdf_disponible', 'ix_tracking_indice_pdf');
            $table->index('pago_estado', 'ix_tracking_indice_pago');
            $table->index('fechacarga_efectiva', 'ix_tracking_indice_fechacarga');

            $table->foreign('comprobante_proveedor_id', 'fk_tracking_indice_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_tracking_indice');
    }
};
