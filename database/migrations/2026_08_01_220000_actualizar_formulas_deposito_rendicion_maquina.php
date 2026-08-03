<?php

use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upsert catálogo v2: D25 calc.deposito + D30/F06 usan calc.deposito (paridad a-rendmaquina.c).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        $ahora = now();
        $porCodigo = [];
        foreach (RendicionMaquinaFormulaCatalogo::canonicos() as $paso) {
            $porCodigo[$paso['codigo']] = $paso;
        }

        foreach (['D25', 'D30', 'F06'] as $codigo) {
            $paso = $porCodigo[$codigo] ?? null;
            if ($paso === null) {
                continue;
            }

            $payload = [
                'destino' => $paso['destino'],
                'expresion' => $paso['expresion'],
                'seccion' => $paso['seccion'],
                'orden' => $paso['orden'],
                'activo' => $paso['activo'] ? 1 : 0,
                'solo_completo' => ! empty($paso['solo_completo']) ? 1 : 0,
                'detalle' => $paso['detalle'] ?? null,
                'version_catalogo' => RendicionMaquinaFormulaCatalogo::VERSION,
                'updated_at' => $ahora,
            ];

            $exists = DB::table('rendicion_maquina_formula')->where('codigo', $codigo)->exists();
            if ($exists) {
                DB::table('rendicion_maquina_formula')->where('codigo', $codigo)->update($payload);
            } else {
                DB::table('rendicion_maquina_formula')->insert(array_merge($payload, [
                    'codigo' => $codigo,
                    'created_at' => $ahora,
                ]));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        DB::table('rendicion_maquina_formula')->where('codigo', 'D25')->delete();

        DB::table('rendicion_maquina_formula')->where('codigo', 'D30')->update([
            'expresion' => 'calc.drop_bill_rodillo + calc.drop_bill_ruleta - calc.vale_rep_fondo + inputs.deposito - inputs.sobrantes',
            'version_catalogo' => 1,
            'updated_at' => now(),
        ]);

        DB::table('rendicion_maquina_formula')->where('codigo', 'F06')->update([
            'expresion' => '(inputs.vale_anterior + inputs.deposito - calc.transferencia) - (calc.resultado_rodillo + calc.resultado_ruleta) - prev.impuesto_drop_dia_ant + inputs.impuesto_drop',
            'version_catalogo' => 1,
            'updated_at' => now(),
        ]);
    }
};
