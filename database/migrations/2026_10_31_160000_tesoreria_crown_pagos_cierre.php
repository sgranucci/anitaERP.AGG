<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cierre de cajero (p-cierrecajero.c) usa pagos del Z, pagos online y pagos
 * de apuestas. La rendición ya guardaba las ventas. El pago cashless de la
 * rendición que se está comparando con Anita (ventas 4.661.000) es el
 * efe_total_z_cash 4.556.600 del efectivo 2479345.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_comprobante', 'pago_cash')) {
                $table->decimal('pago_cash', 14, 2)->default(0)->after('venta_cash');
            }
            if (! Schema::hasColumn('tesoreria_crown_comprobante', 'pago_online')) {
                $table->decimal('pago_online', 14, 2)->default(0)->after('venta_online');
            }
            if (! Schema::hasColumn('tesoreria_crown_comprobante', 'pago_apuesta')) {
                $table->decimal('pago_apuesta', 14, 2)->default(0)->after('vta_apuesta');
            }
        });

        DB::table('tesoreria_crown_comprobante')
            ->where('venta_cash', 4661000)
            ->where('pago_cash', 0)
            ->update(['pago_cash' => 4556600]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }

        $columnas = array_values(array_filter(
            ['pago_cash', 'pago_online', 'pago_apuesta'],
            fn (string $columna) => Schema::hasColumn('tesoreria_crown_comprobante', $columna),
        ));
        if ($columnas === []) {
            return;
        }

        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) use ($columnas) {
            $table->dropColumn($columnas);
        });
    }
};
