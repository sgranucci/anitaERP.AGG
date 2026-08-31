<?php

namespace App\Support\Sueldos\Lsd;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Sueldos;

class LsdPeriodoWizardSupport
{
    /**
     * @return array{
     *   cobertura: array<string, mixed>,
     *   conceptos_exportado_at: ?string,
     *   liquidaciones: list<array<string, mixed>>,
     *   e_pendientes: list<string>,
     *   bloquea_mensual: bool
     * }
     */
    public static function para(int $empresaId, int $periodo): array
    {
        $anio = intdiv($periodo, 100);
        $mes = $periodo % 100;
        $liqs = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo_anio', $anio)
            ->where('periodo_mes', $mes)
            ->whereIn('estado', ['cerrada', 'contabilizada', 'pagada'])
            ->get();

        $presentadas = Lsd_Presentacion_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodo)
            ->get()
            ->groupBy('liquidacion_id');

        $filas = [];
        $ePendientes = [];
        foreach ($liqs as $l) {
            $tipoAfip = LsdTipoLiquidacionSupport::desdeTipoErp($l->tipo);
            $pres = $presentadas->get($l->id, collect());
            $sj = $pres->firstWhere('identificacion', 'SJ');
            $estadoLsd = $sj->estado ?? null;
            $fila = [
                'id' => (int) $l->id,
                'numero' => $l->numero,
                'descripcion' => (string) $l->descripcion,
                'tipo' => (string) $l->tipo,
                'tipo_label' => $l->tipoLabel(),
                'tipo_afip' => $tipoAfip,
                'orden' => LsdTipoLiquidacionSupport::pesoOrden($l->tipo),
                'recibos' => (int) $l->cantidad_recibos,
                'fecha_pago' => optional($l->fecha_pago)->format('Y-m-d'),
                'lsd_estado' => $estadoLsd,
                'presentada' => $estadoLsd === 'presentada',
                'generada' => $sj !== null,
            ];
            $filas[] = $fila;
            if ($tipoAfip === 'E' && ! $fila['generada'] && $fila['recibos'] > 0) {
                $ePendientes[] = '#'.$l->numero.' '.$l->descripcion;
            }
        }
        usort($filas, fn ($a, $b) => $a['orden'] <=> $b['orden'] ?: $a['id'] <=> $b['id']);

        return [
            'cobertura' => LsdConceptoCoberturaSupport::resumen(),
            'conceptos_exportado_at' => LsdConceptosExportMeta::exportadoAt(),
            'liquidaciones' => $filas,
            'e_pendientes' => $ePendientes,
            'bloquea_mensual' => $ePendientes !== [],
        ];
    }
}
