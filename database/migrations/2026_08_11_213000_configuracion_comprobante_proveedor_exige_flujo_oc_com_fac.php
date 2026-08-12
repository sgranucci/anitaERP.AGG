<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo OC/COM/FAC por empresa: COM obligatoria (estilo Biyemas) salvo factura anticipada sin COM aún.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion_comprobante_proveedor')) {
            return;
        }

        if (! Schema::hasColumn('configuracion_comprobante_proveedor', 'exige_flujo_oc_com_fac')) {
            Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
                $table->boolean('exige_flujo_oc_com_fac')->default(false)->after('activo');
            });
        }

        // Biyemas y variantes: activar flujo estricto OC → COM → FAC.
        $empresaIds = DB::table('empresa')
            ->where(function ($q) {
                $q->whereRaw('UPPER(nombre) LIKE ?', ['%BIYEMAS%'])
                    ->orWhere('id', 1);
            })
            ->pluck('id');

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
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('configuracion_comprobante_proveedor')->insert([
                    'empresa_id' => $empresaId,
                    'activo' => true,
                    'exige_flujo_oc_com_fac' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuracion_comprobante_proveedor')) {
            return;
        }

        if (Schema::hasColumn('configuracion_comprobante_proveedor', 'exige_flujo_oc_com_fac')) {
            Schema::table('configuracion_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('exige_flujo_oc_com_fac');
            });
        }
    }
};
