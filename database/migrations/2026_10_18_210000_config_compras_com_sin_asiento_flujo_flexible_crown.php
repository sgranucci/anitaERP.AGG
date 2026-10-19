<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CROWN: COM sin asiento (recepción no valuada) y flujo CP flexible
 * (factura puede ir sola, sin COM ni OC). Editable luego en Configuración.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        $empresaIds = DB::table('empresa')->pluck('id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            if (Schema::hasTable('configuracion_recepcion_proveedor')) {
                $existeRec = DB::table('configuracion_recepcion_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->exists();

                if ($existeRec) {
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

            if (Schema::hasTable('configuracion_comprobante_proveedor')) {
                $existeCp = DB::table('configuracion_comprobante_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->exists();

                if ($existeCp) {
                    DB::table('configuracion_comprobante_proveedor')
                        ->where('empresa_id', $empresaId)
                        ->update([
                            'exige_flujo_oc_com_fac' => false,
                            'activo' => true,
                            'updated_at' => $ahora,
                        ]);
                } else {
                    $fila = [
                        'empresa_id' => $empresaId,
                        'activo' => true,
                        'exige_flujo_oc_com_fac' => false,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                    if (Schema::hasColumn('configuracion_comprobante_proveedor', 'controla_sku_vs_com')) {
                        $fila['controla_sku_vs_com'] = false;
                    }
                    if (Schema::hasColumn('configuracion_comprobante_proveedor', 'controla_precio_unitario')) {
                        $fila['controla_precio_unitario'] = false;
                    }
                    if (Schema::hasColumn('configuracion_comprobante_proveedor', 'tolerancia_precio_pct')) {
                        $fila['tolerancia_precio_pct'] = 0;
                    }
                    DB::table('configuracion_comprobante_proveedor')->insert($fila);
                }
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        // No revierte a valuado/estricto: el operador puede cambiarlo en pantalla.
    }
};
