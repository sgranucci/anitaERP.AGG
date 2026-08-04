<?php

use App\Support\Caja\IngresoEgresoTransferenciaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipo de transacción de tesorería: Transferencia (TRA) para IE entre cuentas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $abreviatura = IngresoEgresoTransferenciaSupport::ABREV_TRA;
        $existente = DB::table('tipotransaccion_caja')
            ->where('abreviatura', $abreviatura)
            ->whereNull('deleted_at')
            ->first();

        $payload = [
            'nombre' => IngresoEgresoTransferenciaSupport::NOMBRE,
            'operacion' => IngresoEgresoTransferenciaSupport::OPERACION,
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
        // No borra TRA: puede tener movimientos.
    }
};
