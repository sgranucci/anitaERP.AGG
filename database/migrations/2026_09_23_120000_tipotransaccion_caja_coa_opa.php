<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * COA (cobro anticipado) y OPA (orden de pago adelantada).
 * Un documento de tesorería sin comprobantes aplicados usa estos tipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->asegurar('COA', 'COB', 'Cobro anticipado', 'C', 1);
        $this->asegurar('OPA', 'OPP', 'Orden de pago adelantada', 'P', -1);
    }

    public function down(): void
    {
        // No borra: pueden tener movimientos.
    }

    private function asegurar(string $abreviatura, string $copiarDe, string $nombre, string $operacion, int $signo): void
    {
        $existente = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
            ->orderBy('id')
            ->first();

        if ($existente) {
            if ($existente->deleted_at !== null) {
                DB::table('tipotransaccion_caja')->where('id', $existente->id)->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $base = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$copiarDe])
            ->whereNull('deleted_at')
            ->first();

        DB::table('tipotransaccion_caja')->insert([
            'nombre' => $nombre,
            'operacion' => $base->operacion ?? $operacion,
            'abreviatura' => $abreviatura,
            'signo' => $base->signo ?? $signo,
            'estado' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
