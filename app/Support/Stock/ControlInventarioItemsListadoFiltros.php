<?php

namespace App\Support\Stock;

use Illuminate\Http\Request;

final class ControlInventarioItemsListadoFiltros
{
    public const ID_PRIMERO = 0;

    public const ID_ULTIMO = 99999999;

    /**
     * @return array{
     *     empresa_id: ?int,
     *     fecha_desde: ?string,
     *     fecha_hasta: ?string,
     *     desdearticulo_id: ?int,
     *     hastaarticulo_id: ?int,
     *     desdecategoria_id: ?int,
     *     hastacategoria_id: ?int,
     *     desdesubcategoria_id: ?int,
     *     hastasubcategoria_id: ?int,
     *     desdeusoarticulo_id: ?int,
     *     hastausoarticulo_id: ?int,
     *     desdetipoarticulo_id: ?int,
     *     hastatipoarticulo_id: ?int,
     *     desde_deposito: string,
     *     hasta_deposito: string,
     *     depositos_filtro: string,
     *     solo_con_movimiento: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desdeDeposito = self::textoOpcional($request->input('desde_deposito'));
        $hastaDeposito = self::textoOpcional($request->input('hasta_deposito'));

        // Compat: si viene el texto libre viejo y no hay desde/hasta, lo respetamos.
        $depositosFiltroLibre = self::textoOpcional($request->input('depositos_filtro'));
        $depositosFiltro = self::componerFiltroDepositos($desdeDeposito, $hastaDeposito);
        if ($depositosFiltro === '' && $depositosFiltroLibre !== '') {
            $depositosFiltro = $depositosFiltroLibre;
        }

        $filtros = [
            'empresa_id' => self::enteroOpcional($request->input('empresa_id')),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'desdearticulo_id' => self::enteroRangoOpcional($request->input('desdearticulo_id')),
            'hastaarticulo_id' => self::enteroRangoOpcional($request->input('hastaarticulo_id')),
            'desdecategoria_id' => self::enteroRangoOpcional($request->input('desdecategoria_id')),
            'hastacategoria_id' => self::enteroRangoOpcional($request->input('hastacategoria_id')),
            'desdesubcategoria_id' => self::enteroRangoOpcional($request->input('desdesubcategoria_id')),
            'hastasubcategoria_id' => self::enteroRangoOpcional($request->input('hastasubcategoria_id')),
            'desdeusoarticulo_id' => self::enteroRangoOpcional($request->input('desdeusoarticulo_id')),
            'hastausoarticulo_id' => self::enteroRangoOpcional($request->input('hastausoarticulo_id')),
            'desdetipoarticulo_id' => self::enteroRangoOpcional($request->input('desdetipoarticulo_id')),
            'hastatipoarticulo_id' => self::enteroRangoOpcional($request->input('hastatipoarticulo_id')),
            'desde_deposito' => $desdeDeposito,
            'hasta_deposito' => $hastaDeposito,
            'depositos_filtro' => $depositosFiltro,
            'solo_con_movimiento' => $request->input('solo_con_movimiento', '1') !== '0',
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
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'desdearticulo_id' => $filtros['desdearticulo_id'] ?? null,
            'hastaarticulo_id' => $filtros['hastaarticulo_id'] ?? null,
            'desdecategoria_id' => $filtros['desdecategoria_id'] ?? null,
            'hastacategoria_id' => $filtros['hastacategoria_id'] ?? null,
            'desdesubcategoria_id' => $filtros['desdesubcategoria_id'] ?? null,
            'hastasubcategoria_id' => $filtros['hastasubcategoria_id'] ?? null,
            'desdeusoarticulo_id' => $filtros['desdeusoarticulo_id'] ?? null,
            'hastausoarticulo_id' => $filtros['hastausoarticulo_id'] ?? null,
            'desdetipoarticulo_id' => $filtros['desdetipoarticulo_id'] ?? null,
            'hastatipoarticulo_id' => $filtros['hastatipoarticulo_id'] ?? null,
            'desde_deposito' => $filtros['desde_deposito'] ?? null,
            'hasta_deposito' => $filtros['hasta_deposito'] ?? null,
            'solo_con_movimiento' => ($filtros['solo_con_movimiento'] ?? true) ? '1' : '0',
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Compone el filtro interno `codigoDesde/codigoHasta` para ExistenciasDepositoFiltroDepositosSupport.
     */
    public static function componerFiltroDepositos(string $desde, string $hasta): string
    {
        $desde = trim($desde);
        $hasta = trim($hasta);
        if ($desde === '' && $hasta === '') {
            return '';
        }
        if ($desde !== '' && $hasta !== '') {
            return $desde.'/'.$hasta;
        }
        if ($desde !== '') {
            return $desde.'/99999999';
        }

        return '0/'.$hasta;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function aplicarFechasPorDefecto(array &$filtros): void
    {
        if (empty($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = date('Y-m-d');
        }
        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = date('Y-m-01', strtotime((string) $filtros['fecha_hasta']));
        }
        if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
            [$filtros['fecha_desde'], $filtros['fecha_hasta']] = [$filtros['fecha_hasta'], $filtros['fecha_desde']];
        }
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

        return $valor !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
    }

    private static function textoOpcional($valor): string
    {
        return trim((string) $valor);
    }
}
