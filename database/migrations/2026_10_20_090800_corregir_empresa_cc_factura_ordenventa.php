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

        // Query Builder: MySQL arma INNER JOIN y PostgreSQL arma UPDATE … FROM.
        DB::table('cliente_cuentacorriente as cc')
            ->join('venta as v', 'v.id', '=', 'cc.venta_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('cc.cobranza_id')
            ->whereNotNull('v.ordenventa_id')
            ->whereColumn('cc.empresa_id', '<>', 'pv.empresa_id')
            ->update(['cc.empresa_id' => DB::raw('pv.empresa_id')]);
    }

    public function down(): void
    {
    }
};
