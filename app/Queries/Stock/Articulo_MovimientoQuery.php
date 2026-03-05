<?php

namespace App\Queries\Stock;

use App\Models\Stock\Articulo_Movimiento;
use DB;

class Articulo_MovimientoQuery implements Articulo_MovimientoQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Movimiento $articulo_movimiento)
    {
        $this->model = $articulo_movimiento;
    }

    public function generaDatosRepStockOt($estado, $mventa_id,
                                            $desdearticulo, $hastaarticulo,
                                            $desdelinea_id, $hastalinea_id,
                                            $desdecategoria_id, $hastacategoria_id,
                                            $desdelote, $hastalote, $deposito_id)
    {
        $articulo_query = $this->model->select('articulo_movimiento.ordentrabajo_id as ordentrabajo_id',
                            'articulo_movimiento.deposito_id as deposito_id',
                            'combinacion.foto as foto', 
                            'linea.nombre as nombrelinea',
                            'articulo.sku as sku', 
                            'combinacion.codigo as codigocombinacion',
                            'combinacion.nombre as nombrecombinacion',
                            'mventa.nombre as nombremarca',
                            'combinacion.estado as estado',
                            'articulo_movimiento.lote as lote',
                            'articulo_movimiento.modulo_id as modulo_id',
                            'articulo_movimiento_talle.talle_id as talle_id',
                            'talle.nombre as nombretalle',
							'pedido_combinacion.pedido_id as pedido',
                            'articulo_movimiento.id as id',
                            'articulo_movimiento_talle.id as idmov',
                            'articulo_movimiento_talle.cantidad as cantidad',
                            'articulo_movimiento_talle.precio as precio')
                            ->join('articulo', 'articulo.id', 'articulo_movimiento.articulo_id')
                            ->join('combinacion', 'combinacion.id', 'articulo_movimiento.combinacion_id')
                            ->join('linea', 'linea.id', 'articulo.linea_id')
                            ->join('mventa', 'mventa.id', 'articulo.mventa_id')
                            //->join('articulo_movimiento', 'articulo_movimiento.combinacion_id', 'combinacion.id')
                            ->join('articulo_movimiento_talle', 'articulo_movimiento_talle.articulo_movimiento_id', 
                                'articulo_movimiento.id')
                            ->join('talle', 'talle.id', 'articulo_movimiento_talle.talle_id')
                            ->leftjoin('pedido_combinacion', 'pedido_combinacion.id', 'articulo_movimiento.pedido_combinacion_id')
                            ->whereBetween('articulo.linea_id', [$desdelinea_id, $hastalinea_id])
                            ->whereBetween('articulo.categoria_id', [$desdecategoria_id, $hastacategoria_id])
                            ->where('articulo_movimiento.lote', '>', '0')
        					->orderBy('nombrelinea','ASC')
                            ->orderBy('sku','ASC')
                            ->orderBy('nombrecombinacion', 'ASC')
                            //->orderBy('modulo_id', 'ASC')
                            ->orderBy('lote','ASC');

        if ($desdearticulo != '' && $hastaarticulo != '')
            $articulo_query = $articulo_query->whereBetween('articulo.descripcion', [$desdearticulo, $hastaarticulo]);
            
        if ($mventa_id != 0)
            $articulo_query = $articulo_query->where('articulo.mventa_id', $mventa_id);
        
        if ($deposito_id != 0)
            $articulo_query = $articulo_query->where('deposito_id', $deposito_id);
        
        switch($estado)
        {
        case 'ACTIVAS':
            $articulo_query = $articulo_query->where('combinacion.estado', 'A');
            break;
        case 'INACTIVAS':
            $articulo_query = $articulo_query->where('combinacion.estado', 'I');
            break;
        }

        if ($desdelote != '')
            $articulo_query = $articulo_query->whereBetween('articulo_movimiento.lote', [
                                                            $desdelote, 
                                                            $hastalote
                                                            ]);

        $articulo_query = $articulo_query->get();
		return $articulo_query;
    }

    public function leeStockPorLote($lote, $articulo_id, $combinacion_id)
    {
        $articulo_query = $this->model->select('articulo_movimiento.ordentrabajo_id as ordentrabajo_id',
            'articulo.sku as sku', 
            'combinacion.id as combinacion_id',
            'combinacion.codigo as codigocombinacion',
            'combinacion.nombre as nombrecombinacion', 
            'mventa.nombre as nombremarca',
            'combinacion.estado as estado',
            'articulo_movimiento.lote as lote',
            'articulo_movimiento.modulo_id as modulo_id',
            'articulo_movimiento_talle.talle_id as talle_id',
            'talle.nombre as nombretalle',
            'articulo_movimiento.tipotransaccion_id as tipotransaccion_id',
            'articulo_movimiento.deposito_id as deposito_id',
            'articulo_movimiento_talle.cantidad as cantidad',
            'articulo_movimiento_talle.precio as precio')
            ->join('articulo', 'articulo.id', 'articulo_movimiento.articulo_id')
            ->join('combinacion', 'combinacion.id', 'articulo_movimiento.combinacion_id')
            ->join('mventa', 'mventa.id', 'articulo.mventa_id')
            //->join('articulo_movimiento', 'articulo_movimiento.combinacion_id', 'combinacion.id')
            ->join('articulo_movimiento_talle', 'articulo_movimiento_talle.articulo_movimiento_id', 'articulo_movimiento.id')
            ->join('talle', 'talle.id', 'articulo_movimiento_talle.talle_id')
            ->where('articulo_movimiento.lote', '=', $lote)
            ->where('articulo.id', '=', $articulo_id)
            ->where('combinacion.id', '=', $combinacion_id)
            ->orderBy('lote','ASC')
            ->get();

        return $articulo_query;
    }

    public function buscaLoteImportacion($lotestock_id)
    {
        $articulo_movimiento = $this->model->select('articulo_movimiento.loteimportacion_id as loteimportacion_id')
            ->where('articulo_movimiento.lote', '=', $lotestock_id)
            ->where('articulo_movimiento.loteimportacion_id', '>', 0)
            ->orderBy('articulo_movimiento.loteimportacion_id','ASC')
            ->get();

        return $articulo_movimiento;
    }
}

