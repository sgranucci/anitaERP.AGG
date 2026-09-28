<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

/**
 * Filtros del informe de movimientos de stock por artículo (l-stkmov.c, orden x artículo).
 */
final class MovimientoStockArticuloReporteFiltros
{
    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = (string) $request->input('modo', self::MODO_MOVIMIENTOS);

        return [
            'desde_sku' => self::texto($request->input('desde_sku')),
            'hasta_sku' => self::texto($request->input('hasta_sku')),
            'desde_combinacion' => self::texto($request->input('desde_combinacion')),
            'hasta_combinacion' => self::texto($request->input('hasta_combinacion')),
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'desde_deposito' => self::texto($request->input('desde_deposito')),
            'hasta_deposito' => self::texto($request->input('hasta_deposito')),
            'tipos' => self::texto($request->input('tipos')),
            'modo' => $modo === self::MODO_TOTALES ? self::MODO_TOTALES : self::MODO_MOVIMIENTOS,
            'total_dia' => $request->boolean('total_dia'),
            'salto_articulo' => $request->boolean('salto_articulo'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'desde_sku' => $filtros['desde_sku'] ?? null,
            'hasta_sku' => $filtros['hasta_sku'] ?? null,
            'desde_combinacion' => $filtros['desde_combinacion'] ?? null,
            'hasta_combinacion' => $filtros['hasta_combinacion'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'desde_deposito' => $filtros['desde_deposito'] ?? null,
            'hasta_deposito' => $filtros['hasta_deposito'] ?? null,
            'tipos' => $filtros['tipos'] ?? null,
            'modo' => ($filtros['modo'] ?? self::MODO_MOVIMIENTOS) === self::MODO_TOTALES
                ? self::MODO_TOTALES
                : null,
            'total_dia' => ! empty($filtros['total_dia']) ? '1' : null,
            'salto_articulo' => ! empty($filtros['salto_articulo']) ? '1' : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = ['x código de artículo'];
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Desde '.self::fechaHumana($desde).' hasta '.self::fechaHumana($hasta);
        }
        if (($filtros['modo'] ?? '') === self::MODO_TOTALES) {
            $partes[] = 'solo totales';
        }
        if (! empty($filtros['total_dia'])) {
            $partes[] = 'con total por día';
        }
        $tipos = trim((string) ($filtros['tipos'] ?? ''));
        if ($tipos !== '') {
            $partes[] = 'comprobantes '.$tipos;
        }

        return implode(' · ', $partes);
    }

    public static function fechaHumana(?string $ymd): string
    {
        $ymd = trim((string) $ymd);
        if ($ymd === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '…';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }

    private static function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }

    private static function fecha(mixed $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return '';
    }
}
