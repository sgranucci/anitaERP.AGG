<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Illuminate\Http\Request;

final class IvaVentasListadoFiltros
{
    public const ORDEN_FECHA = 'fecha';

    public const ORDEN_FECHA_JORNADA = 'fechajornada';

    public const ORDENES = [
        self::ORDEN_FECHA => 'Fecha de movimiento',
        self::ORDEN_FECHA_JORNADA => 'Fecha de jornada',
    ];

    public const SUBDIARIO_VENTAS_A = 'VENTAS_A';

    public const SUBDIARIO_VENTAS_B = 'VENTAS_B';

    public const SUBDIARIO_VENTAS_A_B = 'VENTAS_A_B';

    public const SUBDIARIOS = [
        self::SUBDIARIO_VENTAS_A => 'Ventas A (letra A y C)',
        self::SUBDIARIO_VENTAS_B => 'Ventas B (consumidor final)',
        self::SUBDIARIO_VENTAS_A_B => 'Ventas A y B',
    ];

  /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $orden = trim((string) $request->input('orden_fecha', self::ORDEN_FECHA_JORNADA));
        if (! array_key_exists($orden, self::ORDENES)) {
            $orden = self::ORDEN_FECHA_JORNADA;
        }

        $subdiario = strtoupper(trim((string) $request->input('subdiario', self::SUBDIARIO_VENTAS_B)));
        if (! array_key_exists($subdiario, self::SUBDIARIOS)) {
            $subdiario = self::SUBDIARIO_VENTAS_B;
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $monedaId = (int) $request->input('moneda_id', 1);

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', date('Y-m-01'))),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', date('Y-m-d'))),
            'orden_fecha' => $orden,
            'subdiario' => $subdiario,
            'clasificar_por_host' => $request->boolean('clasificar_por_host'),
            'conciliar_contable' => $request->boolean('consultar')
                ? $request->boolean('conciliar_contable', true)
                : true,
            'solo_moneda_origen' => $request->boolean('consultar')
                ? $request->boolean('solo_moneda_origen')
                : true,
            'moneda_id' => $monedaId > 0 ? $monedaId : 1,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
            'orden_fecha' => $filtros['orden_fecha'] ?? self::ORDEN_FECHA_JORNADA,
            'subdiario' => $filtros['subdiario'] ?? self::SUBDIARIO_VENTAS_B,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
        ];

        if (! empty($filtros['clasificar_por_host'])) {
            $out['clasificar_por_host'] = 1;
        }

        $out['conciliar_contable'] = empty($filtros['conciliar_contable']) ? 0 : 1;
        $out['solo_moneda_origen'] = empty($filtros['solo_moneda_origen']) ? 0 : 1;

        return $out;
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = $filtros['fecha_desde'] ?? '';
        $hasta = $filtros['fecha_hasta'] ?? '';
        if ($desde === '' || $hasta === '') {
            return '';
        }

        return date('d/m/Y', strtotime($desde)).' — '.date('d/m/Y', strtotime($hasta));
    }

    public static function formatearOrdenTexto(array $filtros): string
    {
        $orden = $filtros['orden_fecha'] ?? self::ORDEN_FECHA;

        return self::ORDENES[$orden] ?? $orden;
    }

    public static function formatearSubdiarioTexto(array $filtros): string
    {
        $sub = $filtros['subdiario'] ?? self::SUBDIARIO_VENTAS_B;

        return self::SUBDIARIOS[$sub] ?? $sub;
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros)));
    }

    public static function pasaSubdiario(string $letra, string $subdiario): bool
    {
        $l = strtoupper(trim($letra));

        return match ($subdiario) {
            self::SUBDIARIO_VENTAS_A => $l === 'A' || $l === 'C',
            self::SUBDIARIO_VENTAS_B => $l === 'B',
            self::SUBDIARIO_VENTAS_A_B => in_array($l, ['A', 'B', 'C'], true),
            default => true,
        };
    }
}
