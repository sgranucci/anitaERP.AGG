<?php

namespace App\Queries\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use App\Support\Ventas\GastronomiaVentaHoraReporteFiltros;
use Illuminate\Support\Facades\DB;

final class GastronomiaVentaHoraReporteQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object{jornada:string,hora:int,importe:float,cantidad_comprobantes:int}>
     */
    public function ventasAgrupadas(array $filtros): array
    {
        [$desde, $hasta] = GastronomiaVentaHoraReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $importe = GastronomiaVentaComprobanteSignoSupport::sqlTotalComprobante();
        $hora = SqlDialectSupport::hora('v.created_at');
        $jornada = SqlDialectSupport::fecha('v.fechajornada');

        $query = DB::table('venta as v')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('pv.empresa_id', (int) ($filtros['empresa_id'] ?? 0))
            ->selectRaw("{$jornada} as jornada")
            ->selectRaw("{$hora} as hora")
            ->selectRaw("SUM({$importe}) as importe")
            ->selectRaw('COUNT(DISTINCT v.id) as cantidad_comprobantes')
            ->groupBy(DB::raw($jornada), DB::raw($hora))
            ->orderBy('jornada')
            ->orderByRaw("CASE WHEN {$hora} >= 7 THEN {$hora} ELSE {$hora} + 24 END");

        if ($desde !== '') {
            $query->whereDate('v.fechajornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fechajornada', '<=', $hasta);
        }

        $horas = GastronomiaVentaHoraReporteFiltros::horasSeleccionadas($filtros);
        if ($horas !== []) {
            $query->whereIn(DB::raw($hora), $horas);
        }

        return $query->get()->map(static fn ($row): object => (object) [
            'jornada' => (string) $row->jornada,
            'hora' => (int) $row->hora,
            'importe' => round((float) $row->importe, 2),
            'cantidad_comprobantes' => (int) $row->cantidad_comprobantes,
        ])->all();
    }
}
