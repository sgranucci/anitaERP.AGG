<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class IvaComprasListadoFiltros
{
    public const ORDEN_FECHA_COMP = 'fechacomprobante';

    public const ORDEN_FECHA_IVA = 'fechaiva';

    public const ORDEN_PROVEEDOR = 'proveedor';

    public const ORDEN_TIPO = 'tipo';

    public const ORDEN_COMPROBANTE = 'comprobante';

    public const ORDEN_IMPORTE = 'importe';

    public const ORDENES = [
        self::ORDEN_FECHA_COMP => 'Fecha del comprobante',
        self::ORDEN_FECHA_IVA => 'Fecha IVA',
        self::ORDEN_PROVEEDOR => 'Proveedor',
        self::ORDEN_TIPO => 'Tipo de comprobante',
        self::ORDEN_COMPROBANTE => 'Número de comprobante',
        self::ORDEN_IMPORTE => 'Importe total',
    ];

    public const DIR_ASC = 'asc';

    public const DIR_DESC = 'desc';

    public const DIRECCIONES = [
        self::DIR_ASC => 'Ascendente',
        self::DIR_DESC => 'Descendente',
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
        $orden = trim((string) $request->input('orden', self::ORDEN_FECHA_COMP));
        if (! array_key_exists($orden, self::ORDENES)) {
            $orden = self::ORDEN_FECHA_COMP;
        }

        $ordenDir = strtolower(trim((string) $request->input('orden_dir', self::DIR_ASC)));
        if (! array_key_exists($ordenDir, self::DIRECCIONES)) {
            $ordenDir = self::DIR_ASC;
        }

        $subdiario = strtoupper(trim((string) $request->input('subdiario', self::SUBDIARIO_TODOS)));
        if (! array_key_exists($subdiario, self::SUBDIARIOS)) {
            $subdiario = self::SUBDIARIO_TODOS;
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $monedaId = (int) $request->input('moneda_id', 1);

        $proveedorId = (int) $request->input('proveedor_id', 0);

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => trim((string) $request->input('fecha_desde', date('Y-m-01'))),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', date('Y-m-d'))),
            'orden' => $orden,
            'orden_dir' => $ordenDir,
            'subdiario' => $subdiario,
            'proveedor_id' => $proveedorId > 0 ? $proveedorId : 0,
            'proveedor_codigo' => '',
            'proveedor_nombre' => '',
            'proveedor_etiqueta' => '',
            'tipo' => trim((string) $request->input('tipo', '')),
            'nro_comprobante' => trim((string) $request->input('nro_comprobante', '')),
            'importe_desde' => self::importeOpcional($request->input('importe_desde')),
            'importe_hasta' => self::importeOpcional($request->input('importe_hasta')),
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
            'orden' => $filtros['orden'] ?? self::ORDEN_FECHA_COMP,
            'orden_dir' => $filtros['orden_dir'] ?? self::DIR_ASC,
            'subdiario' => $filtros['subdiario'] ?? self::SUBDIARIO_TODOS,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
        ];

        $proveedorId = (int) ($filtros['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $out['proveedor_id'] = $proveedorId;
        }
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $out['tipo'] = $tipo;
        }
        $nro = trim((string) ($filtros['nro_comprobante'] ?? ''));
        if ($nro !== '') {
            $out['nro_comprobante'] = $nro;
        }
        if (($filtros['importe_desde'] ?? null) !== null) {
            $out['importe_desde'] = $filtros['importe_desde'];
        }
        if (($filtros['importe_hasta'] ?? null) !== null) {
            $out['importe_hasta'] = $filtros['importe_hasta'];
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
        $orden = $filtros['orden'] ?? self::ORDEN_FECHA_COMP;
        $etiqueta = self::ORDENES[$orden] ?? $orden;
        $dir = ($filtros['orden_dir'] ?? self::DIR_ASC) === self::DIR_DESC
            ? 'descendente'
            : 'ascendente';

        return $etiqueta.' ('.$dir.')';
    }

    public static function formatearCriteriosExtra(array $filtros): string
    {
        $partes = [];
        $proveedorId = (int) ($filtros['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $etiqueta = trim((string) ($filtros['proveedor_etiqueta'] ?? ''));
            $partes[] = 'Proveedor: '.($etiqueta !== '' ? $etiqueta : (string) $proveedorId);
        }

        $desde = $filtros['importe_desde'] ?? null;
        $hasta = $filtros['importe_hasta'] ?? null;
        if ($desde !== null || $hasta !== null) {
            $texto = 'Importe';
            if ($desde !== null) {
                $texto .= ' desde '.number_format((float) $desde, 2, ',', '.');
            }
            if ($hasta !== null) {
                $texto .= ' hasta '.number_format((float) $hasta, 2, ',', '.');
            }
            $partes[] = $texto;
        }

        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $partes[] = 'Tipo: '.$tipo;
        }

        $nro = trim((string) ($filtros['nro_comprobante'] ?? ''));
        if ($nro !== '') {
            $partes[] = 'Nro. comprobante: '.$nro;
        }

        return implode(' · ', $partes);
    }

    public static function formatearSubtitulo(array $filtros): string
    {
        $partes = array_values(array_filter([
            'Período: '.self::formatearPeriodoTexto($filtros),
            'Orden: '.self::formatearOrdenTexto($filtros),
            self::formatearSubdiarioTexto($filtros),
            self::formatearCriteriosExtra($filtros),
        ], static fn ($parte): bool => trim((string) $parte) !== ''));

        return implode(' · ', $partes);
    }

    public static function importeOpcional(mixed $valor): ?float
    {
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
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
