<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completo: depósito efectivo = acumulado M+T+N + max(drop − remesa, 0).
 * La remesa no vuelve a sumar en total salidas del C.
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
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        DB::table('rendicion_maquina_formula')
            ->where('codigo', 'D30')
            ->update([
                'expresion' => 'meta.es_completo > 0 ? 0 : (calc.drop_bill_rodillo + calc.drop_bill_ruleta - calc.vale_rep_fondo + calc.deposito - inputs.sobrantes)',
                'detalle' => 'Depósito efectivo (0 en turno C; fórmula Anita en M/T/N)',
                'updated_at' => now(),
            ]);
        DB::table('rendicion_maquina_formula')
            ->where('codigo', 'D50')
            ->update([
                'expresion' => 'calc.tito_rodillo + calc.tito_ruleta + calc.vale_rep_fondo + inputs.salida_ruleta + inputs.pago_manual + calc.deposito_efectivo + inputs.hopper',
                'detalle' => 'Total salidas',
                'updated_at' => now(),
            ]);
    }
};
