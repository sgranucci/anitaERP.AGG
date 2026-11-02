<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La rendición de variables de Anita pide el total de pagos parcial.
 * En la rendición de la caja 1 (comprobante 7) el 600.000 está en pagos
 * de casino on-line y las ventas on-line están en cero. El 4.556.600 que
 * quedó en el número de Z es el monto de pagos Z, que ya está en pago_cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }

        if (! Schema::hasColumn('tesoreria_crown_comprobante', 'pago_parcial')) {
            Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->decimal('pago_parcial', 14, 2)->default(0)->after('venta_cash');
            });
        }

        DB::table('tesoreria_crown_comprobante')
            ->where('id', 7)
            ->where('venta_online', 600000)
            ->where('pago_online', 0)
            ->where('nro_z_cash', '4556600')
            ->update([
                'pago_online' => 600000,
                'venta_online' => 0,
                'nro_z_cash' => null,
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }
        if (! Schema::hasColumn('tesoreria_crown_comprobante', 'pago_parcial')) {
            return;
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            $table->dropColumn('pago_parcial');
        });
    }
};
