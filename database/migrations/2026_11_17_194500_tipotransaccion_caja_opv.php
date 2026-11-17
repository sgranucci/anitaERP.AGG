<?php

use App\Support\Caja\IngresoEgresoOperacionVariaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de transacción de tesorería: OPV (Operación varia) para IE.
 * Solo asiento contable; sin movimiento de cuentas de caja ni tesmov Anita.
 */
return new class extends Migration
{
    public function up(): void
    {
        $abreviatura = IngresoEgresoOperacionVariaSupport::ABREV_OPV;
        $existente = DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
            ->whereNull('deleted_at')
            ->first();

        $payload = [
            'nombre' => IngresoEgresoOperacionVariaSupport::NOMBRE,
            'operacion' => IngresoEgresoOperacionVariaSupport::OPERACION,
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
        // No borra OPV: puede tener movimientos.
    }
};
