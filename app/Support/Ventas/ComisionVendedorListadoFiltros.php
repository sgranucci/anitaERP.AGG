<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros compartidos de los reportes de comisiones de vendedores.
 */
final class ComisionVendedorListadoFiltros
{
    public const MODO_DETALLE = 'detalle';

    public const MODO_RESUMEN = 'resumen';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', '')),
            trim((string) $request->input('fecha_hasta', '')),
        );

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'vendedor_id' => self::enteroOpcional($request->input('vendedor_id')),
            'vendedor_codigo' => trim((string) $request->input('vendedor_codigo', '')),
            'vendedor_nombre' => trim((string) $request->input('vendedor_nombre', '')),
            'tipotransaccion_id' => self::enteroOpcional($request->input('tipotransaccion_id')),
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
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $out['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $out['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if ((int) ($filtros['vendedor_id'] ?? 0) > 0) {
            $out['vendedor_id'] = (int) $filtros['vendedor_id'];
            $out['vendedor_codigo'] = (string) ($filtros['vendedor_codigo'] ?? '');
            $out['vendedor_nombre'] = (string) ($filtros['vendedor_nombre'] ?? '');
        }
        if ((int) ($filtros['tipotransaccion_id'] ?? 0) > 0) {
            $out['tipotransaccion_id'] = (int) $filtros['tipotransaccion_id'];
        }

        return $out;
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

        return Carbon::parse($desde)->format('d/m/Y').' — '.Carbon::parse($hasta)->format('d/m/Y');
    }

    private static function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }
}
