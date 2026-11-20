<?php

use App\Support\Caja\TesoreriaCrownBovedaVigenciaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mauro: ICAM (27) no pide legajo; ICAM y ECAM (28) suman subtipo OTR («Otro»).
 *
 * ICAM tenía operacion=3 (Fallo de bóveda → referencia legajo). Pasa a 0
 * (Ingreso/egreso) como ECAM. OTR reutiliza el mismo código/UI de INGR/OEGR.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            return;
        }

        $ahora = now();
        $desde = TesoreriaCrownBovedaVigenciaSupport::CORTE_NUEVO_CATALOGO;

        DB::table('tesoreria_crown_boveda_tipo')
            ->where('codigo', 27) // ICAM
            ->where('operacion', '3')
            ->update([
                'operacion' => '0',
                'updated_at' => $ahora,
            ]);

        $tipos = DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', [27, 28]) // ICAM, ECAM
            ->get(['id', 'sala_id', 'codigo']);

        foreach ($tipos as $tipo) {
            $this->asegurarOtr(
                (int) $tipo->sala_id,
                (int) $tipo->id,
                $desde,
                $ahora
            );
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_subtipo')) {
            return;
        }

        $ahora = now();

        DB::table('tesoreria_crown_boveda_tipo')
            ->where('codigo', 27)
            ->where('operacion', '0')
            ->update([
                'operacion' => '3',
                'updated_at' => $ahora,
            ]);

        $tipoIds = DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', [27, 28])
            ->pluck('id');
        if ($tipoIds->isEmpty()) {
            return;
        }

        $otrIds = DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('tipo_id', $tipoIds)
            ->where('codigo', 'OTR')
            ->pluck('id');
        if ($otrIds->isEmpty()) {
            return;
        }

        $enUso = DB::table('tesoreria_crown_boveda')
            ->whereIn('subtipo_id', $otrIds)
            ->pluck('subtipo_id')
            ->unique();
        $borrar = $otrIds->diff($enUso);
        if ($borrar->isNotEmpty()) {
            DB::table('tesoreria_crown_boveda_subtipo')->whereIn('id', $borrar)->delete();
        }
    }

    private function asegurarOtr(int $salaId, int $tipoId, string $desde, $ahora): void
    {
        $existe = DB::table('tesoreria_crown_boveda_subtipo')
            ->where('tipo_id', $tipoId)
            ->where('codigo', 'OTR')
            ->first();

        if ($existe) {
            DB::table('tesoreria_crown_boveda_subtipo')->where('id', $existe->id)->update([
                'nombre' => 'Otro (detalle en texto)',
                'activo' => true,
                'mueve_billetaje' => true,
                'signo' => null,
                'vigente_hasta' => null,
                'updated_at' => $ahora,
            ]);

            return;
        }

        DB::table('tesoreria_crown_boveda_subtipo')->insert([
            'sala_id' => $salaId,
            'tipo_id' => $tipoId,
            'codigo' => 'OTR',
            'nombre' => 'Otro (detalle en texto)',
            'activo' => true,
            'mueve_billetaje' => true,
            'signo' => null,
            'vigente_desde' => $desde,
            'vigente_hasta' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
