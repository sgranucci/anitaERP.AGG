<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FECHA_CIERRE = '2026-07-31';

    private const SALDOS = [
        1 => 27441570.93,
        2 => -4657078479.85,
        3 => -57892620223.86,
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('posicion_financiera_saldo')) {
            return;
        }

        foreach (self::SALDOS as $empresaId => $saldoFinal) {
            $existe = DB::table('posicion_financiera_saldo')
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_cierre', self::FECHA_CIERRE)
                ->whereNull('anulado_at')
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('posicion_financiera_saldo')->insert([
                'empresa_id' => $empresaId,
                'fecha_cierre' => self::FECHA_CIERRE,
                'saldo_inicial' => null,
                'saldo_final' => $saldoFinal,
                'origen' => 'semilla_anita',
                'filtros_json' => json_encode([
                    'fuente' => 'saldoposf',
                    'fecha_anita' => 20260731,
                ]),
                'confirmado_por' => null,
                'confirmado_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('posicion_financiera_saldo')) {
            return;
        }

        DB::table('posicion_financiera_saldo')
            ->where('fecha_cierre', self::FECHA_CIERRE)
            ->where('origen', 'semilla_anita')
            ->whereNull('confirmado_por')
            ->delete();
    }
};
