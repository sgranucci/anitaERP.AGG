<?php

use App\Support\Database\MigrationDialectSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso intermedio: unique (puntoventa_id, tipotransaccion_id, numerocomprobante).
 * Reemplaza el unique CAEA gastro (puntoventa + número) que mezclaba series
 * (FAC 1 bloqueaba NCD/RIN 1 en el mismo PV).
 *
 * Corre en todos los entornos. El paso siguiente (190500) lo reemplaza por
 * (codigo_afip, puntoventa_id, numerocomprobante).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO
            )) {
                $table->unique(
                    ['puntoventa_id', 'tipotransaccion_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO,
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta')) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(
            'venta',
            VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX_ELBIERZO_TIPO,
        );

        Schema::table('venta', function (Blueprint $table): void {
            if (! MigrationDialectSupport::tieneIndice(
                'venta',
                VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX
            )) {
                $table->unique(
                    ['puntoventa_id', 'numerocomprobante'],
                    VentaNumerocomprobanteUnicidadSupport::UNIQUE_INDEX,
                );
            }
        });
    }
};
