<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonMunicipal;

use Illuminate\Http\Request;

final class CanonMunicipalListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $periodicidad = null): array
    {
        $anioSel = (int) $request->input('periodo_anio', 0);
        $mesSel = preg_replace('/\D/', '', (string) $request->input('periodo_mes_num', '')) ?? '';
        if ($anioSel >= 2000 && $anioSel <= 2100 && strlen($mesSel) >= 1 && strlen($mesSel) <= 2) {
            $mesNum = max(1, min(12, (int) $mesSel));
            $periodo = sprintf('%04d%02d', $anioSel, $mesNum);
        } else {
            $periodoRaw = (string) $request->input('periodo', date('Ym'));
            $periodo = preg_replace('/\D/', '', $periodoRaw) ?? '';
            if (strlen($periodo) !== 6) {
                $periodo = date('Ym');
            }
        }

        $periodicidad = $periodicidad ?? (string) $request->input('periodicidad', 'semanal');
        if (! in_array($periodicidad, ['semanal', 'quincenal'], true)) {
            $periodicidad = 'semanal';
        }

        $liquidacion = max(1, (int) $request->input('liquidacion', 1));
        [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango(
            $periodicidad,
            $periodo,
            $liquidacion,
        );

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'periodo' => $periodo,
            'periodicidad' => $periodicidad,
            'liquidacion' => $liquidacion,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'periodo' => (string) ($filtros['periodo'] ?? ''),
            'periodicidad' => (string) ($filtros['periodicidad'] ?? 'semanal'),
            'liquidacion' => (int) ($filtros['liquidacion'] ?? 1),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
        ];
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
        $fmt = static fn (string $iso) => date('d/m/Y', strtotime($iso));
        $periodicidad = (string) ($filtros['periodicidad'] ?? '');
        $liq = (int) ($filtros['liquidacion'] ?? 0);
        if ($periodicidad === 'quincenal') {
            $etiq = $liq === 2 ? '2da quincena' : '1ra quincena';

            return $etiq.' ('.$fmt($desde).' — '.$fmt($hasta).')';
        }

        return 'Semana '.$liq.' ('.$fmt($desde).' — '.$fmt($hasta).')';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return md5(json_encode([
            'v' => 1,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function claveCacheResultado(array $filtros): string
    {
        return generaKey('canon_municipal_resultado_v1_'.self::firma($filtros));
    }
}
