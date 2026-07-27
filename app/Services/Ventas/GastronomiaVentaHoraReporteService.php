<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\GastronomiaVentaHoraReporteQuery;
use App\Support\Ventas\GastronomiaVentaHoraReporteFiltros;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;

final class GastronomiaVentaHoraReporteService
{
    /** @var list<int> */
    public const HORAS = GastronomiaVentaHoraReporteFiltros::HORAS_OPERATIVAS;

    public function __construct(
        private readonly GastronomiaVentaHoraReporteQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $horas = GastronomiaVentaHoraReporteFiltros::horasSeleccionadas($filtros);
        $cantidadHoras = max(1, count($horas));
        $agregados = $this->query->ventasAgrupadas($filtros);
        $porJornadaHora = [];

        foreach ($agregados as $agregado) {
            $porJornadaHora[$agregado->jornada][$agregado->hora] = [
                'importe' => (float) $agregado->importe,
                'comprobantes' => (int) $agregado->cantidad_comprobantes,
            ];
        }

        [$desde, $hasta] = GastronomiaVentaHoraReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $totalesHora = array_fill_keys($horas, 0.0);
        $filas = [];
        $totalGeneral = 0.0;
        $cantidadComprobantes = 0;

        if ($desde !== '' && $hasta !== '') {
            foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
                $jornada = $fecha->format('Y-m-d');
                $importes = [];
                $totalDia = 0.0;
                $comprobantesDia = 0;

                foreach ($horas as $hora) {
                    $celda = $porJornadaHora[$jornada][$hora] ?? ['importe' => 0.0, 'comprobantes' => 0];
                    $importe = round((float) $celda['importe'], 2);
                    $importes[$hora] = $importe;
                    $totalDia += $importe;
                    $comprobantesDia += (int) $celda['comprobantes'];
                    $totalesHora[$hora] += $importe;
                }

                $totalDia = round($totalDia, 2);
                $totalGeneral += $totalDia;
                $cantidadComprobantes += $comprobantesDia;

                $filas[] = [
                    'fecha' => $jornada,
                    'dia' => Carbon::parse($jornada)->locale('es')->isoFormat('ddd'),
                    'importes' => $importes,
                    'total' => $totalDia,
                    'promedio' => round($totalDia / $cantidadHoras, 2),
                    'comprobantes' => $comprobantesDia,
                ];
            }
        }

        $totalGeneral = round($totalGeneral, 2);
        $cantidadDias = count($filas);

        return [
            'horas' => $horas,
            'filas' => $filas,
            'totales_hora' => array_map(static fn ($importe): float => round((float) $importe, 2), $totalesHora),
            'total_general' => $totalGeneral,
            'promedio_hora' => round($totalGeneral / max(1, $cantidadDias * $cantidadHoras), 2),
            'cantidad_dias' => $cantidadDias,
            'cantidad_horas' => count($horas),
            'cantidad_comprobantes' => $cantidadComprobantes,
            'periodo_texto' => GastronomiaVentaHoraReporteFiltros::formatearPeriodoTexto($filtros),
            'rango_horas_texto' => GastronomiaVentaHoraReporteFiltros::formatearRangoHorasTexto($filtros),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        return new LengthAwarePaginator(
            array_slice($filas, ($page - 1) * $perPage, $perPage),
            count($filas),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }
}
