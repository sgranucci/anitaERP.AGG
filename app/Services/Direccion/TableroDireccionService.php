<?php

namespace App\Services\Direccion;

use App\Models\Caja\Cheque;
use App\Support\Caja\ChequeListadoFiltros;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Totales del tablero de dirección.
 *
 * Ventas, compras e IVA salen de los comprobantes (con el signo del tipo).
 * Cheques usan el mismo criterio que el listado de cartera / para depositar.
 * La deuda es el saldo pendiente de la cuenta corriente al cierre del período.
 * Los importes se expresan en pesos: cotización del documento, o la vigente si vino en cero.
 */
class TableroDireccionService
{
    private const TOP = 8;

    private const DETALLE = 40;

    private int $cotizacionesAjenas = 0;

    private int $sinCotizacion = 0;

    /** @var array<int, string> */
    private const MESES = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    /**
     * @param  list<int>  $empresaIds
     * @param  array{preset: string, desde: string, hasta: string, anterior_desde: string, anterior_hasta: string, etiqueta: string, etiqueta_anterior: string}  $periodo
     * @return array<string, mixed>
     */
    public function armar(array $empresaIds, array $periodo): array
    {
        $this->cotizacionesAjenas = 0;
        $this->sinCotizacion = 0;
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds)));

        $desde = $periodo['desde'];
        $hasta = $periodo['hasta'];
        $antDesde = $periodo['anterior_desde'];
        $antHasta = $periodo['anterior_hasta'];

        if ($empresaIds === []) {
            return $this->payload($periodo, $this->kpisVacios(), [], [], [], [], [], []);
        }

        $ventas = $this->flujoVentas($empresaIds, $desde, $hasta);
        $ventasAnt = $this->flujoVentas($empresaIds, $antDesde, $antHasta);
        $compras = $this->flujoCompras($empresaIds, $desde, $hasta);
        $comprasAnt = $this->flujoCompras($empresaIds, $antDesde, $antHasta);
        $iva = $this->flujoIva($empresaIds, $desde, $hasta);
        $ivaAnt = $this->flujoIva($empresaIds, $antDesde, $antHasta);

        $cartera = $this->stockCartera($empresaIds, $hasta);
        $carteraAnt = $this->stockCartera($empresaIds, $antHasta, true);
        $depositar = $this->paraDepositar($empresaIds, $this->referencia($hasta));
        $emitidos = $this->stockEmitidos($empresaIds, $hasta);
        $emitidosAnt = $this->stockEmitidos($empresaIds, $antHasta);
        $vencen = $this->emitidosQueVencen($empresaIds, $hasta);

        $deudaCli = $this->deuda($empresaIds, $hasta, 'cliente');
        $deudaCliAnt = $this->deuda($empresaIds, $antHasta, 'cliente');
        $deudaProv = $this->deuda($empresaIds, $hasta, 'proveedor');
        $deudaProvAnt = $this->deuda($empresaIds, $antHasta, 'proveedor');

        $kpis = [
            $this->kpi('ventas', 'Ventas', 'flujo', 'mas_es_mejor', $ventas, $ventasAnt, 'Comprobantes del libro IVA ventas. El total ya viene firmado.'),
            $this->kpi('compras', 'Compras', 'flujo', 'menos_es_mejor', $compras, $comprasAnt, 'Facturas de proveedor, más los remitos COM de movimientos de stock que no tienen esa factura cargada.', $this->extraCompras($compras)),
            $this->kpi('iva', 'IVA a pagar', 'flujo', 'menos_es_mejor', $iva, $ivaAnt, 'IVA débito de ventas menos crédito fiscal de compras.'),
            $this->kpi(
                'cartera',
                'Cheques en cartera',
                'stock',
                'mas_es_mejor',
                $cartera,
                $carteraAnt,
                'Misma cartera que el listado de cheques. '.$depositar['cantidad'].' para depositar ('.$this->dinero($depositar['monto']).').',
                $depositar['cantidad'].' para depositar'
            ),
            $this->kpi(
                'emitidos',
                'Cheques propios',
                'stock',
                'menos_es_mejor',
                $emitidos,
                $emitidosAnt,
                'Solo cheques propios posdatados, con vencimiento de hoy en adelante.',
                $vencen['cantidad'].' vencen en 7 días'
            ),
            $this->kpi('deuda_clientes', 'Deuda de clientes', 'stock', 'menos_es_mejor', $deudaCli, $deudaCliAnt, 'Saldo pendiente de cuenta corriente al cierre.'),
            $this->kpi('deuda_proveedores', 'Deuda a proveedores', 'stock', 'menos_es_mejor', $deudaProv, $deudaProvAnt, 'Saldo pendiente de cuenta corriente al cierre.'),
        ];

        return $this->payload(
            $periodo,
            $kpis,
            $this->serieMensual($empresaIds, $hasta),
            $this->rankingClientes($empresaIds, $desde, $hasta),
            $this->rankingProveedores($empresaIds, $desde, $hasta),
            $this->rankingArticulosVendidos($empresaIds, $desde, $hasta),
            $this->rankingArticulosComprados($empresaIds, $desde, $hasta),
            $this->carteraPorVencimiento($empresaIds, $hasta),
            [
                'para_depositar' => $depositar['cantidad'],
                'vencen_semana' => $vencen['cantidad'],
            ]
        );
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array{desde: string, hasta: string, anterior_desde: string, anterior_hasta: string}  $periodo
     * @return array<string, mixed>
     */
    public function detalle(string $bloque, array $empresaIds, array $periodo): array
    {
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds)));
        $desde = $periodo['desde'];
        $hasta = $periodo['hasta'];

        return match ($bloque) {
            'ventas' => $this->detalleVentas($empresaIds, $desde, $hasta),
            'compras' => $this->detalleCompras($empresaIds, $desde, $hasta),
            'iva' => $this->detalleIva($empresaIds, $desde, $hasta),
            'cartera' => $this->detalleCartera($empresaIds, $hasta),
            'emitidos' => $this->detalleEmitidos($empresaIds, $hasta),
            'deuda_clientes' => $this->detalleDeuda($empresaIds, $hasta, 'cliente'),
            'deuda_proveedores' => $this->detalleDeuda($empresaIds, $hasta, 'proveedor'),
            default => ['titulo' => 'Sin detalle', 'nota' => '', 'secciones' => []],
        };
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function flujoVentas(array $empresaIds, string $desde, string $hasta): array
    {
        $q = $this->ventasBase($empresaIds, $desde, $hasta);

        return [
            'monto' => $this->sumarLocal((clone $q), 'v.moneda_id', 'v.cotizacion', 'v.fecha', 'v.total'),
            'cantidad' => (int) (clone $q)->count(),
        ];
    }

    /**
     * Facturas de proveedor más COM de stock que no están en una factura.
     * El remito COM y la factura de la misma compra no se suman las dos veces:
     * si la leyenda cita FAC sucursal-número y esa factura existe, el COM no entra al total.
     *
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int, com_cantidad: int, com_sin_factura: float, com_sin_factura_cantidad: int}
     */
    private function flujoCompras(array $empresaIds, string $desde, string $hasta): array
    {
        $q = $this->comprasBase($empresaIds, $desde, $hasta);
        $com = $this->comStock($empresaIds, $desde, $hasta);

        return [
            'monto' => round(
                $this->sumarLocal((clone $q), 'c.moneda_id', 'c.cotizacion', 'c.fechacomprobante', '(c.total * tt.signo)')
                + $com['sin_factura'],
                2
            ),
            'cantidad' => (int) (clone $q)->count(),
            'com_cantidad' => $com['cantidad'],
            'com_sin_factura' => $com['sin_factura'],
            'com_sin_factura_cantidad' => $com['sin_factura_cantidad'],
        ];
    }

    /**
     * @param  array{cantidad?: int, com_cantidad?: int, com_sin_factura_cantidad?: int}  $compras
     */
    private function extraCompras(array $compras): string
    {
        $facturas = (int) ($compras['cantidad'] ?? 0);
        $com = (int) ($compras['com_cantidad'] ?? 0);
        $sin = (int) ($compras['com_sin_factura_cantidad'] ?? 0);
        $texto = $facturas.' facturas';
        if ($com === 0) {
            return $texto;
        }
        $texto .= ' · '.$com.' COM en stock';
        if ($sin > 0) {
            $texto .= ' ('.$sin.' sin factura, suman en el total)';
        } else {
            $texto .= ', ya incluidos en las facturas';
        }

        return $texto;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function flujoIva(array $empresaIds, string $desde, string $hasta): array
    {
        $debito = $this->sumarLocal(
            $this->ivaDebitoBase($empresaIds, $desde, $hasta),
            'v.moneda_id',
            'v.cotizacion',
            'v.fecha',
            '(vi.importe * tt.signo)'
        );
        $credito = $this->sumarLocal(
            $this->ivaCreditoBase($empresaIds, $desde, $hasta),
            'c.moneda_id',
            'c.cotizacion',
            'c.fechacomprobante',
            '(cc.monto * tt.signo)'
        );

        return [
            'monto' => round($debito - $credito, 2),
            'cantidad' => 0,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function stockCartera(array $empresaIds, string $hasta, bool $forzarHistorico = false): array
    {
        $q = $this->carteraQuery($empresaIds);
        if ($forzarHistorico || ! $this->esHoyOFuturo($hasta)) {
            $q->whereDate('cheque.fechaemision', '<=', $hasta)
                ->where(function ($w) use ($hasta) {
                    $w->whereNull('cheque.fecha_deposito')
                        ->orWhereDate('cheque.fecha_deposito', '>', $hasta);
                });
        }

        return [
            'monto' => $this->sumarCheque($q),
            'cantidad' => (int) (clone $q)->count(),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function paraDepositar(array $empresaIds, string $hasta): array
    {
        $q = Cheque::query()->whereIn('cheque.empresa_id', $empresaIds);
        ChequeListadoFiltros::aplicar($q, [
            'para_depositar' => true,
            'para_depositar_hasta' => $hasta,
        ]);

        return [
            'monto' => $this->sumarCheque($q),
            'cantidad' => (int) (clone $q)->count(),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function stockEmitidos(array $empresaIds, string $hasta): array
    {
        $q = $this->emitidosQuery($empresaIds, $hasta);

        return [
            'monto' => $this->sumarCheque($q, 'cheque.fechaemision'),
            'cantidad' => (int) (clone $q)->count(),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function emitidosQueVencen(array $empresaIds, string $hasta): array
    {
        $ref = Carbon::parse($this->referencia($hasta));
        $q = $this->emitidosQuery($empresaIds, $hasta)
            ->whereDate('cheque.fechapago', '>=', $ref->toDateString())
            ->whereDate('cheque.fechapago', '<=', $ref->copy()->addDays(7)->toDateString());

        return [
            'monto' => $this->sumarCheque($q),
            'cantidad' => (int) (clone $q)->count(),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{monto: float, cantidad: int}
     */
    private function deuda(array $empresaIds, string $hasta, string $lado): array
    {
        $importe = $this->exprSaldoCc($lado);
        $q = $this->deudaBase($empresaIds, $hasta, $lado);
        $idCol = $lado === 'cliente' ? 'cc.cliente_id' : 'cc.proveedor_id';
        $moneda = 'cc.moneda_id';
        $cot = 'cc.cotizacion';

        $monto = $this->sumarLocal((clone $q), $moneda, $cot, 'cc.fecha', $importe);
        $cantidad = (int) DB::query()->fromSub(
            (clone $q)->select($idCol)->groupBy($idCol)->havingRaw('ABS(SUM('.$importe.')) > 0.009'),
            'saldos'
        )->count();

        return ['monto' => $monto, 'cantidad' => $cantidad];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{mes: string, etiqueta: string, ventas: float, compras: float}>
     */
    private function serieMensual(array $empresaIds, string $hasta): array
    {
        $fin = Carbon::parse($hasta)->startOfMonth();
        $inicio = $fin->copy()->subMonths(11);
        $ventas = $this->mesesAgrupados(
            $this->ventasBase($empresaIds, $inicio->toDateString(), $hasta),
            'v.fecha',
            'v.moneda_id',
            'v.cotizacion',
            'v.total'
        );
        $compras = $this->mesesAgrupados(
            $this->comprasBase($empresaIds, $inicio->toDateString(), $hasta),
            'c.fechacomprobante',
            'c.moneda_id',
            'c.cotizacion',
            '(c.total * tt.signo)'
        );

        $serie = [];
        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $clave = $cursor->format('Y-m');
            $serie[] = [
                'mes' => $clave,
                'etiqueta' => self::MESES[(int) $cursor->format('n')].' '.$cursor->format('y'),
                'ventas' => round((float) ($ventas[$clave] ?? 0), 2),
                'compras' => round((float) ($compras[$clave] ?? 0), 2),
            ];
            $cursor->addMonth();
        }

        return $serie;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{nombre: string, monto: float, monto_fmt: string}>
     */
    private function rankingClientes(array $empresaIds, string $desde, string $hasta): array
    {
        $expr = $this->exprLocal('v.moneda_id', 'v.cotizacion', 'v.total');
        $filas = $this->ventasBase($empresaIds, $desde, $hasta)
            ->join('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->groupBy('v.cliente_id', 'cl.nombre')
            ->selectRaw('cl.nombre as nombre, SUM('.$expr.') as monto')
            ->orderByDesc('monto')
            ->limit(self::TOP)
            ->get();

        return $this->mapRanking($filas);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{nombre: string, monto: float, monto_fmt: string}>
     */
    private function rankingProveedores(array $empresaIds, string $desde, string $hasta): array
    {
        $expr = $this->exprLocal('c.moneda_id', 'c.cotizacion', '(c.total * tt.signo)');
        $filas = $this->comprasBase($empresaIds, $desde, $hasta)
            ->join('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->groupBy('c.proveedor_id', 'pr.nombre')
            ->selectRaw('pr.nombre as nombre, SUM('.$expr.') as monto')
            ->orderByDesc('monto')
            ->limit(self::TOP)
            ->get();

        return $this->mapRanking($filas);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{sku: string, nombre: string, cantidad: float, monto: float, monto_fmt: string}>
     */
    private function rankingArticulosVendidos(array $empresaIds, string $desde, string $hasta): array
    {
        $expr = $this->exprLocal('v.moneda_id', 'v.cotizacion', '(e.cantidad * e.precio * tt.signo)');
        $filas = DB::table('venta_emision as e')
            ->join('venta as v', 'v.id', '=', 'e.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('articulo as a', 'a.id', '=', 'e.articulo_id')
            ->where('tt.iva_ventas', 1)
            ->whereIn('pv.empresa_id', $empresaIds)
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->whereNotNull('e.articulo_id')
            ->groupBy('e.articulo_id', 'a.sku', 'a.descripcion')
            ->selectRaw('a.sku as sku, a.descripcion as nombre, SUM(e.cantidad * tt.signo) as cantidad, SUM('.$expr.') as monto')
            ->orderByDesc('monto')
            ->limit(self::TOP)
            ->get();

        return $this->mapArticulos($filas);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{sku: string, nombre: string, cantidad: float, monto: float, monto_fmt: string}>
     */
    private function rankingArticulosComprados(array $empresaIds, string $desde, string $hasta): array
    {
        $filas = DB::table('articulo_movimiento as am')
            ->join('movimientostock as m', 'm.id', '=', 'am.movimientostock_id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'm.tipotransaccion_stock_id')
            ->join('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->where('ts.abreviatura', 'COM')
            ->whereIn('d.empresa_id', $empresaIds)
            ->whereBetween('m.fecha', [$desde, $hasta])
            ->whereNotNull('am.articulo_id')
            ->groupBy('am.articulo_id', 'a.sku', 'a.descripcion')
            ->selectRaw('a.sku as sku, a.descripcion as nombre, SUM(am.cantidad) as cantidad, SUM(am.cantidad * am.precio) as monto')
            ->orderByDesc('monto')
            ->limit(self::TOP)
            ->get();

        return $this->mapArticulos($filas);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array{etiqueta: string, monto: float, cantidad: int}>
     */
    private function carteraPorVencimiento(array $empresaIds, string $hasta): array
    {
        $ref = Carbon::parse($this->referencia($hasta));
        $q = $this->carteraQuery($empresaIds);
        if (! $this->esHoyOFuturo($hasta)) {
            $q->whereDate('cheque.fechaemision', '<=', $hasta)
                ->where(function ($w) use ($hasta) {
                    $w->whereNull('cheque.fecha_deposito')
                        ->orWhereDate('cheque.fecha_deposito', '>', $hasta);
                });
        }

        $filas = (clone $q)->get(['cheque.fechapago', 'cheque.monto', 'cheque.moneda_id', 'cheque.cotizacion', 'cheque.fechaemision']);
        $buckets = [
            'vencidos' => ['etiqueta' => 'Vencidos', 'monto' => 0.0, 'cantidad' => 0],
            'semana' => ['etiqueta' => 'En 7 días', 'monto' => 0.0, 'cantidad' => 0],
            'mes' => ['etiqueta' => '8 a 30 días', 'monto' => 0.0, 'cantidad' => 0],
            'mas' => ['etiqueta' => 'Más de 30', 'monto' => 0.0, 'cantidad' => 0],
        ];
        foreach ($filas as $fila) {
            $pago = $fila->fechapago ? Carbon::parse($fila->fechapago)->startOfDay() : null;
            $clave = 'mas';
            if ($pago === null || $pago->lt($ref)) {
                $clave = 'vencidos';
            } elseif ($pago->lte($ref->copy()->addDays(7))) {
                $clave = 'semana';
            } elseif ($pago->lte($ref->copy()->addDays(30))) {
                $clave = 'mes';
            }
            $buckets[$clave]['cantidad']++;
            $buckets[$clave]['monto'] += $this->aLocal(
                (float) $fila->monto,
                (int) $fila->moneda_id,
                (float) ($fila->cotizacion ?? 0),
                (string) ($fila->fechaemision ?? $ref->toDateString())
            );
        }

        return array_values($buckets);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleVentas(array $empresaIds, string $desde, string $hasta): array
    {
        if ($empresaIds === []) {
            return $this->detalleVacio('Ventas');
        }
        $filas = $this->ventasBase($empresaIds, $desde, $hasta)
            ->join('cliente as cl', 'cl.id', '=', 'v.cliente_id')
            ->orderByDesc('v.fecha')
            ->orderByDesc('v.id')
            ->limit(self::DETALLE)
            ->get([
                'v.id', 'v.fecha', 'v.numerocomprobante', 'v.total', 'v.moneda_id', 'v.cotizacion',
                'tt.abreviatura', 'tt.signo', 'cl.nombre as cliente', 'cl.id as cliente_id',
            ]);

        return [
            'titulo' => 'Ventas del período',
            'nota' => 'Los '.$filas->count().' comprobantes más recientes. El total de la tarjeta suma todo el período.',
            'secciones' => [[
                'titulo' => '',
                'columnas' => ['Fecha', 'Comprobante', 'Cliente', 'Importe'],
                'filas' => $filas->map(function ($fila) {
                    $importe = $this->aLocal((float) $fila->total, (int) $fila->moneda_id, (float) $fila->cotizacion, (string) $fila->fecha);

                    return $this->fila(
                        [$this->fechaCorta($fila->fecha), trim($fila->abreviatura.' '.$fila->numerocomprobante), (string) $fila->cliente, $this->dinero($importe)],
                        route('editar_cliente', $fila->cliente_id).'?origen=modal_consulta&vista=consulta'
                    );
                })->all(),
            ]],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleCompras(array $empresaIds, string $desde, string $hasta): array
    {
        if ($empresaIds === []) {
            return $this->detalleVacio('Compras');
        }
        $filas = $this->comprasBase($empresaIds, $desde, $hasta)
            ->join('proveedor as pr', 'pr.id', '=', 'c.proveedor_id')
            ->orderByDesc('c.fechacomprobante')
            ->orderByDesc('c.id')
            ->limit(self::DETALLE)
            ->get([
                'c.id', 'c.fechacomprobante', 'c.letra', 'c.sucursal', 'c.numerocomprobante',
                'c.total', 'c.moneda_id', 'c.cotizacion', 'tt.abreviatura', 'tt.signo', 'pr.nombre as proveedor',
            ]);

        $com = $this->comStock($empresaIds, $desde, $hasta);

        return [
            'titulo' => 'Compras del período',
            'nota' => 'Las facturas de proveedor arman el total. Un COM de movimientos de stock suma solo cuando no hay factura con el número que cita la leyenda (FAC sucursal-número).',
            'secciones' => [
                [
                    'titulo' => 'Facturas de proveedor',
                    'columnas' => ['Fecha', 'Comprobante', 'Proveedor', 'Importe'],
                    'filas' => $filas->map(function ($fila) {
                        $importe = $this->aLocal((float) $fila->total * (float) $fila->signo, (int) $fila->moneda_id, (float) $fila->cotizacion, (string) $fila->fechacomprobante);
                        $nro = trim($fila->abreviatura.' '.$fila->letra.' '.str_pad((string) $fila->sucursal, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $fila->numerocomprobante, 8, '0', STR_PAD_LEFT));

                        return $this->fila(
                            [$this->fechaCorta($fila->fechacomprobante), $nro, (string) $fila->proveedor, $this->dinero($importe)],
                            route('editar_comprobante_proveedor', $fila->id).'?origen=modal_consulta&vista=consulta'
                        );
                    })->all(),
                ],
                [
                    'titulo' => 'COM de movimientos de stock',
                    'columnas' => ['Fecha', 'Movimiento', 'Leyenda', 'Situación', 'Importe'],
                    'filas' => array_map(function (array $mov) {
                        return $this->fila(
                            [
                                $this->fechaCorta($mov['fecha']),
                                (string) $mov['codigo'],
                                (string) $mov['leyenda'],
                                $mov['facturado'] ? 'Ya está en una factura' : 'Sin factura, suma en el total',
                                $this->dinero($mov['importe']),
                            ],
                            route('editar_movimientostock', $mov['id']).'?origen=modal_consulta&vista=consulta'
                        );
                    }, array_slice($com['movimientos'], 0, self::DETALLE)),
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleIva(array $empresaIds, string $desde, string $hasta): array
    {
        if ($empresaIds === []) {
            return $this->detalleVacio('IVA');
        }
        $debito = $this->ivaDebitoBase($empresaIds, $desde, $hasta)
            ->orderByDesc('v.fecha')
            ->limit(self::DETALLE)
            ->get(['v.fecha', 'v.numerocomprobante', 'vi.concepto', 'vi.importe', 'v.moneda_id', 'v.cotizacion', 'tt.abreviatura', 'tt.signo']);
        $credito = $this->ivaCreditoBase($empresaIds, $desde, $hasta)
            ->orderByDesc('c.fechacomprobante')
            ->limit(self::DETALLE)
            ->get(['c.fechacomprobante', 'c.numerocomprobante', 'ci.nombre as concepto', 'cc.monto', 'c.moneda_id', 'c.cotizacion', 'tt.abreviatura', 'tt.signo']);

        return [
            'titulo' => 'IVA del período',
            'nota' => 'Débito: conceptos de venta que empiezan con IVA y no son percepciones. Crédito: conceptos de compra IVA (no percepciones), con el signo del comprobante.',
            'secciones' => [
                [
                    'titulo' => 'IVA débito',
                    'columnas' => ['Fecha', 'Comprobante', 'Concepto', 'Importe'],
                    'filas' => $debito->map(function ($fila) {
                        $importe = $this->aLocal((float) $fila->importe * (float) $fila->signo, (int) $fila->moneda_id, (float) $fila->cotizacion, (string) $fila->fecha);

                        return $this->fila([$this->fechaCorta($fila->fecha), trim($fila->abreviatura.' '.$fila->numerocomprobante), (string) $fila->concepto, $this->dinero($importe)]);
                    })->all(),
                ],
                [
                    'titulo' => 'IVA crédito',
                    'columnas' => ['Fecha', 'Comprobante', 'Concepto', 'Importe'],
                    'filas' => $credito->map(function ($fila) {
                        $importe = $this->aLocal((float) $fila->monto * (float) $fila->signo, (int) $fila->moneda_id, (float) $fila->cotizacion, (string) $fila->fechacomprobante);

                        return $this->fila([$this->fechaCorta($fila->fechacomprobante), trim($fila->abreviatura.' '.$fila->numerocomprobante), (string) $fila->concepto, $this->dinero($importe)]);
                    })->all(),
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleCartera(array $empresaIds, string $hasta): array
    {
        if ($empresaIds === []) {
            return $this->detalleVacio('Cartera');
        }
        $q = $this->carteraQuery($empresaIds)->orderBy('cheque.fechapago')->limit(self::DETALLE);
        $filas = $q->get(['cheque.id', 'cheque.numerocheque', 'cheque.fechapago', 'cheque.monto', 'cheque.moneda_id', 'cheque.cotizacion', 'cheque.fechaemision', 'cheque.entregado']);

        return [
            'titulo' => 'Cheques en cartera',
            'nota' => 'Para depositar los que ya vencieron, usá el atajo Depositar cheques.',
            'secciones' => [[
                'titulo' => '',
                'columnas' => ['Pago', 'Número', 'Entregado por', 'Importe'],
                'filas' => $filas->map(function ($fila) {
                    $importe = $this->aLocal((float) $fila->monto, (int) $fila->moneda_id, (float) ($fila->cotizacion ?? 0), (string) ($fila->fechaemision ?? $fila->fechapago));

                    return $this->fila(
                        [$this->fechaCorta($fila->fechapago), (string) $fila->numerocheque, (string) $fila->entregado, $this->dinero($importe)],
                        route('editar_cheque', $fila->id).'?origen=modal_consulta&vista=consulta'
                    );
                })->all(),
            ]],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleEmitidos(array $empresaIds, string $hasta): array
    {
        if ($empresaIds === []) {
            return $this->detalleVacio('Cheques propios');
        }
        $filas = $this->emitidosQuery($empresaIds, $hasta)
            ->orderBy('cheque.fechapago')
            ->orderBy('cheque.id')
            ->limit(self::DETALLE)
            ->get(['cheque.id', 'cheque.numerocheque', 'cheque.fechapago', 'cheque.monto', 'cheque.moneda_id', 'cheque.cotizacion', 'cheque.fechaemision', 'cheque.anombrede']);

        return [
            'titulo' => 'Cheques propios emitidos',
            'nota' => 'Posdatados o diferidos, con vencimiento de hoy en adelante. Los ya vencidos no entran.',
            'secciones' => [[
                'titulo' => '',
                'columnas' => ['Pago', 'Número', 'A nombre de', 'Importe'],
                'filas' => $filas->map(function ($fila) {
                    $importe = $this->aLocal((float) $fila->monto, (int) $fila->moneda_id, (float) ($fila->cotizacion ?? 0), (string) ($fila->fechaemision ?? $fila->fechapago));

                    return $this->fila(
                        [$this->fechaCorta($fila->fechapago), (string) $fila->numerocheque, (string) $fila->anombrede, $this->dinero($importe)],
                        route('editar_cheque', $fila->id).'?origen=modal_consulta&vista=consulta'
                    );
                })->all(),
            ]],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function detalleDeuda(array $empresaIds, string $hasta, string $lado): array
    {
        $titulo = $lado === 'cliente' ? 'Deuda de clientes' : 'Deuda a proveedores';
        if ($empresaIds === []) {
            return $this->detalleVacio($titulo);
        }
        $importe = $this->exprSaldoCc($lado);
        $idCol = $lado === 'cliente' ? 'cc.cliente_id' : 'cc.proveedor_id';
        $nombreTabla = $lado === 'cliente' ? 'cliente' : 'proveedor';
        $ruta = $lado === 'cliente' ? 'editar_cliente' : 'editar_proveedor';
        $expr = $this->exprLocal('cc.moneda_id', 'cc.cotizacion', $importe);

        $filas = $this->deudaBase($empresaIds, $hasta, $lado)
            ->join($nombreTabla.' as nom', 'nom.id', '=', $idCol)
            ->groupBy($idCol, 'nom.nombre')
            ->havingRaw('ABS(SUM('.$importe.')) > 0.009')
            ->selectRaw($idCol.' as entidad_id, nom.nombre as nombre, SUM('.$expr.') as monto')
            ->orderByDesc('monto')
            ->limit(self::DETALLE)
            ->get();

        return [
            'titulo' => $titulo,
            'nota' => 'Saldo pendiente al '.$this->fechaCorta($hasta).'. El listado completo está en el reporte de cuenta corriente.',
            'secciones' => [[
                'titulo' => '',
                'columnas' => [$lado === 'cliente' ? 'Cliente' : 'Proveedor', 'Saldo'],
                'filas' => $filas->map(function ($fila) use ($ruta) {
                    return $this->fila(
                        [(string) $fila->nombre, $this->dinero((float) $fila->monto)],
                        route($ruta, $fila->entidad_id).'?origen=modal_consulta&vista=consulta'
                    );
                })->all(),
            ]],
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function ventasBase(array $empresaIds, string $desde, string $hasta): Builder
    {
        return DB::table('venta as v')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('tt.iva_ventas', 1)
            ->whereIn('pv.empresa_id', $empresaIds)
            ->whereBetween('v.fecha', [$desde, $hasta]);
    }

    /**
     * Remitos COM cargados como movimiento de stock.
     *
     * @param  list<int>  $empresaIds
     * @return array{cantidad: int, sin_factura: float, sin_factura_cantidad: int, movimientos: list<array{id: int, fecha: string, codigo: string, leyenda: string, importe: float, facturado: bool}>}
     */
    private function comStock(array $empresaIds, string $desde, string $hasta): array
    {
        $vacio = ['cantidad' => 0, 'sin_factura' => 0.0, 'sin_factura_cantidad' => 0, 'movimientos' => []];
        if ($empresaIds === []) {
            return $vacio;
        }

        $lineas = DB::table('articulo_movimiento as am')
            ->join('movimientostock as m', 'm.id', '=', 'am.movimientostock_id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'm.tipotransaccion_stock_id')
            ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('ts.abreviatura', 'COM')
            ->whereIn('d.empresa_id', $empresaIds)
            ->whereBetween('m.fecha', [$desde, $hasta])
            ->get([
                'm.id', 'm.fecha', 'm.codigo', 'm.leyenda', 'm.movimientostock_origen_id',
                'am.cantidad', 'am.precio', 'am.moneda_id', 'd.empresa_id',
            ]);

        /** @var array<int, array{id: int, fecha: string, codigo: string, leyenda: string, origen_id: int, empresa_id: int, importe: float, facturado: bool}> $movimientos */
        $movimientos = [];
        foreach ($lineas as $linea) {
            $id = (int) $linea->id;
            if (! isset($movimientos[$id])) {
                $movimientos[$id] = [
                    'id' => $id,
                    'fecha' => (string) $linea->fecha,
                    'codigo' => (string) $linea->codigo,
                    'leyenda' => trim((string) $linea->leyenda),
                    'origen_id' => (int) ($linea->movimientostock_origen_id ?? 0),
                    'empresa_id' => (int) ($linea->empresa_id ?? 0),
                    'importe' => 0.0,
                    'facturado' => false,
                ];
            }
            $movimientos[$id]['importe'] += $this->aLocal(
                (float) $linea->cantidad * (float) $linea->precio,
                (int) ($linea->moneda_id ?: CotizacionVigenteSupport::MONEDA_LOCAL_ID),
                0.0,
                (string) $linea->fecha
            );
        }

        $facturados = $this->comConFactura($movimientos);
        $sinFactura = 0.0;
        $sinCantidad = 0;
        foreach ($movimientos as $id => $mov) {
            $movimientos[$id]['facturado'] = isset($facturados[$id]);
            $movimientos[$id]['importe'] = round($mov['importe'], 2);
            if (! isset($facturados[$id])) {
                $sinFactura += $movimientos[$id]['importe'];
                $sinCantidad++;
            }
        }

        uasort($movimientos, function (array $a, array $b): int {
            return [$b['fecha'], $b['id']] <=> [$a['fecha'], $a['id']];
        });

        return [
            'cantidad' => count($movimientos),
            'sin_factura' => round($sinFactura, 2),
            'sin_factura_cantidad' => $sinCantidad,
            'movimientos' => array_values(array_map(function (array $mov): array {
                return [
                    'id' => $mov['id'],
                    'fecha' => $mov['fecha'],
                    'codigo' => $mov['codigo'],
                    'leyenda' => $mov['leyenda'],
                    'importe' => $mov['importe'],
                    'facturado' => $mov['facturado'],
                ];
            }, $movimientos)),
        ];
    }

    /**
     * @param  array<int, array{id: int, leyenda: string, origen_id: int, empresa_id: int}>  $movimientos
     * @return array<int, true>
     */
    private function comConFactura(array $movimientos): array
    {
        $origenPendiente = [];
        $claves = [];
        foreach ($movimientos as $mov) {
            if ($mov['origen_id'] > 0) {
                $origenPendiente[$mov['id']] = $mov['origen_id'];
                continue;
            }
            $clave = $this->claveFacturaLeyenda($mov['leyenda'], $mov['empresa_id']);
            if ($clave !== null) {
                $claves[$mov['id']] = $clave;
            }
        }

        if ($origenPendiente !== []) {
            $origenes = DB::table('movimientostock as m')
                ->leftJoin('articulo_movimiento as am', 'am.movimientostock_id', '=', 'm.id')
                ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
                ->whereIn('m.id', array_values(array_unique($origenPendiente)))
                ->groupBy('m.id', 'm.leyenda')
                ->selectRaw('m.id, m.leyenda, MIN(d.empresa_id) as empresa_id')
                ->get();
            $origenClave = [];
            foreach ($origenes as $origen) {
                $clave = $this->claveFacturaLeyenda((string) $origen->leyenda, (int) $origen->empresa_id);
                if ($clave !== null) {
                    $origenClave[(int) $origen->id] = $clave;
                    $claves['origen-'.$origen->id] = $clave;
                }
            }
        } else {
            $origenClave = [];
        }

        $existentes = $this->facturasProveedorPorClave(array_values($claves));
        $marcados = [];
        foreach ($movimientos as $mov) {
            if ($mov['origen_id'] > 0) {
                $clave = $origenClave[$mov['origen_id']] ?? null;
            } else {
                $clave = $claves[$mov['id']] ?? null;
            }
            if ($clave !== null && isset($existentes[$clave])) {
                $marcados[$mov['id']] = true;
            }
        }

        return $marcados;
    }

    /**
     * @param  list<string>  $claves
     * @return array<string, true>
     */
    private function facturasProveedorPorClave(array $claves): array
    {
        $claves = array_values(array_unique(array_filter($claves)));
        if ($claves === []) {
            return [];
        }

        $query = DB::table('comprobante_proveedor');
        $query->where(function ($q) use ($claves) {
            foreach ($claves as $clave) {
                [$empresaId, $sucursal, $numero] = array_map('intval', explode('|', $clave));
                $q->orWhere(function ($w) use ($empresaId, $sucursal, $numero) {
                    $w->where('empresa_id', $empresaId)
                        ->where('sucursal', $sucursal)
                        ->where('numerocomprobante', $numero);
                });
            }
        });

        $existentes = [];
        foreach ($query->get(['empresa_id', 'sucursal', 'numerocomprobante']) as $fila) {
            $existentes[(int) $fila->empresa_id.'|'.(int) $fila->sucursal.'|'.(int) $fila->numerocomprobante] = true;
        }

        return $existentes;
    }

    private function claveFacturaLeyenda(string $leyenda, int $empresaId): ?string
    {
        if ($empresaId <= 0 || ! preg_match('/FAC\s*(\d+)\s*-\s*(\d+)/i', $leyenda, $coincide)) {
            return null;
        }

        return $empresaId.'|'.(int) $coincide[1].'|'.(int) $coincide[2];
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function comprasBase(array $empresaIds, string $desde, string $hasta): Builder
    {
        return DB::table('comprobante_proveedor as c')
            ->join('tipotransaccion_compra as tt', 'tt.id', '=', 'c.tipotransaccion_compra_id')
            ->whereIn('c.empresa_id', $empresaIds)
            ->whereBetween('c.fechacomprobante', [$desde, $hasta]);
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function ivaDebitoBase(array $empresaIds, string $desde, string $hasta): Builder
    {
        return DB::table('venta_impuesto as vi')
            ->join('venta as v', 'v.id', '=', 'vi.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->where('tt.iva_ventas', 1)
            ->whereIn('pv.empresa_id', $empresaIds)
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->where(function ($q) {
                $q->where('vi.concepto', 'like', 'IVA%')
                    ->orWhere('vi.concepto', 'like', 'Iva%');
            })
            ->where('vi.concepto', 'not like', '%ercep%');
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function ivaCreditoBase(array $empresaIds, string $desde, string $hasta): Builder
    {
        return DB::table('comprobante_proveedor_concepto as cc')
            ->join('comprobante_proveedor as c', 'c.id', '=', 'cc.comprobante_proveedor_id')
            ->join('tipotransaccion_compra as tt', 'tt.id', '=', 'c.tipotransaccion_compra_id')
            ->join('concepto_ivacompra as ci', 'ci.id', '=', 'cc.concepto_ivacompra_id')
            ->whereIn('c.empresa_id', $empresaIds)
            ->whereBetween('c.fechacomprobante', [$desde, $hasta])
            ->where(function ($q) {
                $q->where('ci.nombre', 'like', 'IVA %')
                    ->orWhere('ci.nombre', 'like', 'Concepto IVA%');
            })
            ->where('ci.nombre', 'not like', '%ercep%');
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function carteraQuery(array $empresaIds): EloquentBuilder
    {
        $q = Cheque::query()->whereIn('cheque.empresa_id', $empresaIds);
        ChequeListadoFiltros::aplicar($q, ['cartera' => true]);

        return $q;
    }

    /**
     * Cheques propios todavía en circulación: posdatados (fecha de pago posterior
     * a la emisión) con vencimiento desde hoy, o desde el cierre si el período ya pasó.
     *
     * @param  list<int>  $empresaIds
     */
    private function emitidosQuery(array $empresaIds, string $hasta): EloquentBuilder
    {
        $desde = $this->referencia($hasta);

        return Cheque::query()
            ->whereIn('cheque.empresa_id', $empresaIds)
            ->where('cheque.origen', 'E')
            ->where(function ($q) {
                $q->whereNull('cheque.estado')
                    ->orWhereNotIn('cheque.estado', ['A', 'R', 'C']);
            })
            ->whereDate('cheque.fechaemision', '<=', $hasta)
            ->whereColumn('cheque.fechapago', '>', 'cheque.fechaemision')
            ->whereDate('cheque.fechapago', '>=', $desde)
            ->where(function ($q) use ($hasta) {
                $q->whereNull('cheque.fecha_acreditacion')
                    ->orWhereDate('cheque.fecha_acreditacion', '>', $hasta);
            });
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function deudaBase(array $empresaIds, string $hasta, string $lado): Builder
    {
        if ($lado === 'cliente') {
            $aplicado = DB::table('cliente_cuentacorriente_aplicacion')
                ->whereDate('fecha', '<=', $hasta)
                ->select('cliente_cuentacorriente_id', DB::raw('SUM(total) as aplicado'))
                ->groupBy('cliente_cuentacorriente_id');

            return DB::table('cliente_cuentacorriente as cc')
                ->leftJoinSub($aplicado, 'ap', 'ap.cliente_cuentacorriente_id', '=', 'cc.id')
                ->whereIn('cc.empresa_id', $empresaIds)
                ->whereDate('cc.fecha', '<=', $hasta);
        }

        $aplicado = DB::table('proveedor_cuentacorriente_aplicacion')
            ->whereDate('fecha', '<=', $hasta)
            ->select('proveedor_cuentacorriente_id', DB::raw('SUM(total) as aplicado'))
            ->groupBy('proveedor_cuentacorriente_id');

        return DB::table('proveedor_cuentacorriente as cc')
            ->leftJoinSub($aplicado, 'ap', 'ap.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->whereIn('cc.empresa_id', $empresaIds)
            ->whereDate('cc.fecha', '<=', $hasta)
            ->whereRaw(SqlDialectSupport::sqlAlcanceDeudaAbiertaProveedorCc('cc'))
            ->whereRaw('ABS('.SqlDialectSupport::coalesce('ap.aplicado', '0').') < ABS(cc.total)')
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('proveedor as p')
                    ->whereColumn('p.id', 'cc.proveedor_id')
                    ->whereNull('p.deleted_at');
            });
    }

    private function exprSaldoCc(string $lado): string
    {
        return 'CASE WHEN cc.total >= 0 THEN GREATEST(0, cc.total + IFNULL(ap.aplicado, 0)) ELSE LEAST(0, cc.total + IFNULL(ap.aplicado, 0)) END';
    }

    private function sumarCheque(EloquentBuilder $query, string $fecha = 'cheque.fechaemision'): float
    {
        return $this->sumarLocal(
            (clone $query)->getQuery(),
            'cheque.moneda_id',
            'cheque.cotizacion',
            $fecha,
            'cheque.monto'
        );
    }

    private function sumarLocal(Builder $query, string $moneda, string $cotizacion, string $fecha, string $importe): float
    {
        $expr = $this->exprLocal($moneda, $cotizacion, $importe);
        $base = (float) (clone $query)->selectRaw('SUM('.$expr.') as monto')->value('monto');

        $grupos = (clone $query)
            ->where($moneda, '<>', CotizacionVigenteSupport::MONEDA_LOCAL_ID)
            ->where(function ($q) use ($cotizacion) {
                $q->whereNull($cotizacion)->orWhere($cotizacion, '<=', 1);
            })
            ->groupBy(DB::raw($moneda), DB::raw($fecha))
            ->selectRaw($moneda.' as moneda_id, '.$fecha.' as fecha, SUM('.$importe.') as monto')
            ->get();

        foreach ($grupos as $grupo) {
            $tasa = CotizacionVigenteSupport::ventaValor((string) $grupo->fecha, (int) $grupo->moneda_id);
            if ($tasa <= 0) {
                $this->sinCotizacion++;

                continue;
            }
            $base += ((float) $grupo->monto) * ($tasa - 1);
            $this->cotizacionesAjenas++;
        }

        return round($base, 2);
    }

    private function exprLocal(string $moneda, string $cotizacion, string $importe): string
    {
        return '('.$importe.') * (CASE WHEN '.$moneda.' = '.CotizacionVigenteSupport::MONEDA_LOCAL_ID
            .' OR '.$moneda.' IS NULL THEN 1 WHEN '.$cotizacion.' > 1 THEN '.$cotizacion.' ELSE 1 END)';
    }

    private function aLocal(float $importe, int $monedaId, float $cotizacion, string $fecha): float
    {
        if ($monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return round($importe, 2);
        }
        if ($cotizacion > 1) {
            return round($importe * $cotizacion, 2);
        }
        $tasa = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($tasa <= 0) {
            $this->sinCotizacion++;

            return round($importe, 2);
        }
        $this->cotizacionesAjenas++;

        return round($importe * $tasa, 2);
    }

    /**
     * @return array<string, float>
     */
    private function mesesAgrupados(Builder $query, string $fecha, string $moneda, string $cotizacion, string $importe): array
    {
        $expr = $this->exprLocal($moneda, $cotizacion, $importe);
        $mes = "DATE_FORMAT({$fecha}, '%Y-%m')";
        $filas = (clone $query)
            ->groupBy(DB::raw($mes))
            ->selectRaw($mes.' as mes, SUM('.$expr.') as monto')
            ->get();
        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(string) $fila->mes] = (float) $fila->monto;
        }

        return $mapa;
    }

    /**
     * @param  array{monto: float, cantidad: int}  $actual
     * @param  array{monto: float, cantidad: int}  $anterior
     * @return array<string, mixed>
     */
    private function kpi(
        string $clave,
        string $titulo,
        string $tipo,
        string $sentido,
        array $actual,
        array $anterior,
        string $ayuda,
        ?string $extra = null
    ): array {
        $variacion = null;
        if (abs($anterior['monto']) >= 0.01) {
            $variacion = round(($actual['monto'] - $anterior['monto']) / abs($anterior['monto']) * 100, 1);
        }

        return [
            'clave' => $clave,
            'titulo' => $titulo,
            'tipo' => $tipo,
            'sentido' => $sentido,
            'monto' => $actual['monto'],
            'monto_fmt' => $this->dinero($actual['monto']),
            'cantidad' => $actual['cantidad'],
            'anterior' => $anterior['monto'],
            'anterior_fmt' => $this->dinero($anterior['monto']),
            'variacion' => $variacion,
            'ayuda' => $ayuda,
            'extra' => $extra ?? ($actual['cantidad'] > 0 ? $actual['cantidad'].' registros' : ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpisVacios(): array
    {
        $cero = ['monto' => 0.0, 'cantidad' => 0];
        $defs = [
            ['ventas', 'Ventas', 'flujo', 'mas_es_mejor'],
            ['compras', 'Compras', 'flujo', 'menos_es_mejor'],
            ['iva', 'IVA a pagar', 'flujo', 'menos_es_mejor'],
            ['cartera', 'Cheques en cartera', 'stock', 'mas_es_mejor'],
            ['emitidos', 'Cheques propios', 'stock', 'menos_es_mejor'],
            ['deuda_clientes', 'Deuda de clientes', 'stock', 'menos_es_mejor'],
            ['deuda_proveedores', 'Deuda a proveedores', 'stock', 'menos_es_mejor'],
        ];
        $kpis = [];
        foreach ($defs as [$clave, $titulo, $tipo, $sentido]) {
            $kpis[] = $this->kpi($clave, $titulo, $tipo, $sentido, $cero, $cero, '');
        }

        return $kpis;
    }

    /**
     * @param  list<array<string, mixed>>  $kpis
     * @param  list<array<string, mixed>>  $serie
     * @param  list<array<string, mixed>>  $clientes
     * @param  list<array<string, mixed>>  $proveedores
     * @param  list<array<string, mixed>>  $vendidos
     * @param  list<array<string, mixed>>  $comprados
     * @param  list<array<string, mixed>>  $vencimientos
     * @param  array{para_depositar?: int, vencen_semana?: int}  $alertas
     * @return array<string, mixed>
     */
    private function payload(
        array $periodo,
        array $kpis,
        array $serie,
        array $clientes,
        array $proveedores,
        array $vendidos,
        array $comprados,
        array $vencimientos = [],
        array $alertas = []
    ): array {
        $avisos = [];
        if ($this->cotizacionesAjenas > 0) {
            $avisos[] = 'Parte del importe en moneda extranjera usó la cotización vigente porque el comprobante no traía tasa.';
        }
        if ($this->sinCotizacion > 0) {
            $avisos[] = 'Hay movimientos en moneda extranjera sin cotización vigente: quedaron sin convertir.';
        }

        return [
            'periodo' => $periodo,
            'kpis' => $kpis,
            'serie' => $serie,
            'clientes' => $clientes,
            'proveedores' => $proveedores,
            'articulos_vendidos' => $vendidos,
            'articulos_comprados' => $comprados,
            'vencimientos' => $vencimientos,
            'alertas' => $alertas,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return list<array{nombre: string, monto: float, monto_fmt: string}>
     */
    private function mapRanking(iterable $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $monto = round((float) $fila->monto, 2);
            $out[] = [
                'nombre' => (string) $fila->nombre,
                'monto' => $monto,
                'monto_fmt' => $this->dinero($monto),
            ];
        }

        return $out;
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return list<array{sku: string, nombre: string, cantidad: float, monto: float, monto_fmt: string}>
     */
    private function mapArticulos(iterable $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $monto = round((float) $fila->monto, 2);
            $out[] = [
                'sku' => (string) $fila->sku,
                'nombre' => (string) $fila->nombre,
                'cantidad' => round((float) $fila->cantidad, 2),
                'monto' => $monto,
                'monto_fmt' => $this->dinero($monto),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $celdas
     * @return array{celdas: list<string>, url: ?string}
     */
    private function fila(array $celdas, ?string $url = null): array
    {
        return ['celdas' => $celdas, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalleVacio(string $titulo): array
    {
        return ['titulo' => $titulo, 'nota' => 'No hay empresas en el alcance.', 'secciones' => []];
    }

    private function dinero(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }

    private function fechaCorta(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private function referencia(string $hasta): string
    {
        $hoy = Carbon::today()->toDateString();

        return $hasta < $hoy ? $hasta : $hoy;
    }

    private function esHoyOFuturo(string $hasta): bool
    {
        return $hasta >= Carbon::today()->toDateString();
    }
}
