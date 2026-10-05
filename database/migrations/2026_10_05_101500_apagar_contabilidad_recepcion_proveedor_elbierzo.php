<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Bierzo no provisiona la COM: la recepción mueve stock y la factura imputa el neto.
 * La migración AGG de Biyemas prendió activa_contabilidad en empresa id 1 (acá es Frigorífico).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $ahora = now();
        $empresaIds = DB::table('empresa')->pluck('id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $existe = DB::table('configuracion_recepcion_proveedor')
                ->where('empresa_id', $empresaId)
                ->exists();

            if ($existe) {
                DB::table('configuracion_recepcion_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->update([
                        'activa_contabilidad' => false,
                        'updated_at' => $ahora,
                    ]);
            } else {
                DB::table('configuracion_recepcion_proveedor')->insert([
                    'empresa_id' => $empresaId,
                    'activa_contabilidad' => false,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        DB::table('configuracion_recepcion_proveedor')
            ->where('empresa_id', 1)
            ->update([
                'activa_contabilidad' => true,
                'updated_at' => now(),
            ]);
    }
};
