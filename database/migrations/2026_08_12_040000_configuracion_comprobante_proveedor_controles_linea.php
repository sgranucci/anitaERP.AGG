<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controles por línea (SKU / precio unitario) en config de factura proveedor.
 * Default false: AGG y el resto siguen solo con control de importe cabecera vs COM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
            $table->boolean('controla_sku_vs_com')->default(false)->after('exige_flujo_oc_com_fac');
            $table->boolean('controla_precio_unitario')->default(false)->after('controla_sku_vs_com');
            $table->decimal('tolerancia_precio_pct', 8, 4)->default(0)->after('controla_precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
            $table->dropColumn([
                'controla_sku_vs_com',
                'controla_precio_unitario',
                'tolerancia_precio_pct',
            ]);
        });
    }
};
