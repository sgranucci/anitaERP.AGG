<?php

namespace App\Support\Ventas;

use App\Models\Stock\Tipoarticulo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de insumos vendidos gastronomía por tipo de artículo y día.
 */
class GastronomiaInsumosTipoarticuloReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            $legacyIds = collect($request->input('empresa_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values();
            if ($legacyIds->isNotEmpty()) {
                $empresaId = (int) $legacyIds->first();
            }
        }

        $tipoarticuloId = (int) $request->input('tipoarticulo_id', 0);
        if ($tipoarticuloId <= 0) {
            $tipoarticuloId = (int) (self::tipoarticuloDefaultId() ?? 0);
        }

        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', '')),
            trim((string) $request->input('fecha_hasta', '')),
        );

        return [
            'empresa_id' => $empresaId,
            'tipoarticulo_id' => $tipoarticuloId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return false;
        }

        if ((int) ($filtros['tipoarticulo_id'] ?? 0) <= 0) {
            return false;
        }

        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = trim($fechaDesde);
        $hasta = trim($fechaHasta);

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    /**
     * @return list<array{ymd: string, label: string}>
     */
    public static function columnasDias(string $fechaDesde, string $fechaHasta): array
    {
        if ($fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $periodo = CarbonPeriod::create(
            Carbon::parse($fechaDesde)->startOfDay(),
            Carbon::parse($fechaHasta)->startOfDay(),
        );

        $columnas = [];
        foreach ($periodo as $fecha) {
            $ymd = $fecha->format('Y-m-d');
            $columnas[] = [
                'ymd' => $ymd,
                'label' => $fecha->format('d/m'),
            ];
        }

        return $columnas;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'consultar' => 1,
            'tipoarticulo_id' => (int) ($filtros['tipoarticulo_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
        ];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde === '' || $hasta === '') {
            return '';
        }

        $fmtDesde = Carbon::parse($desde)->format('d/m/Y');
        $fmtHasta = Carbon::parse($hasta)->format('d/m/Y');

        return $desde === $hasta ? $fmtDesde : $fmtDesde.' — '.$fmtHasta;
    }

    public static function tipoarticuloDefaultId(): ?int
    {
        $nombre = strtoupper((string) config('facturacion.IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO'));
        if ($nombre === '') {
            return null;
        }

        $id = Tipoarticulo::query()->whereRaw('UPPER(TRIM(nombre)) = ?', [$nombre])->value('id');

        return $id !== null ? (int) $id : null;
    }
}
