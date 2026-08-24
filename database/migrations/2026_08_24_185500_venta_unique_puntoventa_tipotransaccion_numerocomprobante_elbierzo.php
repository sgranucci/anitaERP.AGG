<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\MigrationDialectSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL BIERZO: FAC y NCD numeran en series distintas (como AGG).
 * El unique (puntoventa_id, numerocomprobante) bloqueaba NCD 1 si ya existía FAC 1 en el mismo PV
 * (Villafranca / PV 15). AGG no corre este cambio: conserva el unique CAEA gastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('venta')) {
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
        if (! EntornoEmpresaSupport::esElBierzo() || ! Schema::hasTable('venta')) {
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
