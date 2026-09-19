<?php

namespace App\Queries\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Support\Stock\ReporteStockOtSituacionSupport;
use Illuminate\Support\Collection;
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
                            'ordentrabajo.codigo as ordentrabajo_codigo',
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
                            ->join('articulo_movimiento_talle', 'articulo_movimiento_talle.articulo_movimiento_id', 
                                'articulo_movimiento.id')
                            ->join('talle', 'talle.id', 'articulo_movimiento_talle.talle_id')
                            ->leftjoin('pedido_combinacion', 'pedido_combinacion.id', 'articulo_movimiento.pedido_combinacion_id')
                            ->leftJoin('ordentrabajo', 'ordentrabajo.id', 'articulo_movimiento.ordentrabajo_id')
                            ->whereBetween('articulo.linea_id', [$desdelinea_id, $hastalinea_id])
                            ->whereBetween('articulo.categoria_id', [$desdecategoria_id, $hastacategoria_id])
                            ->where(function ($q) {
                                $q->where('articulo_movimiento.lote', '>', '0')
                                    ->orWhere('articulo_movimiento.ordentrabajo_id', '>', 0);
                            })
        					->orderBy('nombrelinea','ASC')
                            ->orderBy('sku','ASC')
                            ->orderBy('nombrecombinacion', 'ASC')
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
            $articulo_query = $articulo_query->where(function ($q) use ($desdelote, $hastalote) {
                $q->whereBetween('articulo_movimiento.lote', [$desdelote, $hastalote])
                    ->orWhereBetween('ordentrabajo.codigo', [$desdelote, $hastalote]);
            });

        $articulo_query = $articulo_query->get();
		return $articulo_query;
    }

    public function generaDatosOtEnProduccion(
        $estado,
        $mventa_id,
        $desdearticulo,
        $hastaarticulo,
        $desdelinea_id,
        $hastalinea_id,
        $desdecategoria_id,
        $hastacategoria_id,
        $desdelote,
        $hastalote,
        array $ordentrabajoIdsYaIncluidos
    ): Collection {
        $idsCierre = ReporteStockOtSituacionSupport::idsTareasCierre();

        $query = DB::table('ordentrabajo as ot')
            ->select(
                'ot.id as ordentrabajo_id',
                DB::raw('0 as deposito_id'),
                'combinacion.foto as foto',
                'linea.nombre as nombrelinea',
                'articulo.sku as sku',
                'combinacion.codigo as codigocombinacion',
                'combinacion.nombre as nombrecombinacion',
                'ot.codigo as ordentrabajo_codigo',
                DB::raw('0 as lote'),
                'pc.modulo_id as modulo_id',
                'pct.talle_id as talle_id',
                'talle.nombre as nombretalle',
                'pc.pedido_id as pedido',
                DB::raw('(ot.id * 100000 + pct.id) as id'),
                'pct.cantidad as cantidad',
                'pct.precio as precio'
            )
            ->join('ordentrabajo_combinacion_talle as oct', 'oct.ordentrabajo_id', '=', 'ot.id')
            ->join('pedido_combinacion_talle as pct', 'pct.id', '=', 'oct.pedido_combinacion_talle_id')
            ->join('pedido_combinacion as pc', 'pc.id', '=', 'pct.pedido_combinacion_id')
            ->join('articulo', 'articulo.id', '=', 'pc.articulo_id')
            ->join('combinacion', 'combinacion.id', '=', 'pc.combinacion_id')
            ->join('linea', 'linea.id', '=', 'articulo.linea_id')
            ->join('talle', 'talle.id', '=', 'pct.talle_id')
            ->whereExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('ordentrabajo_tarea as ott')
                    ->whereColumn('ott.ordentrabajo_id', 'ot.id');
            })
            ->whereNotExists(function ($sub) use ($idsCierre) {
                $sub->select(DB::raw('1'))
                    ->from('ordentrabajo_tarea as ottc')
                    ->whereColumn('ottc.ordentrabajo_id', 'ot.id')
                    ->whereIn('ottc.tarea_id', $idsCierre);
            })
            ->whereBetween('articulo.linea_id', [$desdelinea_id, $hastalinea_id])
            ->whereBetween('articulo.categoria_id', [$desdecategoria_id, $hastacategoria_id])
            ->orderBy('linea.nombre')
            ->orderBy('articulo.sku')
            ->orderBy('combinacion.nombre')
            ->orderBy('ot.codigo');

        $otIds = array_values(array_filter(array_map('intval', $ordentrabajoIdsYaIncluidos)));
        if ($otIds !== []) {
            $query->whereNotIn('ot.id', $otIds);
        }
        if ($desdearticulo != '' && $hastaarticulo != '') {
            $query->whereBetween('articulo.descripcion', [$desdearticulo, $hastaarticulo]);
        }
        if ((int) $mventa_id !== 0) {
            $query->where('articulo.mventa_id', $mventa_id);
        }
        switch ($estado) {
            case 'ACTIVAS':
                $query->where('combinacion.estado', 'A');
                break;
            case 'INACTIVAS':
                $query->where('combinacion.estado', 'I');
                break;
        }
        if ($desdelote != '') {
            $query->whereBetween('ot.codigo', [$desdelote, $hastalote]);
        }

        return $query->get()->map(static function ($row) {
            return [
                'ordentrabajo_id' => (int) $row->ordentrabajo_id,
                'deposito_id' => 0,
                'foto' => $row->foto,
                'nombrelinea' => $row->nombrelinea,
                'sku' => $row->sku,
                'codigocombinacion' => $row->codigocombinacion,
                'nombrecombinacion' => $row->nombrecombinacion,
                'ordentrabajo_codigo' => $row->ordentrabajo_codigo,
                'lote' => 0,
                'modulo_id' => (int) $row->modulo_id,
                'talle_id' => (int) $row->talle_id,
                'nombretalle' => $row->nombretalle,
                'pedido' => $row->pedido,
                'id' => (int) $row->id,
                'cantidad' => (float) $row->cantidad,
                'precio' => $row->precio,
                'en_produccion_forzada' => true,
            ];
        });
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

    public function leeMovimientosLotesArticuloCombinacion(int $articuloId, int $combinacionId, ?int $moduloId = null, ?string $texto = null)
    {
        $q = $this->model->select(
            'articulo_movimiento.lote as lote',
            'articulo_movimiento.ordentrabajo_id as ordentrabajo_id',
            'ordentrabajo.codigo as ordentrabajo_codigo',
            'articulo_movimiento.modulo_id as modulo_id',
            'articulo_movimiento.tipotransaccion_id as tipotransaccion_id',
            'articulo_movimiento.deposito_id as deposito_id',
            'articulo_movimiento_talle.cantidad as cantidad',
            'talle.nombre as nombretalle',
            'modulo.codigo as modulo_codigo',
            'modulo.nombre as modulo_nombre',
            'depmae.codigo as deposito_codigo',
            'depmae.nombre as deposito_nombre'
        )
            ->join('articulo_movimiento_talle', 'articulo_movimiento_talle.articulo_movimiento_id', 'articulo_movimiento.id')
            ->leftJoin('talle', 'talle.id', 'articulo_movimiento_talle.talle_id')
            ->leftJoin('modulo', 'modulo.id', 'articulo_movimiento.modulo_id')
            ->leftJoin('depmae', 'depmae.id', 'articulo_movimiento.deposito_id')
            ->leftJoin('ordentrabajo', 'ordentrabajo.id', 'articulo_movimiento.ordentrabajo_id')
            ->where('articulo_movimiento.articulo_id', $articuloId)
            ->where('articulo_movimiento.combinacion_id', $combinacionId)
            ->where(function ($w) {
                $w->where(function ($l) {
                    $l->whereNotNull('articulo_movimiento.lote')
                        ->where('articulo_movimiento.lote', '<>', '')
                        ->where('articulo_movimiento.lote', '<>', '0');
                })->orWhere('articulo_movimiento.ordentrabajo_id', '>', 0);
            })
            ->orderBy('articulo_movimiento.lote')
            ->orderBy('articulo_movimiento.modulo_id');

        if ($moduloId && $moduloId > 0) {
            $q->where('articulo_movimiento.modulo_id', $moduloId);
        }

        $texto = trim((string) $texto);
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $q->where(function ($w) use ($like, $texto) {
                $w->where('articulo_movimiento.lote', 'like', $like)
                    ->orWhere('ordentrabajo.codigo', 'like', $like)
                    ->orWhere('modulo.codigo', 'like', $like)
                    ->orWhere('modulo.nombre', 'like', $like);
                if (ctype_digit($texto)) {
                    $w->orWhere('articulo_movimiento.lote', $texto)
                        ->orWhere('ordentrabajo.codigo', $texto);
                }
            });
        }

        return $q->get();
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

