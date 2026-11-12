<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la cuenta de conciliación RN: 130212000 (crédito / sufridas) → 330301000
 * (RET. ING.BRUT R.N., pasivo de retenciones practicadas).
 */
return new class extends Migration
{
    private const JURISDICCION_RN = '916';

    private const CUENTA_ERRONEA = '130212000';

    private const CUENTA_CORRECTA = '330301000';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('iibb_presentacion_config_cuenta')) {
            return;
        }

        $configIds = DB::table('iibb_presentacion_config as c')
            ->join('provincia as p', 'p.id', '=', 'c.provincia_id')
            ->where('p.jurisdiccion', self::JURISDICCION_RN)
            ->where('c.tipo', 'retenciones')
            ->pluck('c.id');
        if ($configIds->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($configIds as $configId) {
            $links = DB::table('iibb_presentacion_config_cuenta as cc')
                ->join('cuentacontable as cu', 'cu.id', '=', 'cc.cuentacontable_id')
                ->where('cc.iibb_presentacion_config_id', $configId)
                ->where('cu.codigo', self::CUENTA_ERRONEA)
                ->select('cc.id', 'cc.empresa_id')
                ->get();

            foreach ($links as $link) {
                $cuentaNueva = DB::table('cuentacontable')
                    ->where('empresa_id', $link->empresa_id)
                    ->where('codigo', self::CUENTA_CORRECTA)
                    ->first(['id', 'nombre']);

                DB::table('iibb_presentacion_config_cuenta')->where('id', $link->id)->delete();

                if ($cuentaNueva === null) {
                    continue;
                }
                $nombre = mb_strtoupper((string) ($cuentaNueva->nombre ?? ''));
                if (
                    ! str_contains($nombre, 'ING')
                    || (! str_contains($nombre, 'BRUT') && ! str_contains($nombre, 'IIBB'))
                ) {
                    continue;
                }

                $ya = DB::table('iibb_presentacion_config_cuenta')
                    ->where('iibb_presentacion_config_id', $configId)
                    ->where('empresa_id', $link->empresa_id)
                    ->where('cuentacontable_id', $cuentaNueva->id)
                    ->exists();
                if ($ya) {
                    continue;
                }

                DB::table('iibb_presentacion_config_cuenta')->insert([
                    'iibb_presentacion_config_id' => $configId,
                    'empresa_id' => $link->empresa_id,
                    'cuentacontable_id' => $cuentaNueva->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('iibb_presentacion_config')->where('id', $configId)->update([
                'descripcion' => 'p-ingbruto Crown, aplicativo Rentas Río Negro. Retenciones compras. Conciliación cuenta 330301000.',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No revierte a 130212000: era incorrecta.
    }
};
