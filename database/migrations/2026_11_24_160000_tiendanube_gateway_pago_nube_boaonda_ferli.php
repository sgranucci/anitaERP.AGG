<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boaonda (store 6250382): el gateway API es pago-nube, pero el medio del canal es NUBE BOA
 * (caja 00000612 → 113101012). Ferli (3796054) sigue en PAGO NUBE (00000609).
 */
return new class extends Migration
{
    private const STORE_BOAONDA = '6250382';

    /** @var list<string> */
    private const KEYS_PAGO_NUBE = ['pago-nube', 'pago_nube', 'pago nube'];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('tiendanube_gateway_cuentacaja') || ! Schema::hasTable('cuentacaja')) {
            return;
        }

        $nubeBoaId = $this->cuentacajaIdNubeBoa();
        if ($nubeBoaId <= 0) {
            return;
        }

        DB::table('tiendanube_gateway_cuentacaja')
            ->where('store_id', self::STORE_BOAONDA)
            ->whereIn('gateway_key', self::KEYS_PAGO_NUBE)
            ->update([
                'cuentacaja_id' => $nubeBoaId,
                'updated_at' => now(),
            ]);

        // Unificar filas boa / nube boa a la misma caja canónica 00000612.
        DB::table('tiendanube_gateway_cuentacaja')
            ->where('store_id', self::STORE_BOAONDA)
            ->whereIn('gateway_key', ['boa', 'nube boa'])
            ->update([
                'cuentacaja_id' => $nubeBoaId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('tiendanube_gateway_cuentacaja') || ! Schema::hasTable('cuentacaja')) {
            return;
        }

        $pagoNubeId = (int) (DB::table('cuentacaja')
            ->whereIn('codigo', ['00000609', '609'])
            ->orderByRaw("CASE WHEN codigo = '00000609' THEN 0 ELSE 1 END")
            ->value('id') ?? 0);
        if ($pagoNubeId <= 0) {
            return;
        }

        DB::table('tiendanube_gateway_cuentacaja')
            ->where('store_id', self::STORE_BOAONDA)
            ->whereIn('gateway_key', self::KEYS_PAGO_NUBE)
            ->update([
                'cuentacaja_id' => $pagoNubeId,
                'updated_at' => now(),
            ]);
    }

    private function cuentacajaIdNubeBoa(): int
    {
        return (int) (DB::table('cuentacaja')
            ->whereIn('codigo', ['00000612', '612'])
            ->orderByRaw("CASE WHEN codigo = '00000612' THEN 0 ELSE 1 END")
            ->value('id') ?? 0);
    }
};
