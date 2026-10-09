<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
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
        $puntoventaId = max(0, (int) $request->input('puntoventa_id', 0));
        $tipotransaccionId = max(0, (int) $request->input('tipotransaccion_id', 0));

        $features = IvaVentasFeaturesSupport::all();
        $consultando = $request->boolean('consultar');

        $clasificarHost = $features['clasificar_por_host'] && $request->boolean('clasificar_por_host');
        $conciliarPorUnidad = $features['unidades_negocio'] && (
            $consultando
                ? $request->boolean('conciliar_por_unidad', true)
                : true
        );
        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', date('Y-m-01'))),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', date('Y-m-d'))),
            'orden_fecha' => $orden,
            'subdiario' => $subdiario,
            'provincia_id' => $provinciaId,
            'puntoventa_id' => $puntoventaId,
            'tipotransaccion_id' => $tipotransaccionId,
            'cortar_por_jurisdiccion' => $request->boolean('cortar_por_jurisdiccion'),
            'cortar_por_sucursal_tipo' => $request->boolean('cortar_por_sucursal_tipo'),
            'clasificar_por_host' => $clasificarHost,
            'agrupar_b_por_dia' => $request->boolean('agrupar_b_por_dia'),
            'conciliar_contable' => $consultando
                ? $request->boolean('conciliar_contable', true)
                : true,
            'conciliar_por_unidad' => $conciliarPorUnidad,
            'solo_moneda_origen' => $consultando
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
            'subdiario' => $filtros['subdiario'] ?? self::SUBDIARIO_VENTAS_A_B,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
        ];

        $provinciaId = (int) ($filtros['provincia_id'] ?? 0);
        if ($provinciaId > 0) {
            $out['provincia_id'] = $provinciaId;
        }

        $puntoventaId = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $out['puntoventa_id'] = $puntoventaId;
        }

        $tipotransaccionId = (int) ($filtros['tipotransaccion_id'] ?? 0);
        if ($tipotransaccionId > 0) {
            $out['tipotransaccion_id'] = $tipotransaccionId;
        }

        if (! empty($filtros['cortar_por_jurisdiccion'])) {
            $out['cortar_por_jurisdiccion'] = 1;
        }

        if (! empty($filtros['cortar_por_sucursal_tipo'])) {
            $out['cortar_por_sucursal_tipo'] = 1;
        }

        if (! empty($filtros['clasificar_por_host'])) {
            $out['clasificar_por_host'] = 1;
        }

        if (! empty($filtros['agrupar_b_por_dia'])) {
            $out['agrupar_b_por_dia'] = 1;
        }

        $out['conciliar_contable'] = empty($filtros['conciliar_contable']) ? 0 : 1;
        $out['conciliar_por_unidad'] = empty($filtros['conciliar_por_unidad']) ? 0 : 1;
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

    /**
     * Sucursal y tipo activos, para el subtítulo de pantalla y export.
     */
    public static function formatearCorteTexto(array $filtros): string
    {
        $partes = [];
        $puntoventaId = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $pv = Puntoventa::query()->find($puntoventaId, ['id', 'codigo', 'nombre']);
            $etiqueta = trim((string) (($pv->codigo ?? '').' '.($pv->nombre ?? '')));
            $partes[] = 'Sucursal: '.($etiqueta !== '' ? $etiqueta : '#'.$puntoventaId);
        }

        $tipoId = (int) ($filtros['tipotransaccion_id'] ?? 0);
        if ($tipoId > 0) {
            $tipo = Tipotransaccion::query()->find($tipoId, ['id', 'abreviatura', 'nombre']);
            $etiqueta = trim((string) (($tipo->abreviatura ?? '').' '.($tipo->nombre ?? '')));
            $partes[] = 'Tipo: '.($etiqueta !== '' ? $etiqueta : '#'.$tipoId);
        }

        if (! empty($filtros['cortar_por_sucursal_tipo'])) {
            $partes[] = 'Listado separado por sucursal y tipo';
        }

        return implode(' · ', $partes);
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
