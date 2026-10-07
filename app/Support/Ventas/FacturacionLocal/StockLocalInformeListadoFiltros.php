<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Categoria;
use App\Models\Stock\Subcategoria;
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

    public const ESTADO_LOCAL_ACTIVO = 'ACTIVO';

    public const ESTADO_LOCAL_INACTIVO = 'INACTIVO';

    public const ESTADO_LOCAL_TODOS = 'TODOS';

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
     *   categoria_id: ?int,
     *   categoria_codigo: string,
     *   categoria_nombre: string,
     *   categoria_invalida: bool,
     *   subcategoria_id: ?int,
     *   subcategoria_codigo: string,
     *   subcategoria_nombre: string,
     *   subcategoria_invalida: bool,
     *   estado_local: string,
     *   fecha_desde: ?string,
     *   fecha_hasta: ?string,
     *   desde_sku: string,
     *   hasta_sku: string,
     *   desde_color: ?int,
     *   hasta_color: ?int,
     *   mventa_id: ?int,
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

        $categoria = self::resolverMaestro(
            Categoria::class,
            self::enteroOpcional($request->input('categoria_id')),
            self::textoOpcional($request->input('categoria_codigo')),
            $request->exists('categoria_codigo'),
        );
        $subcategoria = self::resolverMaestro(
            Subcategoria::class,
            self::enteroOpcional($request->input('subcategoria_id')),
            self::textoOpcional($request->input('subcategoria_codigo')),
            $request->exists('subcategoria_codigo'),
        );

        $estadoLocal = strtoupper(trim((string) $request->input('estado_local', self::ESTADO_LOCAL_ACTIVO)));
        if (! in_array($estadoLocal, [self::ESTADO_LOCAL_ACTIVO, self::ESTADO_LOCAL_INACTIVO, self::ESTADO_LOCAL_TODOS], true)) {
            $estadoLocal = self::ESTADO_LOCAL_ACTIVO;
        }

        $filtros = [
            'local_venta_id' => self::enteroOpcional($request->input('local_venta_id')),
            'deposito_anita' => self::enteroOpcional($request->input('deposito_anita')),
            'deposito_erp_id' => self::enteroOpcional($request->input('deposito_erp_id')),
            'origen' => self::ORIGEN_ERP,
            'modo' => $modo,
            'orden' => $orden,
            'categoria_id' => $categoria['id'],
            'categoria_codigo' => $categoria['codigo'],
            'categoria_nombre' => $categoria['nombre'],
            'categoria_invalida' => $categoria['invalida'],
            'subcategoria_id' => $subcategoria['id'],
            'subcategoria_codigo' => $subcategoria['codigo'],
            'subcategoria_nombre' => $subcategoria['nombre'],
            'subcategoria_invalida' => $subcategoria['invalida'],
            'estado_local' => $estadoLocal,
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'desde_sku' => self::textoOpcional($request->input('desde_sku')),
            'hasta_sku' => self::textoOpcional($request->input('hasta_sku')),
            'desde_color' => self::enteroRangoOpcional($request->input('desde_color')),
            'hasta_color' => self::enteroRangoOpcional($request->input('hasta_color')),
            'mventa_id' => self::enteroOpcional($request->input('mventa_id')),
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
        return array_filter([
            'local_venta_id' => $filtros['local_venta_id'] ?? null,
            'deposito_erp_id' => $filtros['deposito_erp_id'] ?? null,
            'modo' => $filtros['modo'] ?? self::MODO_SALDO,
            'orden' => $filtros['orden'] ?? self::ORDEN_ARTICULO,
            'categoria_id' => $filtros['categoria_id'] ?? null,
            'categoria_codigo' => ($filtros['categoria_codigo'] ?? '') !== '' ? $filtros['categoria_codigo'] : null,
            'subcategoria_id' => $filtros['subcategoria_id'] ?? null,
            'subcategoria_codigo' => ($filtros['subcategoria_codigo'] ?? '') !== '' ? $filtros['subcategoria_codigo'] : null,
            'estado_local' => ($filtros['estado_local'] ?? self::ESTADO_LOCAL_ACTIVO) !== self::ESTADO_LOCAL_ACTIVO
                ? ($filtros['estado_local'] ?? null)
                : null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'desde_sku' => $filtros['desde_sku'] ?? null,
            'hasta_sku' => $filtros['hasta_sku'] ?? null,
            'desde_color' => $filtros['desde_color'] ?? null,
            'hasta_color' => $filtros['hasta_color'] ?? null,
            'mventa_id' => $filtros['mventa_id'] ?? null,
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
            || ! empty($filtros['categoria_id'])
            || ! empty($filtros['categoria_invalida'])
            || ! empty($filtros['subcategoria_id'])
            || ! empty($filtros['subcategoria_invalida'])
            || ($filtros['estado_local'] ?? self::ESTADO_LOCAL_ACTIVO) !== self::ESTADO_LOCAL_ACTIVO
            || ! empty($filtros['fecha_desde'])
            || ! empty($filtros['fecha_hasta'])
            || trim((string) ($filtros['desde_sku'] ?? '')) !== ''
            || trim((string) ($filtros['hasta_sku'] ?? '')) !== ''
            || isset($filtros['desde_color'])
            || isset($filtros['hasta_color'])
            || ! empty($filtros['mventa_id'])
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

    public static function etiquetaEstadoLocal(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_LOCAL_INACTIVO => 'Inactivos (canal local)',
            self::ESTADO_LOCAL_TODOS => 'Activos e inactivos (canal local)',
            default => 'Activos (canal local)',
        };
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

    /**
     * Si el formulario envió el código, manda el código (vacío = sin filtro).
     * Si solo viene el id (paginación / export), se resuelve por id.
     *
     * @param  class-string<Categoria|Subcategoria>  $clase
     * @return array{id:?int,codigo:string,nombre:string,invalida:bool}
     */
    private static function resolverMaestro(string $clase, ?int $id, string $codigo, bool $vinoCodigo): array
    {
        if ($vinoCodigo) {
            if ($codigo === '') {
                return ['id' => null, 'codigo' => '', 'nombre' => '', 'invalida' => false];
            }
            $fila = self::buscarMaestroPorCodigo($clase, $codigo);
            if ($fila === null) {
                return ['id' => null, 'codigo' => $codigo, 'nombre' => '', 'invalida' => true];
            }

            return [
                'id' => (int) $fila->id,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'invalida' => false,
            ];
        }

        if ($id === null) {
            return ['id' => null, 'codigo' => '', 'nombre' => '', 'invalida' => false];
        }

        $fila = $clase::query()->select(['id', 'codigo', 'nombre'])->whereKey($id)->first();
        if ($fila === null) {
            return ['id' => null, 'codigo' => (string) $id, 'nombre' => '', 'invalida' => true];
        }

        return [
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
            'invalida' => false,
        ];
    }

    /**
     * @param  class-string<Categoria|Subcategoria>  $clase
     */
    private static function buscarMaestroPorCodigo(string $clase, string $codigo): ?object
    {
        $base = $clase::query()->select(['id', 'codigo', 'nombre']);
        $fila = (clone $base)->where('codigo', $codigo)->first();
        if ($fila) {
            return $fila;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            $fila = (clone $base)->where('codigo', $alt)->first();
            if ($fila) {
                return $fila;
            }
        }

        if (ctype_digit($codigo)) {
            return (clone $base)->whereKey((int) $codigo)->first();
        }

        return null;
    }
}
