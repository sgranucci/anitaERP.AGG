<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula el legajo marketplace a la OPP de reembolso (Ingresos/Egresos, sin proveedor).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cambio_devolucion_marketplace')) {
            return;
        }
        if (Schema::hasColumn('cambio_devolucion_marketplace', 'caja_movimiento_id')) {
            return;
        }

        Schema::table('cambio_devolucion_marketplace', function (Blueprint $table) {
            $table->unsignedBigInteger('caja_movimiento_id')->nullable()->after('venta_nc_id');
            $table->index('caja_movimiento_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cambio_devolucion_marketplace')) {
            return;
        }
        if (! Schema::hasColumn('cambio_devolucion_marketplace', 'caja_movimiento_id')) {
            return;
        }

        Schema::table('cambio_devolucion_marketplace', function (Blueprint $table) {
            $table->dropIndex(['caja_movimiento_id']);
            $table->dropColumn('caja_movimiento_id');
        });
    }
};
