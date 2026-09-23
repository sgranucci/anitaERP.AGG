<?php

use App\Support\Caja\IngresoEgresoCanjeChequeSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de transacción de tesorería: Canje de cheques (CANJE) para IE.
 */
return new class extends Migration
{
    public function up(): void
    {
        $abreviatura = IngresoEgresoCanjeChequeSupport::ABREV_CANJE;
        $existente = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
            ->whereNull('deleted_at')
            ->first();

        $payload = [
            'nombre' => IngresoEgresoCanjeChequeSupport::NOMBRE,
            'operacion' => IngresoEgresoCanjeChequeSupport::OPERACION,
            'signo' => 1,
            'estado' => 'A',
            'updated_at' => now(),
        ];

        if ($existente) {
            DB::table('tipotransaccion_caja')->where('id', $existente->id)->update($payload);

            return;
        }

        DB::table('tipotransaccion_caja')->insert(array_merge($payload, [
            'abreviatura' => $abreviatura,
            'created_at' => now(),
        ]));
    }

    public function down(): void
    {
        // No borra CANJE: puede tener movimientos.
    }
};
