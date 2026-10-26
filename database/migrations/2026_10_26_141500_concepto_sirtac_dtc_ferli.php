<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SIRTAC como concepto de IVA compras del DTC.
 * La cuenta es la que ya existe en el plan: 114210099 SIRTAC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('concepto_ivacompra')
            || ! DB::getSchemaBuilder()->hasTable('tipotransaccion_compra_concepto_ivacompra')) {
            return;
        }

        $cuentaId = (int) (DB::table('cuentacontable')->where('codigo', '114210099')->value('id') ?? 0);
        if ($cuentaId <= 0) {
            return;
        }

        $conceptoId = (int) (DB::table('concepto_ivacompra')
            ->where(function ($q) {
                $q->where('codigo', '611')->orWhere('nombre', 'SIRTAC');
            })
            ->value('id') ?? 0);

        if ($conceptoId <= 0) {
            $columnaId = (int) (DB::table('concepto_ivacompra')->where('codigo', '201')->value('columna_ivacompra_id') ?? 0);
            if ($columnaId <= 0) {
                $columnaId = (int) (DB::table('columna_ivacompra')->where('nombre', 'like', 'RET GCIAS%')->value('id') ?? 0);
            }

            $conceptoId = (int) DB::table('concepto_ivacompra')->insertGetId([
                'nombre' => 'SIRTAC',
                'codigo' => '611',
                'formula' => ' ',
                'columna_ivacompra_id' => $columnaId > 0 ? $columnaId : null,
                'empresa_id' => null,
                'cuentacontabledebe_id' => $cuentaId,
                'cuentacontablehaber_id' => $cuentaId,
                'tipoconcepto' => 'N',
                'retieneganancia' => 'N',
                'retieneIIBB' => 'N',
                'provincia_id' => null,
                'impuesto_id' => null,
                'nombre_ia' => 'SIRTAC',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('concepto_ivacompra')->where('id', $conceptoId)->update([
                'cuentacontabledebe_id' => $cuentaId,
                'cuentacontablehaber_id' => $cuentaId,
                'updated_at' => now(),
            ]);
        }

        $tipoId = (int) (DB::table('tipotransaccion_compra')
            ->where('abreviatura', 'DTC')
            ->where('estado', 'A')
            ->value('id') ?? 0);
        if ($tipoId <= 0 || $conceptoId <= 0) {
            return;
        }

        $ya = DB::table('tipotransaccion_compra_concepto_ivacompra')
            ->where('tipotransaccion_compra_id', $tipoId)
            ->where('concepto_ivacompra_id', $conceptoId)
            ->exists();
        if ($ya) {
            return;
        }

        DB::table('tipotransaccion_compra_concepto_ivacompra')->insert([
            'tipotransaccion_compra_id' => $tipoId,
            'concepto_ivacompra_id' => $conceptoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $conceptoId = (int) (DB::table('concepto_ivacompra')->where('codigo', '611')->where('nombre', 'SIRTAC')->value('id') ?? 0);
        if ($conceptoId <= 0) {
            return;
        }

        DB::table('tipotransaccion_compra_concepto_ivacompra')
            ->where('concepto_ivacompra_id', $conceptoId)
            ->delete();

        $usado = DB::getSchemaBuilder()->hasTable('comprobante_proveedor_concepto')
            && DB::table('comprobante_proveedor_concepto')->where('concepto_ivacompra_id', $conceptoId)->exists();
        if (! $usado) {
            $concepto = \App\Models\Compras\Concepto_Ivacompra::query()->find($conceptoId);
            if ($concepto) {
                $concepto->delete();
            }
        }
    }
};
