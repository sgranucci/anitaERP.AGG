<?php

use App\Support\Caja\IngresoEgresoTransferenciaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: tipos de caja salieron del stub (COB/OPP/DEV/ING/EGR) y no
 * incluyen TRA. La card «Transferencia entre cuentas» filtra solo TRA / operación T.
 *
 * Solo Ferli. No toca AGG (ya tiene TRA por 2026_08_03_180000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $abreviatura = IngresoEgresoTransferenciaSupport::ABREV_TRA;
        $existente = DB::table('tipotransaccion_caja')
            ->where('abreviatura', $abreviatura)
            ->first();

        $payload = [
            'nombre' => IngresoEgresoTransferenciaSupport::NOMBRE,
            'operacion' => IngresoEgresoTransferenciaSupport::OPERACION,
            'signo' => 1,
            'estado' => 'A',
            'updated_at' => now(),
            'deleted_at' => null,
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
