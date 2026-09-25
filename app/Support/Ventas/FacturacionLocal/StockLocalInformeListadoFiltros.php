<?php

namespace App\Support\Ventas\FacturacionLocal;

use Illuminate\Http\Request;

/**
 * Filtros del informe de stock de locales (puerto de l-stocklocal.c).
 */
final class StockLocalInformeListadoFiltros
{
    public const MODO_SALDO = 'saldo';

    public const MODO_APERTURA = 'apertura';

    /** Una fila por movimiento ERP con tipo y número de comprobante. */
    public const MODO_DETALLE = 'detalle';

    public const ORDEN_ARTICULO = 'articulo';

    public const ORDEN_CATEGORIA = 'categoria';

    /** Datos del ERP (articulo_movimiento / saldo). Default. */
    public const ORIGEN_ERP = 'erp';

    /** Datos Anita Local (stkdep / stkvmed vía bridge). */
    public const ORIGEN_ANITA = 'anita';

    /**
     * @return array{
     *   local_venta_id: ?int,
     *   deposito_anita: ?int,
     *   deposito_erp_id: ?int,
     *   origen: string,
     *   modo: string,
     *   orden: string,
     *   fecha_desde: ?string,
     *   fecha_hasta: ?string,
     *   desde_sku: string,
     *   hasta_sku: string,
     *   desde_color: ?int,
     *   hasta_color: ?int,
     *   solo_con_saldo: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = strtolower(trim((string) $request->input('modo', self::MODO_SALDO)));
        if (! in_array($modo, [self::MODO_SALDO, self::MODO_APERTURA, self::MODO_DETALLE], true)) {
            $modo = self::MODO_SALDO;
        }

        $orden = strtolower(trim((string) $request->input('orden', self::ORDEN_ARTICULO)));
        if (! in_array($orden, [self::ORDEN_ARTICULO, self::ORDEN_CATEGORIA], true)) {
            $orden = self::ORDEN_ARTICULO;
        }

        // Checkbox "Traer datos de Anita": 1 = anita, ausente/0 = erp
        $origen = $request->input('origen_anita', '0') === '1' || $request->input('origen') === self::ORIGEN_ANITA
            ? self::ORIGEN_ANITA
            : self::ORIGEN_ERP;

        $filtros = [
            'local_venta_id' => self::enteroOpcional($request->input('local_venta_id')),
            'deposito_anita' => self::enteroOpcional($request->input('deposito_anita')),
            'deposito_erp_id' => self::enteroOpcional($request->input('deposito_erp_id')),
            'origen' => $origen,
            'modo' => $modo,
            'orden' => $orden,
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'desde_sku' => self::textoOpcional($request->input('desde_sku')),
            'hasta_sku' => self::textoOpcional($request->input('hasta_sku')),
            'desde_color' => self::enteroRangoOpcional($request->input('desde_color')),
            'hasta_color' => self::enteroRangoOpcional($request->input('hasta_color')),
            'solo_con_saldo' => $request->input('solo_con_saldo', '1') !== '0',
        ];

        if ($request->boolean('consultar')) {
            self::aplicarFechasPorDefecto($filtros);
        }

        return $filtros;
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        $origenAnita = ($filtros['origen'] ?? self::ORIGEN_ERP) === self::ORIGEN_ANITA;

        return array_filter([
            'local_venta_id' => $filtros['local_venta_id'] ?? null,
            'deposito_anita' => $filtros['deposito_anita'] ?? null,
            'deposito_erp_id' => $filtros['deposito_erp_id'] ?? null,
            'origen_anita' => $origenAnita ? '1' : '0',
            'modo' => $filtros['modo'] ?? self::MODO_SALDO,
            'orden' => $filtros['orden'] ?? self::ORDEN_ARTICULO,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'desde_sku' => $filtros['desde_sku'] ?? null,
            'hasta_sku' => $filtros['hasta_sku'] ?? null,
            'desde_color' => $filtros['desde_color'] ?? null,
            'hasta_color' => $filtros['hasta_color'] ?? null,
            'solo_con_saldo' => ($filtros['solo_con_saldo'] ?? true) ? '1' : '0',
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['local_venta_id'])
            || ! empty($filtros['deposito_anita'])
            || ! empty($filtros['deposito_erp_id'])
            || ($filtros['origen'] ?? self::ORIGEN_ERP) !== self::ORIGEN_ERP
            || ($filtros['modo'] ?? self::MODO_SALDO) !== self::MODO_SALDO
            || ($filtros['orden'] ?? self::ORDEN_ARTICULO) !== self::ORDEN_ARTICULO
            || ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || trim((string) ($filtros['desde_sku'] ?? '')) !== ''
            || trim((string) ($filtros['hasta_sku'] ?? '')) !== ''
            || isset($filtros['desde_color'])
            || isset($filtros['hasta_color'])
            || ! ($filtros['solo_con_saldo'] ?? true);
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros), JSON_UNESCAPED_UNICODE) ?: '');
    }

    /** @param  array<string, mixed>  $filtros */
    public static function aplicarFechasPorDefecto(array &$filtros): void
    {
        if (empty($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }
        if (empty($filtros['fecha_desde'])) {
            // Igual que l-stocklocal batch: desde el inicio (0) → usamos 1900-01-01
            $filtros['fecha_desde'] = '1900-01-01';
        }
    }

    public static function etiquetaModo(string $modo): string
    {
        return match ($modo) {
            self::MODO_APERTURA => 'Entrada / venta / saldo',
            self::MODO_DETALLE => 'Detalle movimientos (tipo y nro.)',
            default => 'Solo saldo',
        };
    }

    public static function etiquetaOrden(string $orden): string
    {
        return $orden === self::ORDEN_CATEGORIA
            ? 'Por categoría (agrupación)'
            : 'Por artículo';
    }

    public static function etiquetaOrigen(string $origen): string
    {
        return $origen === self::ORIGEN_ANITA
            ? 'Anita Local (Informix)'
            : 'ERP (articulo_movimiento)';
    }

    private static function enteroOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $entero = (int) $valor;

        return $entero > 0 ? $entero : null;
    }

    private static function enteroRangoOpcional($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return $valor;
    }

    private static function textoOpcional($valor): string
    {
        return trim((string) $valor);
    }
}
