<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de tesorería OPA (orden de pago adelantada) para SP ANTICIPADA.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existente = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPA'])
            ->whereNull('deleted_at')
            ->first();

        if ($existente) {
            return;
        }

        $opp = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP'])
            ->whereNull('deleted_at')
            ->first();

        DB::table('tipotransaccion_caja')->insert([
            'nombre' => 'Orden de pago adelantada',
            'operacion' => $opp->operacion ?? 'P',
            'abreviatura' => 'OPA',
            'signo' => $opp->signo ?? -1,
            'estado' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No borra OPA: puede tener movimientos.
    }
};
