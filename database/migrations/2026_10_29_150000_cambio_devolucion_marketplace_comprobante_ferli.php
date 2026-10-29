<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de facturas y notas del legajo de cambio/devolución (Ferli).
 * Permite anular un reemplazo mal cargado y facturar otro sin perder los PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('cambio_devolucion_marketplace')) {
            return;
        }
        if (Schema::hasTable('cambio_devolucion_marketplace_comprobante')) {
            return;
        }

        Schema::create('cambio_devolucion_marketplace_comprobante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cambio_id');
            $table->string('tipo', 30);
            $table->unsignedBigInteger('venta_id');
            $table->timestamps();

            $table->foreign('cambio_id', 'fk_cdmc_cambio')
                ->references('id')->on('cambio_devolucion_marketplace')->onDelete('cascade');
            $table->foreign('venta_id', 'fk_cdmc_venta')
                ->references('id')->on('venta')->onDelete('restrict');
            $table->index(['cambio_id', 'tipo'], 'idx_cdmc_cambio_tipo');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('cambio_devolucion_marketplace_comprobante');
    }
};
