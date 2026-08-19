<?php

declare(strict_types=1);

namespace App\Support\Contable\Suss;

use Illuminate\Http\Request;

final class SussListadoFiltros
{
    /** Liquidación: 0=rango libre, 1=1ra quincena, 2=2da quincena, 3=mes completo. */
    /** @var array<int, string> */
    public const LIQUIDACIONES = [
        1 => '1ra quincena',
        2 => '2da quincena',
        3 => 'Mes completo',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $liquidacion = max(0, min(3, (int) $request->input('liquidacion', 1)));
        // Preferir selects mes/año; aceptar también periodo (YYYYMM) o periodo_mes (YYYY-MM).
        $anioSel = (int) $request->input('periodo_anio', 0);
        $mesSel = preg_replace('/\D/', '', (string) $request->input('periodo_mes_num', '')) ?? '';
        if ($anioSel >= 2000 && $anioSel <= 2100 && strlen($mesSel) >= 1 && strlen($mesSel) <= 2) {
            $mesNum = max(1, min(12, (int) $mesSel));
            $periodo = sprintf('%04d%02d', $anioSel, $mesNum);
        } else {
            $periodoRaw = (string) $request->input('periodo', $request->input('periodo_mes', date('Ym')));
            $periodo = preg_replace('/\D/', '', $periodoRaw) ?? '';
            if (strlen($periodo) !== 6) {
                $periodo = date('Ym');
            }
        }

        [$desde, $hasta] = self::rangoDesdeLiquidacion($periodo, $liquidacion);
        if ($liquidacion === 0) {
            $desde = (string) $request->input('fecha_desde', $desde);
            $hasta = (string) $request->input('fecha_hasta', $hasta);
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'periodo' => $periodo,
            'liquidacion' => $liquidacion,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'conciliar_contable' => $request->boolean('conciliar_contable', true),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function rangoDesdeLiquidacion(string $periodoYm, int $liquidacion): array
    {
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $anio = (int) date('Y');
            $mes = (int) date('m');
        }
        $ultimo = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
        $desdeDia = 1;
        $hastaDia = $ultimo;

        if ($liquidacion === 1) {
            $hastaDia = 15;
        } elseif ($liquidacion === 2) {
            $desdeDia = 16;
        }

        return [
            sprintf('%04d-%02d-%02d', $anio, $mes, $desdeDia),
            sprintf('%04d-%02d-%02d', $anio, $mes, $hastaDia),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'periodo' => (string) ($filtros['periodo'] ?? ''),
            'liquidacion' => (int) ($filtros['liquidacion'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
        ];
        if (! empty($filtros['conciliar_contable'])) {
            $out['conciliar_contable'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['fecha_desde'] ?? '') !== ''
            && ($filtros['fecha_hasta'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return '';
        }
        $liq = (int) ($filtros['liquidacion'] ?? 0);
        $etiqueta = self::LIQUIDACIONES[$liq] ?? '';
        $fmt = static fn (string $iso) => date('d/m/Y', strtotime($iso));
        $rango = $fmt($desde).' — '.$fmt($hasta);

        return $etiqueta !== '' ? $etiqueta.' ('.$rango.')' : $rango;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return md5(json_encode([
            'v' => 2,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'conciliar_contable' => ! empty($filtros['conciliar_contable']) ? 1 : 0,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function claveCacheResultado(array $filtros): string
    {
        return generaKey('suss_resultado_v2_'.self::firma($filtros));
    }
}
