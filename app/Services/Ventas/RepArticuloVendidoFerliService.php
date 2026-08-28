<?php

namespace App\Services\Ventas;

use App\Models\Stock\Articulo_Movimiento;
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
            $cantidad = -$cantidadPos;
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
        $ventaEmisionAgg = DB::table('venta_emision')
            ->select(
                'venta_id',
                'pedido_combinacion_id',
                'articulo_id',
                'combinacion_id',
                DB::raw('SUM(cantidad) as cantidad'),
                DB::raw('MAX(precio) as precio'),
                DB::raw('COALESCE(MAX(loteimportacion_id), 0) as loteimportacion_ve')
            )
            ->groupBy('venta_id', 'pedido_combinacion_id', 'articulo_id', 'combinacion_id');

        $loteimportacionExpr = 'COALESCE(NULLIF(articulo_movimiento.loteimportacion_id, 0), venta_emision_agg.loteimportacion_ve, 0)';

        $query = Articulo_Movimiento::query()->select(
            'articulo_movimiento.id as id',
            'venta.fecha as fecha',
            'articulo_movimiento.articulo_id as articulo_id',
            'venta_emision_agg.cantidad as cantidad',
            'venta_emision_agg.precio as precio',
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
            'lote_importacion.numerodespacho as numerodespacho')
            ->joinSub($ventaEmisionAgg, 'venta_emision_agg', function ($join) {
                $join->on('venta_emision_agg.pedido_combinacion_id', 'articulo_movimiento.pedido_combinacion_id')
                    ->on('venta_emision_agg.articulo_id', 'articulo_movimiento.articulo_id')
                    ->on('venta_emision_agg.combinacion_id', 'articulo_movimiento.combinacion_id');
            })
            ->join('venta', 'venta.id', 'venta_emision_agg.venta_id')
            ->join('articulo', 'articulo.id', 'articulo_movimiento.articulo_id')
            ->join('categoria', 'categoria.id', 'articulo.categoria_id')
            ->leftJoin('unidadmedida', 'unidadmedida.id', 'articulo.unidadmedida_id')
            ->join('tipotransaccion', 'tipotransaccion.id', 'venta.tipotransaccion_id')
            ->join('puntoventa', 'puntoventa.id', 'venta.puntoventa_id')
            ->join('cliente', 'cliente.id', 'venta.cliente_id')
            ->leftJoin('lote as lote_importacion', function ($join) use ($loteimportacionExpr) {
                $join->on('lote_importacion.id', '=', DB::raw('('.$loteimportacionExpr.')'));
            })
            ->whereNull('articulo_movimiento.deleted_at')
            ->whereNull('venta.deleted_at')
            ->whereNotNull('articulo_movimiento.pedido_combinacion_id')
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
