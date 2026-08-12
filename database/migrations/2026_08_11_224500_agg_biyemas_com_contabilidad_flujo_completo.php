<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG / Biyemas: circuito completo OC→COM→FAC con asiento (provisión FAR) al confirmar COM.
 * Asegura exige_flujo_oc_com_fac + activa_contabilidad en empresas Biyemas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresaIds = DB::table('empresa')
            ->where(function ($q) {
                $q->whereRaw('UPPER(nombre) LIKE ?', ['%BIYEMAS%'])
                    ->orWhere('id', 1);
            })
            ->pluck('id');

        $ahora = now();

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $existeCp = DB::table('configuracion_comprobante_proveedor')
                ->where('empresa_id', $empresaId)
                ->exists();

            if ($existeCp) {
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

            $existeRec = DB::table('configuracion_recepcion_proveedor')
                ->where('empresa_id', $empresaId)
                ->exists();

            if ($existeRec) {
                DB::table('configuracion_recepcion_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->update([
                        'activa_contabilidad' => true,
                        'updated_at' => $ahora,
                    ]);
            } else {
                DB::table('configuracion_recepcion_proveedor')->insert([
                    'empresa_id' => $empresaId,
                    'activa_contabilidad' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revierte: era alineación operativa AGG; dejar flags como estén.
    }
};
