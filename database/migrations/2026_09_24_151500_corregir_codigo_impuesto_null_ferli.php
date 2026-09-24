<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Ventas\AnitaVengravCodigoTasaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solo Ferli / Calzados Ferli: impuesto Gravado 27% quedó con codigo = 'NULL'
 * (texto). vengrav.veng_codigo_tasa es numérico en Informix → 1213 Character
 * to numeric conversion al facturar ítems al 27 % (ej. FAC A-00012-00083127).
 *
 * Completa codigo con codigoarca o tasa → Id AlicIVA, solo si el actual no es numérico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() && ! MovimientoStockFerliSupport::esCalzadosFerli()) {
            return;
        }

        if (! Schema::hasTable('impuesto')) {
            return;
        }

        $filas = DB::table('impuesto')->select(['id', 'nombre', 'valor', 'codigo', 'codigoarca'])->get();
        foreach ($filas as $fila) {
            $actual = trim((string) ($fila->codigo ?? ''));
            if ($actual !== '' && strcasecmp($actual, 'NULL') !== 0 && is_numeric($actual)) {
                continue;
            }
            try {
                $id = AnitaVengravCodigoTasaSupport::resolver(
                    $fila->codigo,
                    $fila->codigoarca ?? null,
                    (float) ($fila->valor ?? 0),
                );
            } catch (\Throwable) {
                continue;
            }
            DB::table('impuesto')->where('id', $fila->id)->update(['codigo' => (string) $id]);
        }
    }

    public function down(): void
    {
        // No revierte: el literal NULL era el bug.
    }
};
