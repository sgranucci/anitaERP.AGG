<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paridad Anita calcula_rendicion_turno_completo / lee_rendiciones_del_dia:
 * - D30: deposito_efectivo = 0 en Completo
 * - E10/E30/E40: conservar semillas de Noche / suma M+T+N en Completo
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

        foreach (['D30', 'E10', 'E30', 'E40'] as $codigo) {
            if (! isset($porCodigo[$codigo])) {
                continue;
            }
            DB::table('rendicion_maquina_formula')
                ->where('codigo', $codigo)
                ->update([
                    'expresion' => $porCodigo[$codigo]['expresion'],
                    'detalle' => $porCodigo[$codigo]['detalle'] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Sin rollback: las expresiones nuevas son la paridad Anita vigente.
    }
};
