<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: flujo estricto OC→COM→FAC en Kandiko y Rebisco (mismo criterio que Biyemas).
 * Excepción OC→FAC sigue en contrato sin recepción o OC anticipada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresaIds = DB::table('empresa')
            ->where(function ($q) {
                $q->whereRaw('UPPER(nombre) LIKE ?', ['%KANDIKO%'])
                    ->orWhereRaw('UPPER(nombre) LIKE ?', ['%REBISCO%']);
            })
            ->pluck('id');

        $ahora = now();

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $existe = DB::table('configuracion_comprobante_proveedor')
                ->where('empresa_id', $empresaId)
                ->exists();

            if ($existe) {
                DB::table('configuracion_comprobante_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->update([
                        'exige_flujo_oc_com_fac' => true,
                        'activo' => true,
                        'updated_at' => $ahora,
                    ]);
            } else {
                DB::table('configuracion_comprobante_proveedor')->insert([
                    'empresa_id' => $empresaId,
                    'activo' => true,
                    'exige_flujo_oc_com_fac' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        $empresaIds = DB::table('empresa')
            ->where(function ($q) {
                $q->whereRaw('UPPER(nombre) LIKE ?', ['%KANDIKO%'])
                    ->orWhereRaw('UPPER(nombre) LIKE ?', ['%REBISCO%']);
            })
            ->pluck('id');

        if ($empresaIds->isEmpty()) {
            return;
        }

        DB::table('configuracion_comprobante_proveedor')
            ->whereIn('empresa_id', $empresaIds)
            ->update([
                'exige_flujo_oc_com_fac' => false,
                'updated_at' => now(),
            ]);
    }
};
