<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completo: el extra max(drop − remesa, 0) va a CAJA PESOS del arqueo.
 * D30 vuelve a 0 en C (paridad Anita). D50 C no suma el vale/remesa.
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

        foreach (['D30', 'D50'] as $codigo) {
            if (! isset($porCodigo[$codigo])) {
                continue;
            }
            DB::table('rendicion_maquina_formula')
                ->where('codigo', $codigo)
                ->update([
                    'expresion' => $porCodigo[$codigo]['expresion'],
                    'detalle' => $porCodigo[$codigo]['detalle'] ?? null,
                    'version_catalogo' => RendicionMaquinaFormulaCatalogo::VERSION,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Sin rollback: D30=0 en C es la paridad Anita.
    }
};
