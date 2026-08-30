<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de ventas por concepto de mostrador.
 */
final class VentasPorConceptoListadoFiltros
{
    public const AGRUPAR_CONCEPTO = 'concepto';

    public const AGRUPAR_CUENTA = 'cuenta';

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
            'concepto_venta_id' => self::enteroOpcional($request->input('concepto_venta_id')),
            'concepto_codigo' => trim((string) $request->input('concepto_codigo', '')),
            'concepto_nombre' => trim((string) $request->input('concepto_nombre', '')),
            'tipotransaccion_id' => self::enteroOpcional($request->input('tipotransaccion_id')),
            'agrupar_por' => self::normalizarAgruparPor($request->input('agrupar_por')),
        ];
    }

    public static function normalizarAgruparPor(mixed $valor): string
    {
        return (string) $valor === self::AGRUPAR_CUENTA
            ? self::AGRUPAR_CUENTA
            : self::AGRUPAR_CONCEPTO;
    }

    public static function agrupaPorCuenta(array $filtros): bool
    {
        return ($filtros['agrupar_por'] ?? self::AGRUPAR_CONCEPTO) === self::AGRUPAR_CUENTA;
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
        if ((int) ($filtros['concepto_venta_id'] ?? 0) > 0) {
            $out['concepto_venta_id'] = (int) $filtros['concepto_venta_id'];
            $out['concepto_codigo'] = (string) ($filtros['concepto_codigo'] ?? '');
            $out['concepto_nombre'] = (string) ($filtros['concepto_nombre'] ?? '');
        }
        if ((int) ($filtros['tipotransaccion_id'] ?? 0) > 0) {
            $out['tipotransaccion_id'] = (int) $filtros['tipotransaccion_id'];
        }
        $out['agrupar_por'] = self::agrupaPorCuenta($filtros)
            ? self::AGRUPAR_CUENTA
            : self::AGRUPAR_CONCEPTO;

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

    private static function enteroOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }
}
