<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fórmula local protegida: «Actualizar fórmulas desde Anita», «Retraducir» y «Sincronizar»
 * no pisan un concepto marcado. El 25/11/2026 10:17:44 la actualización desde Anita volvió a
 * dejar el fallback de saldo TVAC en 420/422 (corregido el 13/11 con
 * 2026_11_13_130100_fix_formulas_vacaciones_plus_adelanto).
 *
 * Tabla compartida por todos los clientes; columna con default false: sin efecto en lo existente.
 * Se marcan 420/422 solo si su cantidad ya no usa el saldo (total_vacaciones), es decir donde
 * la corrección local está aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('concepto_sueldos')) {
            return;
        }

        if (! Schema::hasColumn('concepto_sueldos', 'formula_local_protegida')) {
            Schema::table('concepto_sueldos', function (Blueprint $table) {
                $table->boolean('formula_local_protegida')->default(false)->after('activo')
                    ->comment('1 = la actualización desde Anita no pisa la fórmula de este concepto');
            });
        }

        $codigos = DB::table('concepto_sueldos')
            ->whereIn('codigo', [420, 422])
            ->where(function ($q) {
                $q->whereNull('formula_cantidad')
                    ->orWhere('formula_cantidad', 'not like', '%total_vacaciones%');
            })
            ->where(function ($q) {
                $q->whereNull('formula')
                    ->orWhere('formula', 'not like', '%total_vacaciones%');
            })
            ->pluck('id');

        if ($codigos->isNotEmpty()) {
            DB::table('concepto_sueldos')->whereIn('id', $codigos)->update(['formula_local_protegida' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('concepto_sueldos') && Schema::hasColumn('concepto_sueldos', 'formula_local_protegida')) {
            Schema::table('concepto_sueldos', function (Blueprint $table) {
                $table->dropColumn('formula_local_protegida');
            });
        }
    }
};
