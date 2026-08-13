<?php

namespace App\Support\Stock;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de recepción de proveedores (l-recprov.c).
 */
final class RecepcionProveedorReporteFiltros
{
    public const MODO_DETALLE = 'detalle';

    public const MODO_RESUMEN = 'resumen';

    public const ORDEN_FECHA = 'fecha';

    public const ORDEN_ARTICULO = 'articulo';

    public const ORDEN_PROVEEDOR = 'proveedor';

    public const ORDEN_CENTROCOSTO = 'centrocosto';

    public const ORDEN_CUENTA = 'cuenta';

    public const ORDEN_COMPROBANTE = 'comprobante';

    public const FACTURACION_TODAS = 'todas';

    public const FACTURACION_NO_FACTURADAS = 'no_facturadas';

    public const FACTURACION_FACTURADAS = 'facturadas';

    public const TIPO_TODAS = 'todas';

    public const TIPO_RECEPCION = 'RECEPCION';

    public const TIPO_DEVOLUCION = 'DEVOLUCION';

    public const ESTADO_TODAS = 'todas';

    public const ESTADO_CONFIRMADA = 'CONFIRMADA';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_ANULADA = 'ANULADA';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO = [
        ['valor' => self::MODO_DETALLE, 'etiqueta' => 'Detalle por artículo (líneas)'],
        ['valor' => self::MODO_RESUMEN, 'etiqueta' => 'Resumen por COM'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ORDEN = [
        ['valor' => self::ORDEN_FECHA, 'etiqueta' => 'Fecha / COM'],
        ['valor' => self::ORDEN_ARTICULO, 'etiqueta' => 'Artículo'],
        ['valor' => self::ORDEN_PROVEEDOR, 'etiqueta' => 'Proveedor'],
        ['valor' => self::ORDEN_CENTROCOSTO, 'etiqueta' => 'Centro de costo'],
        ['valor' => self::ORDEN_CUENTA, 'etiqueta' => 'Cuenta contable'],
        ['valor' => self::ORDEN_COMPROBANTE, 'etiqueta' => 'Comprobante'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_FACTURACION = [
        ['valor' => self::FACTURACION_TODAS, 'etiqueta' => 'Todas'],
        ['valor' => self::FACTURACION_NO_FACTURADAS, 'etiqueta' => 'No facturadas (sin factura ERP)'],
        ['valor' => self::FACTURACION_FACTURADAS, 'etiqueta' => 'Facturadas (con factura ERP)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_TIPO = [
        ['valor' => self::TIPO_TODAS, 'etiqueta' => 'Recepciones y devoluciones'],
        ['valor' => self::TIPO_RECEPCION, 'etiqueta' => 'Solo recepciones'],
        ['valor' => self::TIPO_DEVOLUCION, 'etiqueta' => 'Solo devoluciones'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ESTADO = [
        ['valor' => self::ESTADO_CONFIRMADA, 'etiqueta' => 'Confirmadas'],
        ['valor' => self::ESTADO_TODAS, 'etiqueta' => 'Todas (incluye borrador y anuladas)'],
        ['valor' => self::ESTADO_BORRADOR, 'etiqueta' => 'Solo borrador'],
        ['valor' => self::ESTADO_ANULADA, 'etiqueta' => 'Solo anuladas'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $modo = trim((string) $request->input('modo', self::MODO_DETALLE));
        if (! self::modoValido($modo)) {
            $modo = self::MODO_DETALLE;
        }

        $orden = trim((string) $request->input('orden', self::ORDEN_FECHA));
        if (! self::ordenValido($orden)) {
            $orden = self::ORDEN_FECHA;
        }

        $facturacion = trim((string) $request->input('facturacion', self::FACTURACION_TODAS));
        if (! self::facturacionValida($facturacion)) {
            $facturacion = self::FACTURACION_TODAS;
        }

        $tipo = trim((string) $request->input('tipo', self::TIPO_TODAS));
        if (! self::tipoValido($tipo)) {
            $tipo = self::TIPO_TODAS;
        }

        $estado = trim((string) $request->input('estado', self::ESTADO_CONFIRMADA));
        if (! self::estadoValido($estado)) {
            $estado = self::ESTADO_CONFIRMADA;
        }

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'modo' => $modo,
            'orden' => $orden,
            'facturacion' => $facturacion,
            'tipo' => $tipo,
            'estado' => $estado,
            'solo_diferencias' => $request->boolean('solo_diferencias'),
            'solo_rechazadas' => $request->boolean('solo_rechazadas'),
            'proveedor' => trim((string) $request->input('proveedor', '')),
            'sku' => trim((string) $request->input('sku', '')),
            'deposito' => trim((string) $request->input('deposito', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'modo' => ($filtros['modo'] ?? self::MODO_DETALLE) !== self::MODO_DETALLE
                ? ($filtros['modo'] ?? null)
                : null,
            'orden' => ($filtros['orden'] ?? self::ORDEN_FECHA) !== self::ORDEN_FECHA
                ? ($filtros['orden'] ?? null)
                : null,
            'facturacion' => ($filtros['facturacion'] ?? self::FACTURACION_TODAS) !== self::FACTURACION_TODAS
                ? ($filtros['facturacion'] ?? null)
                : null,
            'tipo' => ($filtros['tipo'] ?? self::TIPO_TODAS) !== self::TIPO_TODAS
                ? ($filtros['tipo'] ?? null)
                : null,
            'estado' => ($filtros['estado'] ?? self::ESTADO_CONFIRMADA) !== self::ESTADO_CONFIRMADA
                ? ($filtros['estado'] ?? null)
                : null,
            'solo_diferencias' => ! empty($filtros['solo_diferencias']) ? 1 : null,
            'solo_rechazadas' => ! empty($filtros['solo_rechazadas']) ? 1 : null,
            'proveedor' => ($filtros['proveedor'] ?? '') !== '' ? ($filtros['proveedor'] ?? null) : null,
            'sku' => ($filtros['sku'] ?? '') !== '' ? ($filtros['sku'] ?? null) : null,
            'deposito' => ($filtros['deposito'] ?? '') !== '' ? ($filtros['deposito'] ?? null) : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy->copy()->subDays(90)->format('Y-m-d'),
            'fecha_hasta' => $hoy->format('Y-m-d'),
            'modo' => self::MODO_DETALLE,
            'orden' => self::ORDEN_FECHA,
            'facturacion' => self::FACTURACION_TODAS,
            'tipo' => self::TIPO_TODAS,
            'estado' => self::ESTADO_CONFIRMADA,
            'solo_diferencias' => false,
            'solo_rechazadas' => false,
            'proveedor' => '',
            'sku' => '',
            'deposito' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        return array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        $normalizado = [
            'empresa_ids' => self::empresaIds($filtros),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'modo' => (string) ($filtros['modo'] ?? self::MODO_DETALLE),
            'orden' => (string) ($filtros['orden'] ?? self::ORDEN_FECHA),
            'facturacion' => (string) ($filtros['facturacion'] ?? self::FACTURACION_TODAS),
            'tipo' => (string) ($filtros['tipo'] ?? self::TIPO_TODAS),
            'estado' => (string) ($filtros['estado'] ?? self::ESTADO_CONFIRMADA),
            'solo_diferencias' => ! empty($filtros['solo_diferencias']),
            'solo_rechazadas' => ! empty($filtros['solo_rechazadas']),
            'proveedor' => (string) ($filtros['proveedor'] ?? ''),
            'sku' => (string) ($filtros['sku'] ?? ''),
            'deposito' => (string) ($filtros['deposito'] ?? ''),
        ];

        return hash('sha256', json_encode($normalizado, JSON_UNESCAPED_UNICODE));
    }

    public static function modoValido(string $modo): bool
    {
        return in_array($modo, [self::MODO_DETALLE, self::MODO_RESUMEN], true);
    }

    public static function ordenValido(string $orden): bool
    {
        return in_array($orden, [
            self::ORDEN_FECHA,
            self::ORDEN_ARTICULO,
            self::ORDEN_PROVEEDOR,
            self::ORDEN_CENTROCOSTO,
            self::ORDEN_CUENTA,
            self::ORDEN_COMPROBANTE,
        ], true);
    }

    public static function facturacionValida(string $valor): bool
    {
        return in_array($valor, [
            self::FACTURACION_TODAS,
            self::FACTURACION_NO_FACTURADAS,
            self::FACTURACION_FACTURADAS,
        ], true);
    }

    public static function tipoValido(string $valor): bool
    {
        return in_array($valor, [self::TIPO_TODAS, self::TIPO_RECEPCION, self::TIPO_DEVOLUCION], true);
    }

    public static function estadoValido(string $valor): bool
    {
        return in_array($valor, [
            self::ESTADO_TODAS,
            self::ESTADO_CONFIRMADA,
            self::ESTADO_BORRADOR,
            self::ESTADO_ANULADA,
        ], true);
    }

    public static function etiquetaModo(string $modo): string
    {
        return self::etiquetaOpcion(self::OPCIONES_MODO, $modo);
    }

    public static function etiquetaOrden(string $orden): string
    {
        return self::etiquetaOpcion(self::OPCIONES_ORDEN, $orden);
    }

    public static function etiquetaFacturacion(string $valor): string
    {
        return self::etiquetaOpcion(self::OPCIONES_FACTURACION, $valor);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return $desde.' — '.$hasta;
        }

        if ($desde !== '') {
            return 'Desde '.$desde;
        }

        return '—';
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    /**
     * @param  list<array{valor: string, etiqueta: string}>  $opciones
     */
    private static function etiquetaOpcion(array $opciones, string $valor): string
    {
        foreach ($opciones as $opcion) {
            if ($opcion['valor'] === $valor) {
                return $opcion['etiqueta'];
            }
        }

        return $opciones[0]['etiqueta'] ?? $valor;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
