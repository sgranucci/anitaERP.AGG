<?php

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

final class GastronomiaVentaHoraReporteFiltros
{
    public const HORA_DESDE_DEFAULT = 0;

    public const HORA_HASTA_DEFAULT = 24;

    /**
     * Orden operativo de gastronomía: 07→23 y 00→06.
     *
     * @var list<int>
     */
    public const HORAS_OPERATIVAS = [
        7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18,
        19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', '')),
            trim((string) $request->input('fecha_hasta', '')),
        );

        [$horaDesde, $horaHasta] = self::normalizarRangoHoras(
            $request->input('hora_desde', self::HORA_DESDE_DEFAULT),
            $request->input('hora_hasta', self::HORA_HASTA_DEFAULT),
        );

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'hora_desde' => $horaDesde,
            'hora_hasta' => $horaHasta,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{0:int,1:int}
     */
    public static function normalizarRangoHoras(mixed $desde, mixed $hasta): array
    {
        $horaDesde = is_numeric($desde) ? (int) $desde : self::HORA_DESDE_DEFAULT;
        $horaHasta = is_numeric($hasta) ? (int) $hasta : self::HORA_HASTA_DEFAULT;

        $horaDesde = max(0, min(23, $horaDesde));
        $horaHasta = max(0, min(24, $horaHasta));

        return [$horaDesde, $horaHasta];
    }

    /**
     * Horas visibles en el orden operativo, filtradas por el rango solicitado.
     * `hora_hasta = 24` incluye hasta las 23 inclusive.
     *
     * @return list<int>
     */
    public static function horasSeleccionadas(array $filtros): array
    {
        [$horaDesde, $horaHasta] = self::normalizarRangoHoras(
            $filtros['hora_desde'] ?? self::HORA_DESDE_DEFAULT,
            $filtros['hora_hasta'] ?? self::HORA_HASTA_DEFAULT,
        );

        $seleccionadas = [];
        foreach (self::HORAS_OPERATIVAS as $hora) {
            if (self::horaEnRango($hora, $horaDesde, $horaHasta)) {
                $seleccionadas[] = $hora;
            }
        }

        return $seleccionadas !== []
            ? $seleccionadas
            : self::HORAS_OPERATIVAS;
    }

    public static function horaEnRango(int $hora, int $horaDesde, int $horaHasta): bool
    {
        if ($horaHasta >= 24) {
            return $hora >= $horaDesde;
        }

        if ($horaDesde <= $horaHasta) {
            return $hora >= $horaDesde && $hora <= $horaHasta;
        }

        // Cruce de medianoche: ej. 22 → 06.
        return $hora >= $horaDesde || $hora <= $horaHasta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = [];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $query['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $query['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $query['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        [$horaDesde, $horaHasta] = self::normalizarRangoHoras(
            $filtros['hora_desde'] ?? self::HORA_DESDE_DEFAULT,
            $filtros['hora_hasta'] ?? self::HORA_HASTA_DEFAULT,
        );
        $query['hora_desde'] = $horaDesde;
        $query['hora_hasta'] = $horaHasta;

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde === '' && $hasta === '') {
            return '';
        }

        $formatear = static fn (string $fecha): string => $fecha !== ''
            ? Carbon::parse($fecha)->format('d/m/Y')
            : '—';

        return 'Desde '.$formatear($desde).' hasta '.$formatear($hasta)
            .' · Horas '.self::formatearRangoHorasTexto($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearRangoHorasTexto(array $filtros): string
    {
        [$horaDesde, $horaHasta] = self::normalizarRangoHoras(
            $filtros['hora_desde'] ?? self::HORA_DESDE_DEFAULT,
            $filtros['hora_hasta'] ?? self::HORA_HASTA_DEFAULT,
        );

        return sprintf('%02d–%02d', $horaDesde, $horaHasta);
    }
}
