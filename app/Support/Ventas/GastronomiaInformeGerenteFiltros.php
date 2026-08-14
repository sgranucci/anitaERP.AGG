<?php

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe gerente gastronomía (empresa + rango de fechas de jornada).
 */
final class GastronomiaInformeGerenteFiltros
{
    /**
     * @return array{empresa_id:int,fecha_desde:string,fecha_hasta:string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);

        $desde = trim((string) $request->input('fecha_desde', ''));
        $hasta = trim((string) $request->input('fecha_hasta', ''));

        // Compatibilidad con URL/favoritos previos (un solo día).
        $legacy = trim((string) $request->input('fecha_jornada', ''));
        if ($legacy !== '') {
            if ($desde === '') {
                $desde = $legacy;
            }
            if ($hasta === '') {
                $hasta = $legacy;
            }
        }

        [$desde, $hasta] = self::normalizarRangoFechas($desde, $hasta);

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = self::normalizarFecha($desde);
        $hasta = self::normalizarFecha($hasta);

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    public static function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
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
        $out = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $out['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $out['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function esUnSoloDia(array $filtros): bool
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));

        return $desde !== '' && $desde === $hasta;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde === '' || $hasta === '') {
            return '';
        }

        try {
            $d = Carbon::parse($desde)->format('d/m/Y');
            $h = Carbon::parse($hasta)->format('d/m/Y');
        } catch (\Throwable) {
            return $desde.' — '.$hasta;
        }

        return $desde === $hasta ? $d : $d.' — '.$h;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return sha1(json_encode(self::paraQueryString($filtros), JSON_UNESCAPED_UNICODE) ?: '');
    }
}
