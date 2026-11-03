<?php

namespace App\Support\Contable;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte EFE (Estado de flujo mensual)
 * y de la posición financiera (rango de meses optativo).
 */
class EfeMensualListadoFiltros
{
    /** Tope de meses a generar en un solo pedido (posición financiera). */
    public const MAX_MESES_RANGO = 12;

    /**
     * @return array{
     *   empresa_id: int,
     *   moneda_id: int,
     *   mes: int,
     *   anio: int,
     *   mes_hasta: int,
     *   anio_hasta: int,
     *   solo_moneda_origen: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $mes = max(1, min(12, (int) $request->input(
            'mes_desde',
            $request->input('mes', (int) date('n'))
        )));
        $anio = max(2000, min(2100, (int) $request->input(
            'anio_desde',
            $request->input('anio', (int) date('Y'))
        )));

        // Hasta optativo: si no viene, se asume el mismo mes del desde.
        $tieneHasta = $request->filled('mes_hasta') || $request->filled('anio_hasta');
        $mesHasta = $tieneHasta
            ? max(1, min(12, (int) $request->input('mes_hasta', $mes)))
            : $mes;
        $anioHasta = $tieneHasta
            ? max(2000, min(2100, (int) $request->input('anio_hasta', $anio)))
            : $anio;

        $desdeYm = ($anio * 100) + $mes;
        $hastaYm = ($anioHasta * 100) + $mesHasta;
        if ($hastaYm < $desdeYm) {
            $mesHasta = $mes;
            $anioHasta = $anio;
        }

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'mes' => $mes,
            'anio' => $anio,
            'mes_hasta' => $mesHasta,
            'anio_hasta' => $anioHasta,
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && (int) ($filtros['mes'] ?? 0) > 0
            && (int) ($filtros['anio'] ?? 0) > 0;
    }

    public static function esRango(array $filtros): bool
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        $mesHasta = (int) ($filtros['mes_hasta'] ?? $mes);
        $anioHasta = (int) ($filtros['anio_hasta'] ?? $anio);

        return (($anioHasta * 100) + $mesHasta) !== (($anio * 100) + $mes);
    }

    /**
     * Lista de períodos mensuales desde..hasta (inclusive), capada a MAX_MESES_RANGO.
     *
     * @return list<array{mes: int, anio: int}>
     */
    public static function periodosMensuales(array $filtros): array
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($mes <= 0 || $anio <= 0) {
            return [];
        }

        $mesHasta = (int) ($filtros['mes_hasta'] ?? $mes);
        $anioHasta = (int) ($filtros['anio_hasta'] ?? $anio);

        $cursor = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $fin = Carbon::createFromDate($anioHasta, $mesHasta, 1)->startOfMonth();
        if ($fin->lt($cursor)) {
            $fin = $cursor->copy();
        }

        $periodos = [];
        while ($cursor->lte($fin) && count($periodos) < self::MAX_MESES_RANGO) {
            $periodos[] = [
                'mes' => (int) $cursor->month,
                'anio' => (int) $cursor->year,
            ];
            $cursor->addMonth();
        }

        return $periodos;
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        $mesHasta = (int) ($filtros['mes_hasta'] ?? $mes);
        $anioHasta = (int) ($filtros['anio_hasta'] ?? $anio);

        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'mes' => $mes,
            'anio' => $anio,
            'mes_desde' => $mes,
            'anio_desde' => $anio,
        ];

        if (self::esRango($filtros)) {
            $out['mes_hasta'] = $mesHasta;
            $out['anio_hasta'] = $anioHasta;
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        return array_filter($out, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosParaMayorConcepto(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => 'mes',
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
            'agrupacion_resumen' => 'concepto_cuenta',
        ];
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode([
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
            'mes_hasta' => (int) ($filtros['mes_hasta'] ?? ($filtros['mes'] ?? 0)),
            'anio_hasta' => (int) ($filtros['anio_hasta'] ?? ($filtros['anio'] ?? 0)),
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
        ]));
    }
}
