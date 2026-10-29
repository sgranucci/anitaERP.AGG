<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca qué líneas de reemplazo ya se facturaron, para sumar otro par
 * y emitir una factura aparte sin anular la anterior (Ferli).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('cambio_devolucion_marketplace_linea')
            || ! Schema::hasTable('cambio_devolucion_marketplace_comprobante')) {
            return;
        }

        if (! Schema::hasColumn('cambio_devolucion_marketplace_linea', 'venta_fac_id')) {
            Schema::table('cambio_devolucion_marketplace_linea', function (Blueprint $table) {
                $table->unsignedBigInteger('venta_fac_id')->nullable()->after('venta_emision_id');
                $table->foreign('venta_fac_id', 'fk_cdml_venta_fac')
                    ->references('id')->on('venta')->onDelete('restrict');
            });
        }

        if (! Schema::hasColumn('cambio_devolucion_marketplace_comprobante', 'anula_venta_id')) {
            Schema::table('cambio_devolucion_marketplace_comprobante', function (Blueprint $table) {
                $table->unsignedBigInteger('anula_venta_id')->nullable()->after('venta_id');
            });
        }

        $legajos = DB::table('cambio_devolucion_marketplace')
            ->where('venta_reemplazo_id', '>', 0)
            ->get(['id', 'venta_reemplazo_id']);

        foreach ($legajos as $legajo) {
            $ventaId = (int) $legajo->venta_reemplazo_id;
            if (! DB::table('venta')->where('id', $ventaId)->exists()) {
                continue;
            }

            DB::table('cambio_devolucion_marketplace_linea')
                ->where('cambio_id', $legajo->id)
                ->where('tipo', 'reemplazo')
                ->whereNull('venta_fac_id')
                ->update(['venta_fac_id' => $ventaId]);

            $ya = DB::table('cambio_devolucion_marketplace_comprobante')
                ->where('cambio_id', $legajo->id)
                ->where('tipo', 'fac_reemplazo')
                ->where('venta_id', $ventaId)
                ->exists();
            if ($ya) {
                continue;
            }

            DB::table('cambio_devolucion_marketplace_comprobante')->insert([
                'cambio_id' => $legajo->id,
                'tipo' => 'fac_reemplazo',
                'venta_id' => $ventaId,
                'anula_venta_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (Schema::hasTable('cambio_devolucion_marketplace_linea')
            && Schema::hasColumn('cambio_devolucion_marketplace_linea', 'venta_fac_id')) {
            Schema::table('cambio_devolucion_marketplace_linea', function (Blueprint $table) {
                $table->dropForeign('fk_cdml_venta_fac');
                $table->dropColumn('venta_fac_id');
            });
        }

        if (Schema::hasTable('cambio_devolucion_marketplace_comprobante')
            && Schema::hasColumn('cambio_devolucion_marketplace_comprobante', 'anula_venta_id')) {
            Schema::table('cambio_devolucion_marketplace_comprobante', function (Blueprint $table) {
                $table->dropColumn('anula_venta_id');
            });
        }
    }
};
