<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Ventas\ArcaMtxcaComprobanteTotalesSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solo Ferli / Calzados Ferli: impuesto.codigoarca quedó vacío tras
 * 2025_11_28_agregar_codigoarca_impuesto. WSFE exige Id AlicIVA
 * (FEParamGetTiposIva); sin valor AFIP responde [10019].
 *
 * Completa solo filas vacías / 0, usando tasa → Id ARCA o codigo si ya es válido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() && ! MovimientoStockFerliSupport::esCalzadosFerli()) {
            return;
        }

        if (! Schema::hasTable('impuesto') || ! Schema::hasColumn('impuesto', 'codigoarca')) {
            return;
        }

        $filas = DB::table('impuesto')->select(['id', 'nombre', 'valor', 'codigo', 'codigoarca'])->get();
        foreach ($filas as $fila) {
            $actual = trim((string) ($fila->codigoarca ?? ''));
            if ($actual !== '' && $actual !== '0' && ArcaMtxcaComprobanteTotalesSupport::esCondicionGravada((int) $actual)) {
                continue;
            }
            try {
                $id = ArcaMtxcaComprobanteTotalesSupport::resolverIdAlicIva(
                    null,
                    $fila->codigo,
                    (float) ($fila->valor ?? 0),
                );
            } catch (\Throwable) {
                continue;
            }
            DB::table('impuesto')->where('id', $fila->id)->update(['codigoarca' => (string) $id]);
        }
    }

    public function down(): void
    {
        // No revierte: el vacío original era el bug.
    }
};
