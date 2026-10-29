<?php

namespace App\Support\Ventas;

use Illuminate\Http\Request;

/**
 * Criterios del análisis de entregas (l-analisisent.c), recorte operativo.
 */
final class AnalisisEntregasFiltros
{
    public const ORDEN_PEDIDO = 'pedido';

    public const ORDEN_CLIENTE = 'cliente';

    public const ORDEN_ARTICULO = 'articulo';

    public const ORDEN_VENDEDOR = 'vendedor';

    public const ESTADO_TODOS = 'todos';

    public const ESTADO_CON_SALDO = 'con_saldo';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_RECHAZADO = 'rechazado';

    public const ESTADO_PRODUCCION = 'produccion';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_CONDICIONAL = 'condicional';

    public const ESTADO_ANULADO = 'anulado';

    public const SITUACION_TODOS = 'todos';

    public const SITUACION_PENDIENTES = 'pendientes';

    public const SITUACION_CERRADOS = 'cerrados';

    public const SITUACION_CUMPLIDOS = 'cumplidos';

    public const MODO_DETALLE = 'detalle';

    public const MODO_TOTALES = 'totales';

    /**
     * @return array<string, string>
     */
    public static function ordenes(): array
    {
        return [
            self::ORDEN_PEDIDO => 'Por número de pedido',
            self::ORDEN_CLIENTE => 'Por cliente',
            self::ORDEN_ARTICULO => 'Por artículo',
            self::ORDEN_VENDEDOR => 'Por vendedor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return [
            self::ESTADO_TODOS => 'Todos (sin anulados)',
            self::ESTADO_CON_SALDO => 'Pendiente de entregar',
            self::ESTADO_APROBADO => 'Aprobado',
            self::ESTADO_RECHAZADO => 'Rechazado',
            self::ESTADO_PRODUCCION => 'Producción',
            self::ESTADO_ENTREGADO => 'Entregado',
            self::ESTADO_CONDICIONAL => 'Condicional',
            self::ESTADO_ANULADO => 'Anulados',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situaciones(): array
    {
        return [
            self::SITUACION_TODOS => 'Todos los ítems',
            self::SITUACION_PENDIENTES => 'Pendientes',
            self::SITUACION_CERRADOS => 'Cerrados',
            self::SITUACION_CUMPLIDOS => 'Cumplidos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function modos(): array
    {
        return [
            self::MODO_DETALLE => 'Movimientos',
            self::MODO_TOTALES => 'Solo totales',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $orden = (string) $request->input('orden', self::ORDEN_PEDIDO);
        if (! array_key_exists($orden, self::ordenes())) {
            $orden = self::ORDEN_PEDIDO;
        }

        $estado = (string) $request->input('estado', self::ESTADO_TODOS);
        if (! array_key_exists($estado, self::estados())) {
            $estado = self::ESTADO_TODOS;
        }

        $situacion = (string) $request->input('situacion', self::SITUACION_TODOS);
        if (! array_key_exists($situacion, self::situaciones())) {
            $situacion = self::SITUACION_TODOS;
        }

        $modo = (string) $request->input('modo', self::MODO_DETALLE);
        if (! array_key_exists($modo, self::modos())) {
            $modo = self::MODO_DETALLE;
        }

        return [
            'orden' => $orden,
            'estado' => $estado,
            'situacion' => $situacion,
            'modo' => $modo,
            'fecha_pedido_desde' => self::fecha($request, 'fecha_pedido_desde', now()->startOfMonth()->toDateString()),
            'fecha_pedido_hasta' => self::fecha($request, 'fecha_pedido_hasta', now()->toDateString()),
            'fecha_entrega_desde' => self::fecha($request, 'fecha_entrega_desde', ''),
            'fecha_entrega_hasta' => self::fecha($request, 'fecha_entrega_hasta', ''),
            'fecha_parametro' => self::fecha($request, 'fecha_parametro', now()->toDateString()) ?: now()->toDateString(),
            'cliente_id' => max(0, (int) $request->input('cliente_id', 0)),
            'articulo_id' => max(0, (int) $request->input('articulo_id', 0)),
            'vendedor_id' => max(0, (int) $request->input('vendedor_id', 0)),
            'sucursal_desde' => self::enteroOpcional($request->input('sucursal_desde')),
            'sucursal_hasta' => self::enteroOpcional($request->input('sucursal_hasta')),
            'consultar' => $request->boolean('consultar') ? 1 : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = [
            'consultar' => 1,
            'orden' => $filtros['orden'] ?? self::ORDEN_PEDIDO,
            'estado' => $filtros['estado'] ?? self::ESTADO_TODOS,
            'situacion' => $filtros['situacion'] ?? self::SITUACION_TODOS,
            'modo' => $filtros['modo'] ?? self::MODO_DETALLE,
            'fecha_parametro' => $filtros['fecha_parametro'] ?? '',
        ];

        foreach ([
            'fecha_pedido_desde',
            'fecha_pedido_hasta',
            'fecha_entrega_desde',
            'fecha_entrega_hasta',
        ] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $query[$clave] = $valor;
            }
        }

        foreach (['cliente_id', 'articulo_id', 'vendedor_id'] as $clave) {
            $id = (int) ($filtros[$clave] ?? 0);
            if ($id > 0) {
                $query[$clave] = $id;
            }
        }

        if ($filtros['sucursal_desde'] !== null && $filtros['sucursal_desde'] !== '') {
            $query['sucursal_desde'] = $filtros['sucursal_desde'];
        }
        if ($filtros['sucursal_hasta'] !== null && $filtros['sucursal_hasta'] !== '') {
            $query['sucursal_hasta'] = $filtros['sucursal_hasta'];
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [
            self::ordenes()[$filtros['orden']] ?? '',
            self::estados()[$filtros['estado']] ?? '',
            self::situaciones()[$filtros['situacion']] ?? '',
            self::modos()[$filtros['modo']] ?? '',
        ];

        $pedido = self::rangoTexto(
            'Pedido',
            (string) ($filtros['fecha_pedido_desde'] ?? ''),
            (string) ($filtros['fecha_pedido_hasta'] ?? '')
        );
        $entrega = self::rangoTexto(
            'Entrega',
            (string) ($filtros['fecha_entrega_desde'] ?? ''),
            (string) ($filtros['fecha_entrega_hasta'] ?? '')
        );
        $partes[] = $pedido;
        $partes[] = $entrega;
        $partes[] = 'Atrasos al '.self::dmy((string) ($filtros['fecha_parametro'] ?? ''));

        if (($filtros['sucursal_desde'] ?? null) !== null || ($filtros['sucursal_hasta'] ?? null) !== null) {
            $partes[] = 'Sucursal '
                .($filtros['sucursal_desde'] ?? '0')
                .' a '
                .($filtros['sucursal_hasta'] ?? '9999');
        }

        return implode(' · ', array_filter($partes, static fn ($p) => $p !== ''));
    }

    private static function fecha(Request $request, string $clave, string $siNoViene): string
    {
        if (! $request->exists($clave)) {
            return $siNoViene;
        }

        $valor = trim((string) $request->input($clave, ''));
        if ($valor === '') {
            return '';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $valor);

        return $dt instanceof \DateTime ? $dt->format('Y-m-d') : '';
    }

    private static function enteroOpcional(mixed $valor): ?int
    {
        $texto = trim((string) $valor);
        if ($texto === '' || ! preg_match('/^-?\d+$/', $texto)) {
            return null;
        }

        return (int) $texto;
    }

    private static function rangoTexto(string $titulo, string $desde, string $hasta): string
    {
        if ($desde === '' && $hasta === '') {
            return $titulo.': todos';
        }
        if ($desde !== '' && $hasta !== '') {
            return $titulo.': '.self::dmy($desde).' a '.self::dmy($hasta);
        }
        if ($desde !== '') {
            return $titulo.': desde '.self::dmy($desde);
        }

        return $titulo.': hasta '.self::dmy($hasta);
    }

    private static function dmy(string $ymd): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);

        return $dt instanceof \DateTime ? $dt->format('d/m/Y') : $ymd;
    }
}
