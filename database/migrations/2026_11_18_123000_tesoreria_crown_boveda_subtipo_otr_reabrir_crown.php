<?php

use App\Support\Caja\TesoreriaCrownBovedaVigenciaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reabre subtipo OTR («Otro») en OEGR y lo agrega a INGR del catálogo nuevo.
 * La UI/validación de subtipo_texto ya existía; el catálogo unificado lo había cerrado al 31/10/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_subtipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $ahora = now();
        $desde = TesoreriaCrownBovedaVigenciaSupport::CORTE_NUEVO_CATALOGO;

        $tipos = DB::table('tesoreria_crown_boveda_tipo')
            ->whereIn('codigo', [1, 14]) // INGR, OEGR
            ->get(['id', 'sala_id', 'codigo']);

        foreach ($tipos as $tipo) {
            $this->asegurarOtr(
                (int) $tipo->sala_id,
                (int) $tipo->id,
                (int) $tipo->codigo,
                $desde,
                $ahora
            );
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()
            || ! Schema::hasTable('tesoreria_crown_boveda_subtipo')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')) {
            return;
        }

        $ahora = now();
        $hasta = TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_HASTA;
        $corte = TesoreriaCrownBovedaVigenciaSupport::CORTE_NUEVO_CATALOGO;

        // OEGR OTR: vuelve a cerrar al 31/10 (estado previo al reopen).
        $oegrIds = DB::table('tesoreria_crown_boveda_tipo')->where('codigo', 14)->pluck('id');
        DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('tipo_id', $oegrIds)
            ->where('codigo', 'OTR')
            ->update([
                'vigente_hasta' => $hasta,
                'updated_at' => $ahora,
            ]);

        // INGR OTR creados por esta migración (desde = corte): se borran si no tienen uso.
        $ingrIds = DB::table('tesoreria_crown_boveda_tipo')->where('codigo', 1)->pluck('id');
        $otrIngr = DB::table('tesoreria_crown_boveda_subtipo')
            ->whereIn('tipo_id', $ingrIds)
            ->where('codigo', 'OTR')
            ->whereDate('vigente_desde', $corte)
            ->pluck('id');
        if ($otrIngr->isEmpty()) {
            return;
        }
        $enUso = DB::table('tesoreria_crown_boveda')
            ->whereIn('subtipo_id', $otrIngr)
            ->pluck('subtipo_id')
            ->unique();
        $borrar = $otrIngr->diff($enUso);
        if ($borrar->isNotEmpty()) {
            DB::table('tesoreria_crown_boveda_subtipo')->whereIn('id', $borrar)->delete();
        }
    }

    private function asegurarOtr(int $salaId, int $tipoId, int $codigoTipo, string $desde, $ahora): void
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
            'vigente_desde' => $codigoTipo === 14
                ? TesoreriaCrownBovedaVigenciaSupport::VIGENCIA_LEGADO_DESDE
                : $desde,
            'vigente_hasta' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
