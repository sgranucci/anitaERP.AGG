<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rendición máquinas (uso 8): Maco dólar/euro → Caja dólar/euro.
 * Así la cuentacontable del valor coincide con Anita (111010002 / 111010003).
 */
return new class extends Migration
{
    private const USO_RENDICION_MAQUINAS = 8;

    /**
     * empresa_id => [maco_dolar_codigo, caja_dolar_codigo, maco_euro_codigo, caja_euro_codigo]
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const SWAP_POR_EMPRESA = [
        1 => ['121', '110', '122', '129'],
        2 => ['221', '210', '222', '219'],
        3 => ['321', '310', '322', '319'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('cuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        foreach (self::SWAP_POR_EMPRESA as $empresaId => [$macoDolar, $cajaDolar, $macoEuro, $cajaEuro]) {
            $this->swapUsoYValores($empresaId, $macoDolar, $cajaDolar);
            $this->swapUsoYValores($empresaId, $macoEuro, $cajaEuro);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('cuentacaja') || ! Schema::hasTable('cuentacaja_usocuentacaja')) {
            return;
        }

        foreach (self::SWAP_POR_EMPRESA as $empresaId => [$macoDolar, $cajaDolar, $macoEuro, $cajaEuro]) {
            $this->swapUsoYValores($empresaId, $cajaDolar, $macoDolar);
            $this->swapUsoYValores($empresaId, $cajaEuro, $macoEuro);
        }
    }

    private function swapUsoYValores(int $empresaId, string $codigoDesde, string $codigoHasta): void
    {
        $desdeId = (int) (DB::table('cuentacaja')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoDesde)
            ->value('id') ?? 0);
        $hastaId = (int) (DB::table('cuentacaja')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigoHasta)
            ->value('id') ?? 0);

        if ($desdeId <= 0 || $hastaId <= 0) {
            return;
        }

        DB::table('cuentacaja_usocuentacaja')
            ->where('cuentacaja_id', $desdeId)
            ->where('usocuentacaja_id', self::USO_RENDICION_MAQUINAS)
            ->delete();

        $yaTiene = DB::table('cuentacaja_usocuentacaja')
            ->where('cuentacaja_id', $hastaId)
            ->where('usocuentacaja_id', self::USO_RENDICION_MAQUINAS)
            ->exists();

        if (! $yaTiene) {
            DB::table('cuentacaja_usocuentacaja')->insert([
                'cuentacaja_id' => $hastaId,
                'usocuentacaja_id' => self::USO_RENDICION_MAQUINAS,
            ]);
        }

        if (! Schema::hasTable('rendicion_maquina_valor')) {
            return;
        }

        DB::table('rendicion_maquina_valor')
            ->where('cuentacaja_id', $desdeId)
            ->update([
                'cuentacaja_id' => $hastaId,
                'updated_at' => now(),
            ]);
    }
};
