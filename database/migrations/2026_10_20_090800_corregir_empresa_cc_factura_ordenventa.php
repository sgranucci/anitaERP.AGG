<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturar una orden de venta grababa la cuenta corriente en la empresa 1.
 * El comprobante sigue el punto de venta. El asiento ya estaba en esa empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliente_cuentacorriente')
            || ! Schema::hasTable('venta')
            || ! Schema::hasTable('puntoventa')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE cliente_cuentacorriente AS cc
                SET empresa_id = pv.empresa_id
                FROM venta AS v
                JOIN puntoventa AS pv ON pv.id = v.puntoventa_id
                WHERE v.id = cc.venta_id
                  AND cc.cobranza_id IS NULL
                  AND v.ordenventa_id IS NOT NULL
                  AND cc.empresa_id <> pv.empresa_id
            ');

            return;
        }

        DB::statement('
            UPDATE cliente_cuentacorriente cc
            INNER JOIN venta v ON v.id = cc.venta_id
            INNER JOIN puntoventa pv ON pv.id = v.puntoventa_id
            SET cc.empresa_id = pv.empresa_id
            WHERE cc.cobranza_id IS NULL
              AND v.ordenventa_id IS NOT NULL
              AND cc.empresa_id <> pv.empresa_id
        ');
    }

    public function down(): void
    {
    }
};
