<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percepción SIRCIP congelada por comprobante de venta y jurisdicción.
 *
 * Una fila por factura/NC y jurisdicción de la operación. Guarda lo que la DDJJ
 * mensual necesita y que no se puede recalcular después: tipo de registro (1, 2,
 * 4, 5), CRC del contribuyente y período del padrón usado, base, alícuota e importe.
 *
 * Así una DDJJ ya presentada no cambia si después se pisa o purga el padrón.
 * venta_id no lleva FK: mismo criterio que venta_impuesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_sircip')) {
            return;
        }

        Schema::create('venta_sircip', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('venta_impuesto_id')->nullable();
            $table->unsignedSmallInteger('jurisdiccion');
            $table->unsignedTinyInteger('tipo_registro');
            $table->char('periodo_padron', 6)->nullable();
            $table->unsignedTinyInteger('crc')->nullable();
            $table->char('alicuota_letra', 1)->nullable();
            $table->decimal('alicuota', 10, 6)->default(0);
            $table->decimal('base', 20, 6)->default(0);
            $table->decimal('importe', 20, 6)->default(0);
            $table->char('comprobante_original', 17)->nullable();
            $table->timestamps();

            $table->index('venta_id', 'ix_venta_sircip_venta');
            $table->index('venta_impuesto_id', 'ix_venta_sircip_venta_impuesto');
            $table->index(['periodo_padron', 'jurisdiccion'], 'ix_venta_sircip_periodo_jurisdiccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_sircip');
    }
};
