<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

use Illuminate\Http\Request;

final class CanonEntidadesListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
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

        [$desde, $hasta] = self::rangoMes($periodo);

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'periodo' => $periodo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function rangoMes(string $periodoYm): array
    {
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $anio = (int) date('Y');
            $mes = (int) date('m');
        }
        $ultimo = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));

        return [
            sprintf('%04d-%02d-01', $anio, $mes),
            sprintf('%04d-%02d-%02d', $anio, $mes, $ultimo),
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
        $periodo = (string) ($filtros['periodo'] ?? '');
        $anio = (int) substr($periodo, 0, 4);
        $mes = (int) substr($periodo, 4, 2);
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $etiqueta = ($meses[$mes] ?? '').($anio > 0 ? ' '.$anio : '');
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return trim($etiqueta);
        }
        $fmt = static fn (string $iso) => date('d/m/Y', strtotime($iso));

        return trim($etiqueta).' ('.$fmt($desde).' — '.$fmt($hasta).')';
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
        return generaKey('canon_entidades_resultado_v1_'.self::firma($filtros));
    }
}
