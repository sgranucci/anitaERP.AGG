<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rectificación Myriam: local Mercadolibre = PV sucursal 21 (no 23).
 * Full MELI sigue en 27 / depósito 27.
 */
return new class extends Migration
{
    private const LOCAL_CODIGO = '5';

    private const PV_CODIGO_ERRONEO = '00023';

    private const PV_CODIGO_CORRECTO = '00021';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta') || ! Schema::hasTable('puntoventa')) {
            return;
        }

        $localId = (int) (DB::table('local_venta')->where('codigo', self::LOCAL_CODIGO)->value('id') ?? 0);
        $pvCorrectoId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_CORRECTO)->value('id') ?? 0);
        if ($localId <= 0 || $pvCorrectoId <= 0) {
            return;
        }

        DB::table('local_venta')
            ->where('id', $localId)
            ->update([
                'puntoventa_id' => $pvCorrectoId,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('local_venta_puntoventa')) {
            $pvErroneoId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_ERRONEO)->value('id') ?? 0);
            $q = DB::table('local_venta_puntoventa')->where('local_venta_id', $localId);
            if ($pvErroneoId > 0) {
                $q->where('puntoventa_id', $pvErroneoId);
            }
            $q->update([
                'puntoventa_id' => $pvCorrectoId,
                'updated_at' => now(),
            ]);

            if (! DB::table('local_venta_puntoventa')
                ->where('local_venta_id', $localId)
                ->where('puntoventa_id', $pvCorrectoId)
                ->exists()) {
                DB::table('local_venta_puntoventa')->insert([
                    'local_venta_id' => $localId,
                    'puntoventa_id' => $pvCorrectoId,
                    'es_default' => 1,
                    'orden' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta') || ! Schema::hasTable('puntoventa')) {
            return;
        }

        $localId = (int) (DB::table('local_venta')->where('codigo', self::LOCAL_CODIGO)->value('id') ?? 0);
        $pv23Id = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_ERRONEO)->value('id') ?? 0);
        if ($localId <= 0 || $pv23Id <= 0) {
            return;
        }

        DB::table('local_venta')
            ->where('id', $localId)
            ->update([
                'puntoventa_id' => $pv23Id,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('local_venta_puntoventa')) {
            $pv21Id = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_CORRECTO)->value('id') ?? 0);
            $q = DB::table('local_venta_puntoventa')->where('local_venta_id', $localId);
            if ($pv21Id > 0) {
                $q->where('puntoventa_id', $pv21Id);
            }
            $q->update([
                'puntoventa_id' => $pv23Id,
                'updated_at' => now(),
            ]);
        }
    }
};
