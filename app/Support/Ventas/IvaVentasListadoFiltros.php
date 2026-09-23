<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Support\Ventas\IvaVentas\IvaVentasFeaturesSupport;
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
        self::SUBDIARIO_VENTAS_A_B => 'Ventas A y B (recomendado)',
        self::SUBDIARIO_VENTAS_A => 'Ventas A (letra A y C)',
        self::SUBDIARIO_VENTAS_B => 'Ventas B (consumidor final)',
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

        $subdiario = strtoupper(trim((string) $request->input('subdiario', self::SUBDIARIO_VENTAS_A_B)));
        if (! array_key_exists($subdiario, self::SUBDIARIOS)) {
            $subdiario = self::SUBDIARIO_VENTAS_A_B;
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $monedaId = (int) $request->input('moneda_id', 1);
        $provinciaId = max(0, (int) $request->input('provincia_id', 0));

        $features = IvaVentasFeaturesSupport::all();
        $consultando = $request->boolean('consultar');

        $clasificarHost = $features['clasificar_por_host'] && $request->boolean('clasificar_por_host');
        $conciliarPorUnidad = $features['unidades_negocio'] && (
            $consultando
                ? $request->boolean('conciliar_por_unidad', true)
                : true
        );
        $completarFsl = $features['completar_fsl_anita'] && (
            $consultando
                ? $request->boolean('completar_fsl_anita', true)
                : true
        );

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', date('Y-m-01'))),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', date('Y-m-d'))),
            'orden_fecha' => $orden,
            'subdiario' => $subdiario,
            'provincia_id' => $provinciaId,
            'cortar_por_jurisdiccion' => $request->boolean('cortar_por_jurisdiccion'),
            'clasificar_por_host' => $clasificarHost,
            'agrupar_b_por_dia' => $request->boolean('agrupar_b_por_dia'),
            'auditar_ctamov' => $request->boolean('auditar_ctamov'),
            'conciliar_contable' => $consultando
                ? $request->boolean('conciliar_contable', true)
                : true,
            'conciliar_por_unidad' => $conciliarPorUnidad,
            'solo_moneda_origen' => $consultando
                ? $request->boolean('solo_moneda_origen')
                : true,
            'completar_fsl_anita' => $completarFsl,
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
            'subdiario' => $filtros['subdiario'] ?? self::SUBDIARIO_VENTAS_A_B,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
        ];

        $provinciaId = (int) ($filtros['provincia_id'] ?? 0);
        if ($provinciaId > 0) {
            $out['provincia_id'] = $provinciaId;
        }

        if (! empty($filtros['cortar_por_jurisdiccion'])) {
            $out['cortar_por_jurisdiccion'] = 1;
        }

        if (! empty($filtros['clasificar_por_host'])) {
            $out['clasificar_por_host'] = 1;
        }

        if (! empty($filtros['agrupar_b_por_dia'])) {
            $out['agrupar_b_por_dia'] = 1;
        }

        if (! empty($filtros['auditar_ctamov'])) {
            $out['auditar_ctamov'] = 1;
        }

        $out['conciliar_contable'] = empty($filtros['conciliar_contable']) ? 0 : 1;
        $out['conciliar_por_unidad'] = empty($filtros['conciliar_por_unidad']) ? 0 : 1;
        $out['solo_moneda_origen'] = empty($filtros['solo_moneda_origen']) ? 0 : 1;
        $out['completar_fsl_anita'] = empty($filtros['completar_fsl_anita']) ? 0 : 1;

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
            // Z = RMV interno vending (p-vtagastro); entra al libro IVA ventas ERP.
            self::SUBDIARIO_VENTAS_B => $l === 'B' || $l === 'Z',
            self::SUBDIARIO_VENTAS_A_B => in_array($l, ['A', 'B', 'C', 'Z'], true),
            default => true,
        };
    }
}
