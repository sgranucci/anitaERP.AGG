<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local Mercadolibre: PV sucursal 23 (antes 21), depósito 10.
 * Pedido operativo 25/sep/2026.
 */
return new class extends Migration
{
    private const LOCAL_CODIGO = '5';

    private const PV_CODIGO_ANTERIOR = '00021';

    private const PV_CODIGO_NUEVO = '00023';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta') || ! Schema::hasTable('puntoventa')) {
            return;
        }

        $localId = (int) (DB::table('local_venta')->where('codigo', self::LOCAL_CODIGO)->value('id') ?? 0);
        $pvNuevoId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_NUEVO)->value('id') ?? 0);
        if ($localId <= 0 || $pvNuevoId <= 0) {
            return;
        }

        DB::table('local_venta')
            ->where('id', $localId)
            ->update([
                'puntoventa_id' => $pvNuevoId,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('local_venta_puntoventa')) {
            $pvAnteriorId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_ANTERIOR)->value('id') ?? 0);
            $q = DB::table('local_venta_puntoventa')->where('local_venta_id', $localId);
            if ($pvAnteriorId > 0) {
                $q->where('puntoventa_id', $pvAnteriorId);
            }
            $q->update([
                'puntoventa_id' => $pvNuevoId,
                'updated_at' => now(),
            ]);

            if (! DB::table('local_venta_puntoventa')
                ->where('local_venta_id', $localId)
                ->where('puntoventa_id', $pvNuevoId)
                ->exists()) {
                DB::table('local_venta_puntoventa')->insert([
                    'local_venta_id' => $localId,
                    'puntoventa_id' => $pvNuevoId,
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
        $pvAnteriorId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_ANTERIOR)->value('id') ?? 0);
        if ($localId <= 0 || $pvAnteriorId <= 0) {
            return;
        }

        DB::table('local_venta')
            ->where('id', $localId)
            ->update([
                'puntoventa_id' => $pvAnteriorId,
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('local_venta_puntoventa')) {
            $pvNuevoId = (int) (DB::table('puntoventa')->where('codigo', self::PV_CODIGO_NUEVO)->value('id') ?? 0);
            $q = DB::table('local_venta_puntoventa')->where('local_venta_id', $localId);
            if ($pvNuevoId > 0) {
                $q->where('puntoventa_id', $pvNuevoId);
            }
            $q->update([
                'puntoventa_id' => $pvAnteriorId,
                'updated_at' => now(),
            ]);
        }
    }
};
