<?php

namespace App\Services\Ventas;

use Illuminate\Support\Facades\DB;

class RepArticuloVendidoFerliService
{
    public function generaDatosRepArticulosVendidos($tipoOrigen, $desdefecha, $hastafecha,
        $desdearticulo_id, $hastaarticulo_id,
        $desdecliente_id, $hastacliente_id,
        $desdelinea_id, $hastalinea_id,
        $mventa_id)
    {
        $data = $this->consultarMovimientos($tipoOrigen, $desdefecha, $hastafecha,
            $desdearticulo_id, $hastaarticulo_id,
            $desdecliente_id, $hastacliente_id,
            $desdelinea_id, $hastalinea_id,
            $mventa_id);

        $articulos = [];
        $totales = [
            'cantidad' => 0,
            'cantidad_mov' => 0,
            'importe' => 0,
        ];

        foreach ($data as $movimiento) {
            $cantidadPos = abs((float) $movimiento->cantidad);
            // La venta sale de stock (negativa). La nota de crédito (signo < 0) vuelve a entrar.
            $cantidad = ((int) ($movimiento->signo ?? 1)) < 0 ? $cantidadPos : -$cantidadPos;
            $precio = (float) $movimiento->precio;
            $importe = $cantidad * $precio;
            $codigoArticulo = str_pad($movimiento->sku, 13, '0', STR_PAD_LEFT);
            $agrupacion = str_pad($movimiento->agrupacion, 4, '0', STR_PAD_LEFT);
            $nroComprobante = $this->formateaNroComprobanteVenta($movimiento->codigoventa, $movimiento->puntoventa, $movimiento->numerocomprobante);
            $unidad = $movimiento->unidadmedida ?: 'Par';

            $renglon = [
                'fecha' => $movimiento->fecha,
                'tipocomprobante' => $movimiento->tipocomprobante,
                'nrocomprobante' => $nroComprobante,
                'numerodespacho' => $movimiento->numerodespacho ?? '',
                'cantidad' => $cantidadPos,
                'cantidad_mov' => $cantidad,
                'unidad' => $unidad,
                'importe' => $importe,
                'codigocliente' => $movimiento->codigocliente,
                'nombrecliente' => $movimiento->nombrecliente,
            ];

            $key = $movimiento->articulo_id;
            if (! array_key_exists($key, $articulos)) {
                $articulos[$key] = [
                    'codigo' => $codigoArticulo,
                    'nombre' => $movimiento->nombrearticulo,
                    'agrupacion' => $agrupacion,
                    'renglones' => [],
                    'total_cantidad' => 0,
                    'total_cantidad_mov' => 0,
                    'total_importe' => 0,
                ];
            }

            $articulos[$key]['renglones'][] = $renglon;
            $articulos[$key]['total_cantidad'] += $cantidadPos;
            $articulos[$key]['total_cantidad_mov'] += $cantidad;
            $articulos[$key]['total_importe'] += $importe;

            $totales['cantidad'] += $cantidadPos;
            $totales['cantidad_mov'] += $cantidad;
            $totales['importe'] += $importe;
        }

        return [
            'articulos' => array_values($articulos),
            'totales' => $totales,
        ];
    }

    private function consultarMovimientos($tipoOrigen, $desdefecha, $hastafecha,
        $desdearticulo_id, $hastaarticulo_id,
        $desdecliente_id, $hastacliente_id,
        $desdelinea_id, $hastalinea_id,
        $mventa_id)
    {
        // El comprobante manda. Hay facturas y remitos sin movimiento de stock
        // (no se grabó el consumo de OT): un inner join contra articulo_movimiento
        // las dejaba afuera y el listado no cerraba con los pedidos facturados.
        $ventaEmisionAgg = DB::table('venta_emision')
            ->select(
                'venta_id',
                'pedido_combinacion_id',
                'articulo_id',
                'combinacion_id',
                DB::raw('SUM(cantidad) as cantidad'),
                DB::raw('MAX(precio) as precio'),
                DB::raw('COALESCE(MAX(NULLIF(loteimportacion_id, 0)), 0) as loteimportacion_ve')
            )
            ->groupBy('venta_id', 'pedido_combinacion_id', 'articulo_id', 'combinacion_id');

        $lotesMovimiento = DB::table('articulo_movimiento')
            ->select(
                'venta_id',
                'pedido_combinacion_id',
                'articulo_id',
                'combinacion_id',
                DB::raw('COALESCE(MAX(NULLIF(loteimportacion_id, 0)), 0) as loteimportacion_mov')
            )
            ->whereNotNull('venta_id')
            ->groupBy('venta_id', 'pedido_combinacion_id', 'articulo_id', 'combinacion_id');

        $loteimportacionExpr = 'COALESCE(NULLIF(lotes_mov.loteimportacion_mov, 0), NULLIF(venta_emision_agg.loteimportacion_ve, 0), 0)';

        $query = DB::query()->fromSub($ventaEmisionAgg, 'venta_emision_agg')
            ->select(
                'venta.fecha as fecha',
                'venta_emision_agg.articulo_id as articulo_id',
                'venta_emision_agg.cantidad as cantidad',
                'venta_emision_agg.precio as precio',
                'tipotransaccion.signo as signo',
                'articulo.sku as sku',
                'articulo.descripcion as nombrearticulo',
                'categoria.codigo as agrupacion',
                'unidadmedida.nombre as unidadmedida',
                'tipotransaccion.abreviatura as tipocomprobante',
                'venta.codigo as codigoventa',
                'venta.numerocomprobante as numerocomprobante',
                'puntoventa.codigo as puntoventa',
                'cliente.codigo as codigocliente',
                'cliente.nombre as nombrecliente',
                'lote_importacion.numerodespacho as numerodespacho'
            )
            ->leftJoinSub($lotesMovimiento, 'lotes_mov', function ($join) {
                $join->on('lotes_mov.venta_id', '=', 'venta_emision_agg.venta_id')
                    ->on('lotes_mov.articulo_id', '=', 'venta_emision_agg.articulo_id')
                    ->on('lotes_mov.combinacion_id', '=', 'venta_emision_agg.combinacion_id')
                    ->where(function ($mismoPedido) {
                        $mismoPedido->whereColumn('lotes_mov.pedido_combinacion_id', 'venta_emision_agg.pedido_combinacion_id')
                            ->orWhere(function ($ambosNulos) {
                                $ambosNulos->whereNull('lotes_mov.pedido_combinacion_id')
                                    ->whereNull('venta_emision_agg.pedido_combinacion_id');
                            });
                    });
            })
            ->join('venta', 'venta.id', 'venta_emision_agg.venta_id')
            ->join('articulo', 'articulo.id', 'venta_emision_agg.articulo_id')
            ->join('categoria', 'categoria.id', 'articulo.categoria_id')
            ->leftJoin('unidadmedida', 'unidadmedida.id', 'articulo.unidadmedida_id')
            ->join('tipotransaccion', 'tipotransaccion.id', 'venta.tipotransaccion_id')
            ->join('puntoventa', 'puntoventa.id', 'venta.puntoventa_id')
            ->join('cliente', 'cliente.id', 'venta.cliente_id')
            ->leftJoin('lote as lote_importacion', function ($join) use ($loteimportacionExpr) {
                $join->on('lote_importacion.id', '=', DB::raw('('.$loteimportacionExpr.')'));
            })
            ->whereIn('tipotransaccion.operacionstock', ['O', 'E'])
            ->whereBetween('venta.fecha', [$desdefecha, $hastafecha])
            ->whereBetween('articulo.id', [$desdearticulo_id, $hastaarticulo_id])
            ->whereBetween('cliente.id', [$desdecliente_id, $hastacliente_id])
            ->whereBetween('articulo.linea_id', [$desdelinea_id, $hastalinea_id])
            ->orderBy('articulo.sku', 'ASC')
            ->orderBy('venta.fecha', 'ASC')
            ->orderBy('venta.numerocomprobante', 'ASC');

        switch ($tipoOrigen) {
            case 'IMPORTADO':
                $query = $query->whereRaw($loteimportacionExpr.' > 0');
                break;
            case 'NACIONAL':
                $query = $query->whereRaw($loteimportacionExpr.' = 0');
                break;
        }

        if ($mventa_id != 0) {
            $query = $query->where('articulo.mventa_id', $mventa_id);
        }

        return $query->get();
    }

    private function formateaNroComprobanteVenta($codigoventa, $puntoventa, $numerocomprobante)
    {
        if ($codigoventa) {
            $partes = explode(' ', trim($codigoventa), 2);
            if (count($partes) >= 2) {
                $numeracion = explode('-', $partes[1]);
                if (count($numeracion) >= 3) {
                    return $numeracion[0]
                        .str_pad($numeracion[1], 4, '0', STR_PAD_LEFT)
                        .'-'
                        .str_pad($numeracion[2], config('facturacion.DIGITOS_COMPROBANTE'), '0', STR_PAD_LEFT);
                }
            }
        }

        return str_pad($puntoventa, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad($numerocomprobante, config('facturacion.DIGITOS_COMPROBANTE'), '0', STR_PAD_LEFT);
    }
}
