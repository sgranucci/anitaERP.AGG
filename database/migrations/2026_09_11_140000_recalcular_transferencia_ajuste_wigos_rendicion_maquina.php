<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recálculo de transferencia con ajuste WIGOS: el arqueo (depósito) entra a
 * salidas también en Completo, y E10/E30/E40 se recalculan (no quedan congelados).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        $porCodigo = [];
        foreach (RendicionMaquinaFormulaCatalogo::canonicos() as $paso) {
            $porCodigo[(string) $paso['codigo']] = $paso;
        }

        $ahora = now();
        foreach (['D30', 'D50', 'E10', 'E30', 'E40'] as $codigo) {
            if (! isset($porCodigo[$codigo])) {
                continue;
            }
            $paso = $porCodigo[$codigo];
            DB::table('rendicion_maquina_formula')
                ->where('codigo', $codigo)
                ->update([
                    'expresion' => $paso['expresion'],
                    'detalle' => $paso['detalle'] ?? null,
                    'version_catalogo' => RendicionMaquinaFormulaCatalogo::VERSION,
                    'updated_at' => $ahora,
                ]);
        }
    }

    public function down(): void
    {
        // Sin rollback: las expresiones nuevas son el recálculo con ajuste WIGOS.
    }
};
