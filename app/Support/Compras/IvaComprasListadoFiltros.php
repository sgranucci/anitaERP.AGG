<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class IvaComprasListadoFiltros
{
    public const ORDEN_FECHA_IVA = 'fechaiva';

    public const ORDEN_FECHA_COMP = 'fechacomprobante';

    public const ORDEN_PROVEEDOR = 'proveedor';

    public const ORDEN_TIPO = 'tipo';

    public const ORDENES = [
        self::ORDEN_FECHA_IVA => 'Fecha IVA',
        self::ORDEN_FECHA_COMP => 'Fecha movimiento',
        self::ORDEN_PROVEEDOR => 'Proveedor',
        self::ORDEN_TIPO => 'Tipo de comprobante',
    ];

    /** Subdiario Compras (tipotransaccion_compra.subdiario = C), más Gastos si existiera. */
    public const SUBDIARIO_COMPRAS = 'C';

    /** Todos los informables al libro (C y G), como Libro IVA Digital. */
    public const SUBDIARIO_TODOS = 'TODOS';

    /** Solo FCE (es_fce = 1). */
    public const SUBDIARIO_FCE = 'FCE';

    public const SUBDIARIOS = [
        self::SUBDIARIO_TODOS => 'Compras y gastos (recomendado)',
        self::SUBDIARIO_COMPRAS => 'Solo subdiario Compras (C)',
        self::SUBDIARIO_FCE => 'Solo FCE',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $orden = trim((string) $request->input('orden', self::ORDEN_FECHA_IVA));
        if (! array_key_exists($orden, self::ORDENES)) {
            $orden = self::ORDEN_FECHA_IVA;
        }

        $subdiario = strtoupper(trim((string) $request->input('subdiario', self::SUBDIARIO_TODOS)));
        if (! array_key_exists($subdiario, self::SUBDIARIOS)) {
            $subdiario = self::SUBDIARIO_TODOS;
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $monedaId = (int) $request->input('moneda_id', 1);

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', date('Y-m-01'))),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', date('Y-m-d'))),
            'orden' => $orden,
            'subdiario' => $subdiario,
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
            'orden' => $filtros['orden'] ?? self::ORDEN_FECHA_IVA,
            'subdiario' => $filtros['subdiario'] ?? self::SUBDIARIO_TODOS,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
        ];

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
        $orden = $filtros['orden'] ?? self::ORDEN_FECHA_IVA;

        return self::ORDENES[$orden] ?? $orden;
    }

    public static function formatearSubdiarioTexto(array $filtros): string
    {
        $sub = $filtros['subdiario'] ?? self::SUBDIARIO_TODOS;

        return self::SUBDIARIOS[$sub] ?? $sub;
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros)));
    }
}
