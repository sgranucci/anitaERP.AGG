<?php

namespace App\Support\Stock;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;

/**
 * Informe de movimientos de stock por artículo (Anita l-stkmov.c, orden x artículo).
 *
 * En Ferli cada talle de articulo_movimiento_talle es un renglón. El código de
 * combinación (color) es lo que Anita imprimía en N.Par. El importe usa el precio
 * del movimiento menos el descuento de línea y el descuento general de la venta.
 * La cantidad de articulo_movimiento ya viene firmada: positiva entrada, negativa salida.
 */
final class MovimientoStockArticuloReporteSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<object>, totales: array<string, float|int>}
     */
    public static function consultar(array $filtros): array
    {
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($fechaDesde === '' || $fechaHasta === '') {
            throw new \InvalidArgumentException('Indique desde y hasta fecha.');
        }
        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
            $filtros['fecha_desde'] = $fechaDesde;
            $filtros['fecha_hasta'] = $fechaHasta;
        }

        $movimientos = self::leerMovimientos($filtros, $fechaDesde, $fechaHasta);
        $aperturas = [];
        if (self::correspondeSaldoInicial($fechaDesde)) {
            $aperturas = self::leerAperturas($filtros, $fechaDesde);
        }

        return self::armarPresentacion($movimientos, $aperturas, $filtros);
    }

    /**
     * Anita: si la fecha desde es el día 1 del mes no arrastra saldo inicial.
     */
    public static function correspondeSaldoInicial(?string $fecha): bool
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '' || ! preg_match('/^\d{4}-\d{2}-(\d{2})$/', $fecha, $m)) {
            return true;
        }

        return (int) $m[1] !== 1;
    }

    /**
     * Factura normal: el movimiento nace con el comprobante y sale por venta.fecha.
     * Picking: el stock ya salió al preparar (Consumo de OT con otra fecha) y la
     * factura solo vincula venta_id. Ese renglón queda en la fecha del picking.
     */
    private static function sqlFechaInforme(): string
    {
        return 'CASE'
            .' WHEN am.venta_id IS NOT NULL AND v.fecha IS NOT NULL'
            .' AND NOT (am.concepto = \'Consumo de OT\' AND am.fecha <> v.fecha)'
            .' THEN v.fecha'
            .' ELSE am.fecha END';
    }

    private static function fechaYmd(mixed $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        return substr($fecha, 0, 10);
    }

    public static function precioNeto(float $precio, float $descuentoLinea, float $descuentoGeneral): float
    {
        $neto = $precio;
        $neto *= (1 - ($descuentoLinea / 100));
        $neto *= (1 - ($descuentoGeneral / 100));

        return $neto;
    }

    /**
     * FAC A-00012-00083128 → A0012-00083128 (letra + punto de venta 4 + número 8).
     */
    public static function numeroComprobante(?string $ventaCodigo, ?string $movimientoCodigo, ?string $concepto = null): string
    {
        $codigo = trim((string) $ventaCodigo);
        if ($codigo !== '' && preg_match('/([A-Za-z])-0*(\d+)-0*(\d+)/', $codigo, $m) === 1) {
            return strtoupper($m[1])
                .str_pad($m[2], 4, '0', STR_PAD_LEFT)
                .'-'
                .str_pad($m[3], 8, '0', STR_PAD_LEFT);
        }
        if ($codigo !== '') {
            return $codigo;
        }
        $mov = trim((string) $movimientoCodigo);
        if ($mov !== '') {
            return $mov;
        }

        return trim((string) $concepto);
    }

    public static function skuAnita(string $sku): string
    {
        $sku = trim($sku);
        if ($sku !== '' && ctype_digit($sku) && strlen($sku) < 13) {
            return str_pad($sku, 13, '0', STR_PAD_LEFT);
        }

        return $sku;
    }

    public static function codigoCliente(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (ctype_digit($codigo) && strlen($codigo) < 6) {
            return str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }

    public static function nombreCliente(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s*,\s*/', ' ', $nombre) ?? $nombre);
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);

        return $nombre;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<int, array{cantidad: float, importe: float}>  $aperturas
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<object>, totales: array<string, float|int>}
     */
    public static function armarPresentacion(array $movimientos, array $aperturas, array $filtros): array
    {
        $soloTotales = ($filtros['modo'] ?? '') === MovimientoStockArticuloReporteFiltros::MODO_TOTALES;
        $totalDia = ! empty($filtros['total_dia']);
        $salto = ! empty($filtros['salto_articulo']);
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');

        $porArticulo = [];
        foreach ($movimientos as $mov) {
            $id = (int) ($mov['articulo_id'] ?? 0);
            $porArticulo[$id][] = $mov;
        }

        uksort($porArticulo, function (int $a, int $b) use ($porArticulo): int {
            $skuA = self::skuAnita((string) ($porArticulo[$a][0]['sku'] ?? ''));
            $skuB = self::skuAnita((string) ($porArticulo[$b][0]['sku'] ?? ''));

            return $skuA <=> $skuB;
        });

        $filas = [];
        $totEntrada = 0.0;
        $totSalida = 0.0;
        $totImporte = 0.0;
        $nMov = 0;
        $primerArticulo = true;

        foreach ($porArticulo as $articuloId => $lineas) {
            usort($lineas, [self::class, 'compararLineas']);
            $cab = $lineas[0];
            $sku = self::skuAnita((string) ($cab['sku'] ?? ''));
            $agr = trim((string) ($cab['agrupacion'] ?? ''));
            if ($agr !== '' && ctype_digit($agr) && strlen($agr) < 4) {
                $agr = str_pad($agr, 4, '0', STR_PAD_LEFT);
            }
            $textoArt = 'Art.: '.$sku.' '.trim((string) ($cab['descripcion'] ?? ''));
            if ($agr !== '') {
                $textoArt .= '   Agr.: '.$agr;
            }

            $filas[] = self::fila('encabezado', [
                'texto' => $textoArt,
                'articulo_id' => $articuloId,
                'sku' => (string) ($cab['sku'] ?? ''),
                'salto' => $salto && ! $primerArticulo,
                'nombreempresa' => (string) ($cab['nombreempresa'] ?? ''),
            ]);
            $primerArticulo = false;

            $apertura = $aperturas[$articuloId] ?? null;
            $saldo = 0.0;
            if ($apertura !== null && (abs($apertura['cantidad']) > 0.0000001 || abs($apertura['importe']) > 0.0000001)) {
                $saldo = (float) $apertura['cantidad'];
                if (! $soloTotales) {
                    $filas[] = self::fila('saldo_inicial', [
                        'texto' => 'Saldo inicial al '.MovimientoStockArticuloReporteFiltros::fechaHumana($fechaDesde),
                        'saldo' => $saldo,
                        'importe' => (float) $apertura['importe'],
                        'articulo_id' => $articuloId,
                        'nombreempresa' => (string) ($cab['nombreempresa'] ?? ''),
                    ]);
                }
            }

            $diaEntrada = 0.0;
            $diaSalida = 0.0;
            $diaImporte = 0.0;
            $diaFecha = null;
            $artEntrada = 0.0;
            $artSalida = 0.0;
            $artImporte = 0.0;
            $imprimio = false;

            $cerrarDia = function () use (
                &$filas,
                &$diaFecha,
                &$diaEntrada,
                &$diaSalida,
                &$diaImporte,
                $totalDia,
                $articuloId,
                $cab
            ): void {
                if (! $totalDia || $diaFecha === null) {
                    return;
                }
                if (abs($diaEntrada) < 0.0000001 && abs($diaSalida) < 0.0000001 && abs($diaImporte) < 0.0000001) {
                    $diaFecha = null;
                    $diaEntrada = $diaSalida = $diaImporte = 0.0;

                    return;
                }
                $filas[] = self::fila('total_dia', [
                    'texto' => 'Total día '.MovimientoStockArticuloReporteFiltros::fechaHumana($diaFecha),
                    'entrada' => $diaEntrada,
                    'salida' => $diaSalida,
                    'saldo' => $diaEntrada - $diaSalida,
                    'importe' => $diaImporte,
                    'articulo_id' => $articuloId,
                    'nombreempresa' => (string) ($cab['nombreempresa'] ?? ''),
                ]);
                $diaFecha = null;
                $diaEntrada = $diaSalida = $diaImporte = 0.0;
            };

            foreach ($lineas as $lin) {
                $cant = (float) ($lin['cantidad'] ?? 0);
                if (abs($cant) < 0.0000001) {
                    continue;
                }
                $fecha = (string) ($lin['fecha'] ?? '');
                if ($diaFecha !== null && $fecha !== $diaFecha) {
                    $cerrarDia();
                }
                $diaFecha = $fecha;

                $entrada = $cant > 0 ? $cant : null;
                $salida = $cant < 0 ? abs($cant) : null;
                $importe = (float) ($lin['importe'] ?? 0);
                $saldo += $cant;
                if ($entrada !== null) {
                    $diaEntrada += $entrada;
                    $artEntrada += $entrada;
                    $totEntrada += $entrada;
                }
                if ($salida !== null) {
                    $diaSalida += $salida;
                    $artSalida += $salida;
                    $totSalida += $salida;
                }
                $diaImporte += $importe;
                $artImporte += $importe;
                $totImporte += $importe;
                $nMov++;
                $imprimio = true;

                if ($soloTotales) {
                    continue;
                }

                $filas[] = self::fila('movimiento', [
                    'fecha' => $fecha,
                    'tip' => (string) ($lin['tip'] ?? ''),
                    'numero' => (string) ($lin['numero'] ?? ''),
                    'combinacion' => (string) ($lin['combinacion'] ?? ''),
                    'color' => (string) ($lin['color'] ?? ''),
                    'talle' => (string) ($lin['talle'] ?? ''),
                    'entrada' => $entrada,
                    'salida' => $salida,
                    'saldo' => $saldo,
                    'umd' => (string) ($lin['umd'] ?? ''),
                    'importe' => $importe,
                    'cliente_codigo' => self::codigoCliente((string) ($lin['cliente_codigo'] ?? '')),
                    'cliente_nombre' => self::nombreCliente((string) ($lin['cliente_nombre'] ?? '')),
                    'deposito' => (string) ($lin['deposito'] ?? ''),
                    'partida' => (string) ($lin['partida'] ?? ''),
                    'concepto' => (string) ($lin['concepto'] ?? ''),
                    'articulo_id' => $articuloId,
                    'venta_id' => (int) ($lin['venta_id'] ?? 0),
                    'movimientostock_id' => (int) ($lin['movimientostock_id'] ?? 0),
                    'nombreempresa' => (string) ($lin['nombreempresa'] ?? ''),
                ]);
            }

            $cerrarDia();

            if ($soloTotales && $imprimio) {
                $filas[] = self::fila('total_articulo', [
                    'texto' => 'Total '.$sku.' '.trim((string) ($cab['descripcion'] ?? '')),
                    'entrada' => $artEntrada,
                    'salida' => $artSalida,
                    'saldo' => $artEntrada - $artSalida,
                    'importe' => $artImporte,
                    'articulo_id' => $articuloId,
                    'nombreempresa' => (string) ($cab['nombreempresa'] ?? ''),
                ]);
            }

            if (! $imprimio) {
                while ($filas !== []) {
                    $ultima = $filas[array_key_last($filas)];
                    $tipoUlt = $ultima->tipo ?? '';
                    if (($tipoUlt === 'encabezado' || $tipoUlt === 'saldo_inicial')
                        && (int) ($ultima->articulo_id ?? 0) === $articuloId) {
                        array_pop($filas);
                        continue;
                    }
                    break;
                }
            }
        }

        if ($nMov > 0) {
            $filas[] = self::fila('total_general', [
                'texto' => 'Total general',
                'entrada' => $totEntrada,
                'salida' => $totSalida,
                'saldo' => $totEntrada - $totSalida,
                'importe' => $totImporte,
            ]);
        }

        return [
            'filas' => $filas,
            'totales' => [
                'entrada' => $totEntrada,
                'salida' => $totSalida,
                'saldo' => $totEntrada - $totSalida,
                'importe' => $totImporte,
                'movimientos' => $nMov,
                'articulos' => count($porArticulo),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function fila(string $tipo, array $datos): object
    {
        $datos['tipo'] = $tipo;

        return (object) $datos;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compararLineas(array $a, array $b): int
    {
        $cmp = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string) ($a['tip'] ?? ''), (string) ($b['tip'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string) ($a['numero'] ?? ''), (string) ($b['numero'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = ((int) ($a['articulo_movimiento_id'] ?? 0)) <=> ((int) ($b['articulo_movimiento_id'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }

        return self::talleOrden((string) ($a['talle'] ?? '')) <=> self::talleOrden((string) ($b['talle'] ?? ''));
    }

    private static function talleOrden(string $talle): int
    {
        $talle = trim($talle);
        if ($talle !== '' && ctype_digit($talle)) {
            return (int) $talle;
        }
        if (preg_match('/(\d+)/', $talle, $m) === 1) {
            return (int) $m[1];
        }

        return 9999;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private static function leerMovimientos(array $filtros, string $fechaDesde, string $fechaHasta): array
    {
        $fecha = self::sqlFechaInforme();
        $rows = self::queryBase($filtros)
            ->whereRaw($fecha.' >= ?', [$fechaDesde])
            ->whereRaw($fecha.' <= ?', [$fechaHasta])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $linea = self::lineaDesdeRow($row);
            if ($linea === null) {
                continue;
            }
            $out[] = $linea;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array{cantidad: float, importe: float}>
     */
    private static function leerAperturas(array $filtros, string $fechaDesde): array
    {
        $importeExpr = 'COALESCE(amt.cantidad, am.cantidad) * '
            .'CASE WHEN COALESCE(amt.precio, 0) > 0 THEN amt.precio ELSE am.precio END * '
            .'(1 - COALESCE(am.descuento, 0) / 100) * (1 - COALESCE(v.descuento, 0) / 100)';

        $rows = self::queryBase($filtros)
            ->whereRaw(self::sqlFechaInforme().' < ?', [$fechaDesde])
            ->select('am.articulo_id')
            ->selectRaw('SUM(COALESCE(amt.cantidad, am.cantidad)) as cantidad')
            ->selectRaw('SUM('.$importeExpr.') as importe')
            ->groupBy('am.articulo_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->articulo_id] = [
                'cantidad' => (float) ($row->cantidad ?? 0),
                'importe' => (float) ($row->importe ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function queryBase(array $filtros)
    {
        $query = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->leftJoin('categoria as cat', 'cat.id', '=', 'a.categoria_id')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'a.unidadmedida_id')
            ->leftJoin('depmae as dep', 'dep.id', '=', 'am.deposito_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', 'dep.empresa_id')
            ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
            ->leftJoin('color as col', 'col.id', '=', 'am.color_id')
            ->leftJoin('articulo_movimiento_talle as amt', function ($join) {
                $join->on('amt.articulo_movimiento_id', '=', 'am.id')
                    ->whereRaw('ABS(amt.cantidad) > 0.000001');
            })
            ->leftJoin('talle as t_amt', 't_amt.id', '=', 'amt.talle_id')
            ->leftJoin('talle as t_am', 't_am.id', '=', 'am.talle_id')
            ->leftJoin('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'am.tipotransaccion_id')
            ->leftJoin('venta as v', 'v.id', '=', 'am.venta_id')
            ->leftJoin('tipotransaccion as ttv', 'ttv.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('cliente as cli', 'cli.id', '=', 'v.cliente_id')
            ->leftJoin('movimientostock as ms', 'ms.id', '=', 'am.movimientostock_id')
            ->select([
                'am.id as am_id',
                'am.articulo_id',
                'am.fecha',
                DB::raw(self::sqlFechaInforme().' as fecha_informe'),
                'am.cantidad as am_cantidad',
                'am.precio as am_precio',
                'am.descuento as am_descuento',
                'am.concepto',
                'am.numeroparte',
                'am.venta_id',
                'am.movimientostock_id',
                'amt.id as amt_id',
                'amt.cantidad as amt_cantidad',
                'amt.precio as amt_precio',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'cat.codigo as agrupacion',
                'um.abreviatura as umd',
                'dep.codigo as deposito_codigo',
                'emp.nombre as nombreempresa',
                'c.codigo as combinacion_codigo',
                'c.nombre as combinacion_nombre',
                'col.nombre as color_nombre',
                't_amt.codigo as talle_amt_codigo',
                't_amt.nombre as talle_amt_nombre',
                't_am.codigo as talle_am_codigo',
                't_am.nombre as talle_am_nombre',
                'v.codigo as venta_codigo',
                'v.descuento as venta_descuento',
                'v.nombre as venta_nombre',
                'ttv.abreviatura as tipo_venta',
                'ts.abreviatura as tipo_stock',
                'tt.abreviatura as tipo_transaccion',
                'cli.codigo as cliente_codigo',
                'cli.nombre as cliente_nombre',
                'ms.codigo as movimiento_codigo',
            ]);

        self::aplicarFiltros($query, $filtros);

        return $query;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarFiltros($query, array $filtros): void
    {
        $ids = MovimientosArticuloDepositoSupport::idsDepositosConsultables();
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (is_array($ids)) {
            $query->whereIn('am.deposito_id', $ids);
        }

        self::aplicarRangoCodigo($query, 'a.sku', (string) ($filtros['desde_sku'] ?? ''), (string) ($filtros['hasta_sku'] ?? ''), true);
        self::aplicarRangoCodigo($query, 'c.codigo', (string) ($filtros['desde_combinacion'] ?? ''), (string) ($filtros['hasta_combinacion'] ?? ''), true);
        self::aplicarRangoCodigo($query, 'dep.codigo', (string) ($filtros['desde_deposito'] ?? ''), (string) ($filtros['hasta_deposito'] ?? ''), true);

        $tipos = self::tipos($filtros['tipos'] ?? '');
        if ($tipos !== []) {
            $query->where(function ($q) use ($tipos) {
                $q->whereIn('ttv.abreviatura', $tipos)
                    ->orWhere(function ($q2) use ($tipos) {
                        $q2->whereNull('v.id')
                            ->where(function ($q3) use ($tipos) {
                                $q3->whereIn('ts.abreviatura', $tipos)
                                    ->orWhereIn('tt.abreviatura', $tipos);
                            });
                    });
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarRangoCodigo($query, string $columna, string $desde, string $hasta, bool $quitarCeros): void
    {
        $desdeOrig = trim($desde);
        $hastaOrig = trim($hasta);
        $desde = $desdeOrig;
        $hasta = $hastaOrig;
        if ($quitarCeros) {
            $desde = ltrim($desde, '0');
            $hasta = ltrim($hasta, '0');
            if ($desde === '' && $desdeOrig !== '') {
                $desde = '0';
            }
            if ($hasta === '' && $hastaOrig !== '') {
                $hasta = '0';
            }
        }
        if ($desde === '' && $hasta === '') {
            return;
        }

        $ambosNumericos = ($desde === '' || ctype_digit($desde)) && ($hasta === '' || ctype_digit($hasta))
            && ($desde !== '' || $hasta !== '');
        $colNum = $ambosNumericos && ($desde !== '' && $hasta !== '' && ctype_digit($desde) && ctype_digit($hasta));

        if ($colNum) {
            $expr = SqlDialectSupport::castEntero($columna);
            $query->whereRaw($expr.' >= ?', [(int) $desde]);
            $query->whereRaw($expr.' <= ?', [(int) $hasta]);

            return;
        }

        $exprTxt = $quitarCeros
            ? 'TRIM(LEADING \'0\' FROM '.$columna.')'
            : $columna;
        if ($desde !== '') {
            $query->whereRaw($exprTxt.' >= ?', [$desde]);
        }
        if ($hasta !== '') {
            $query->whereRaw($exprTxt.' <= ?', [$hasta]);
        }
    }

    /**
     * @return list<string>
     */
    private static function tipos(mixed $texto): array
    {
        $partes = preg_split('/[,\s;]+/', strtoupper(trim((string) $texto))) ?: [];
        $out = [];
        foreach ($partes as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function lineaDesdeRow(object $row): ?array
    {
        $esTalle = $row->amt_id !== null;
        $cantidad = $esTalle ? (float) ($row->amt_cantidad ?? 0) : (float) ($row->am_cantidad ?? 0);
        if (abs($cantidad) < 0.0000001) {
            return null;
        }

        $precio = $esTalle && (float) ($row->amt_precio ?? 0) > 0
            ? (float) $row->amt_precio
            : (float) ($row->am_precio ?? 0);
        $neto = self::precioNeto($precio, (float) ($row->am_descuento ?? 0), (float) ($row->venta_descuento ?? 0));

        $talle = $esTalle
            ? self::etiquetaTalle($row->talle_amt_codigo ?? null, $row->talle_amt_nombre ?? null)
            : self::etiquetaTalle($row->talle_am_codigo ?? null, $row->talle_am_nombre ?? null);

        $color = trim((string) ($row->combinacion_nombre ?? ''));
        if ($color === '') {
            $color = trim((string) ($row->color_nombre ?? ''));
        }

        $tip = trim((string) ($row->tipo_venta ?? ''));
        if ($tip === '' || $row->venta_id === null) {
            $tip = trim((string) ($row->tipo_stock ?: $row->tipo_transaccion ?: ''));
        }

        $clienteNombre = trim((string) ($row->cliente_nombre ?? ''));
        if ($clienteNombre === '') {
            $clienteNombre = trim((string) ($row->venta_nombre ?? ''));
        }

        return [
            'articulo_id' => (int) $row->articulo_id,
            'articulo_movimiento_id' => (int) $row->am_id,
            'sku' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->articulo_descripcion ?? ''),
            'agrupacion' => (string) ($row->agrupacion ?? ''),
            'umd' => (string) ($row->umd ?? ''),
            'fecha' => self::fechaYmd($row->fecha_informe ?? $row->fecha ?? null),
            'tip' => $tip,
            'numero' => self::numeroComprobante($row->venta_codigo ?? null, $row->movimiento_codigo ?? null, $row->concepto ?? null),
            'cantidad' => $cantidad,
            'importe' => $cantidad * $neto,
            'cliente_codigo' => (string) ($row->cliente_codigo ?? ''),
            'cliente_nombre' => $clienteNombre,
            'deposito' => (string) ($row->deposito_codigo ?? ''),
            'partida' => trim((string) ($row->numeroparte ?? '')),
            'combinacion' => trim((string) ($row->combinacion_codigo ?? '')),
            'color' => $color,
            'talle' => $talle,
            'venta_id' => (int) ($row->venta_id ?? 0),
            'movimientostock_id' => (int) ($row->movimientostock_id ?? 0),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'concepto' => trim((string) ($row->concepto ?? '')),
        ];
    }

    private static function etiquetaTalle(mixed $codigo, mixed $nombre): string
    {
        $nombre = trim((string) $nombre);
        if ($nombre !== '') {
            return $nombre;
        }

        return trim((string) $codigo);
    }
}
