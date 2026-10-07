<?php

namespace App\Support\Ventas\FacturacionLocal;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de ventas por artículo (Facturación Local / Reportes Local).
 * Criterio de alcance: uno o varios puntos de venta (venta.puntoventa_id).
 */
final class FacturacionLocalVentasArticulosReporteFiltros
{
    public const MODO_ABIERTO_TALLE = 'abierto_talle';

    public const MODO_CERRADO = 'cerrado';

    /** @var list<array{valor:string,etiqueta:string}> */
    public const MODOS = [
        ['valor' => self::MODO_ABIERTO_TALLE, 'etiqueta' => 'Abierto por talle'],
        ['valor' => self::MODO_CERRADO, 'etiqueta' => 'Cerrado por artículo / combinación'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            trim((string) $request->input('fecha_desde', $request->input('desde', ''))),
            trim((string) $request->input('fecha_hasta', $request->input('hasta', ''))),
        );

        $modo = trim((string) $request->input('modo', self::MODO_ABIERTO_TALLE));
        if (! in_array($modo, [self::MODO_ABIERTO_TALLE, self::MODO_CERRADO], true)) {
            $modo = self::MODO_ABIERTO_TALLE;
        }

        return [
            'puntoventa_ids' => self::normalizarIds($request->input('puntoventa_id', [])),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'modo' => $modo,
            'incluir_costo' => $request->boolean('incluir_costo'),
        ];
    }

    /**
     * Acepta puntoventa_id=17 (histórico) y puntoventa_id[]=17&puntoventa_id[]=25.
     *
     * @return list<int>
     */
    public static function normalizarIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [$raw];
        }
        $out = [];
        foreach ($raw as $id) {
            if (is_array($id)) {
                continue;
            }
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function idsPuntoventa(array $filtros): array
    {
        return self::normalizarIds($filtros['puntoventa_ids'] ?? []);
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        if ($desde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = '';
        }
        if ($hasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = '';
        }
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
        if (self::idsPuntoventa($filtros) === []) {
            return false;
        }

        return ($filtros['fecha_desde'] ?? '') !== '' && ($filtros['fecha_hasta'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $ids = self::idsPuntoventa($filtros);
        if ($ids !== []) {
            $out['puntoventa_id'] = $ids;
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $out['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $out['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        $modo = (string) ($filtros['modo'] ?? self::MODO_ABIERTO_TALLE);
        if ($modo !== self::MODO_ABIERTO_TALLE) {
            $out['modo'] = $modo;
        }
        if (! empty($filtros['incluir_costo'])) {
            $out['incluir_costo'] = 1;
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
        if ($desde === '' && $hasta === '') {
            return '';
        }
        $fmt = static fn (string $ymd) => $ymd !== '' ? Carbon::parse($ymd)->format('d/m/Y') : '—';

        return 'Desde '.$fmt($desde).' hasta '.$fmt($hasta);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function etiquetaModo(array $filtros): string
    {
        $modo = (string) ($filtros['modo'] ?? self::MODO_ABIERTO_TALLE);
        foreach (self::MODOS as $op) {
            if ($op['valor'] === $modo) {
                return $op['etiqueta'];
            }
        }

        return self::MODOS[0]['etiqueta'];
    }

    public static function esAbiertoPorTalle(array $filtros): bool
    {
        return ((string) ($filtros['modo'] ?? self::MODO_ABIERTO_TALLE)) === self::MODO_ABIERTO_TALLE;
    }

    public static function incluirCosto(array $filtros): bool
    {
        return ! empty($filtros['incluir_costo']);
    }
}
