<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Pedido;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use Illuminate\Support\Collection;
use DB;

class PedidoQuery implements PedidoQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido $pedido)
    {
        $this->model = $pedido;
    }

    public function allPedidoIndex($cliente_id, $opcion)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $pedidos = $this->model->with('clientes:id,nombre')->with('mventas:id,nombre')
                                ->orderBy('id','desc')
                                ->with('pedido_combinaciones')
                                ->with('pedido_articulos')
                                ->get();
        return $pedidos;
    }
    
    public function allPedidoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $pedidos = $this->model->select('pedido.id as id',
                                'pedido.fecha as fecha',
                                'pedido.fechaentrega as fechaentrega',
                                'cliente.nombre as nombrecliente',
                                'pedido.codigo as codigo',
                                'pedido.estadopedido as estado',
                                'pedido.transporte_id as transporte_id',
                                'transporte.nombre as nombretransporte',
                                'transporte.codigo as codigotransporte')
                                ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
                                ->join('transporte', 'transporte.id', 'pedido.transporte_id')
                                ->with('pedido_articulos');

        if ($estado != '')
        {
            $pedidos = $pedidos->where('pedido.estado', $estado);
        }

        if (isset($reparto[0]) && $reparto[0] != '')
        {
            // Verifica si vienen rangos o repartos en particular (/ o ,;)
            if (specialChars($reparto[0], '/'))
            {
                $repartos = explode("/", $reparto[0]);

                $pedidos = $pedidos->whereBetween('transporte.codigo', $repartos);
            }
            else if (specialChars($reparto[0], ',;'))
            {
                $repartos = explode(",", $reparto[0]);

                if (count($repartos) == 0)
                    $repartos = explode(";", $reparto[0]);

                $pedidos = $pedidos->whereIn('transporte.codigo', $repartos);
            }
            else
                $pedidos = $pedidos->where('transporte.codigo', $reparto[0]);
        }

        if ($fechaentrega != '')
        {
            if (gettype($fechaentrega) != 'string')
                $pedidos = $pedidos->where('pedido.fechaentrega', '>=', $fechaentrega->format('Y-m-d'));
            else
            {
                $fechasEntrega = explode("/", $fechaentrega);
                $pedidos = $pedidos->whereBetween('pedido.fechaentrega', $fechasEntrega);
            }
        }

        $pedidos = $pedidos->where(function ($query) use ($busqueda) {
                            $query->orwhere('cliente.nombre', 'like', '%'.$busqueda.'%')
                                    ->orwhere('pedido.fecha', $busqueda)
                                    ->orwhere('pedido.id', $busqueda)
                                    ->orWhere('pedido.estado', 'like', '%'.$busqueda.'%')
                                    ->orWhere('transporte.nombre', 'like', '%'.$busqueda.'%');
                            });


        $pedidos = $pedidos->orderBy('id','desc')
                ->paginate(10);

        return $pedidos;
    }

    public function allPedidoIndexSinPaginar($busqueda)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $pedidos = $this->model->select('pedido.id as id',
                                'pedido.fecha as fecha',
                                'cliente.nombre as nombrecliente',
                                'pedido.codigo as codigo',
                                'pedido.estadopedido as estado')
                                ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
                                ->with('pedido_combinaciones')
                                ->where('cliente.nombre', 'like', '%'.$busqueda.'%')
                                ->orwhere('pedido.fecha', $busqueda)
                                ->orwhere('pedido.id', $busqueda)
                                ->orWhere('pedido.estadopedido', 'like', '%'.$busqueda.'%')
                                ->orWhereHas('mventas', function ($query) use ($busqueda) {
                                    $query->where('nombre', 'like', '%'.$busqueda.'%');
                                })
                                ->orderBy('id','desc')
                                ->get();
        return $pedidos;
    }
                
    public function allPendiente($cliente_id = null)
    {
		if ($cliente_id)
			$mod = $this->model->with('clientes:id,nombre')->with('mventas:id,nombre')->with('transportes:id,nombre')->with('pedido_combinaciones')->where('estado','P')->where('cliente_id',$cliente_id)->get();
		else
			$mod = $this->model->with('clientes:id,nombre')->with('mventas:id,nombre')->with('transportes:id,nombre')->with('pedido_combinaciones')->where('estado','P')->get();

		return $mod;
    }

    public function allPendienteOt($articulo_id, $combinacion_id)
    {
		$mod = $this->model->with('clientes:id,nombre')
                ->with('mventas:id,nombre')
                ->with('transportes:id,nombre')
				->withWhereHasOtArticuloCombinacion($articulo_id, $combinacion_id)
				->get();

		return $mod;
    }

    public function leePedidoporCodigo($codigo)
    {
        return $this->model->select('id')->where('codigo' , $codigo)->first();
    }

    public function leePedidoporId($id)
    {
        $mod = $this->model->with('clientes:id,nombre')->with('mventas:id,nombre')->with('transportes:id,nombre')->with('pedido_articulos')->where('id',$id)->get();

        return $mod;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->with('pedido_combinaciones')->get();
    }

    public function findPorRangoFecha($tipolistado, $mventa_id, $desdefecha, $hastafecha, 
                                $desdevendedor_id, $hastavendedor_id,
                                $desdecliente_id, $hastacliente_id,
                                $desdearticulo_id, $hastaarticulo_id,
                                $desdelinea_id, $hastalinea_id,
                                $desdefondo_id, $hastafondo_id)
    {
        $pedido = $this->model
                        ->select('pedido.*','pedido_combinacion.*',
                                'pedido_combinacion.id as pedido_combinacion_id',
                                'pedido_combinacion_talle.talle_id as talle_id',
                                'talle.nombre as nombretalle',
                                'pedido_combinacion_talle.cantidad as cantidadportalle',
                                'articulo.linea_id as linea_id',
                                'articulo.sku as sku',
                                'articulo.id as articulo_id',
                                'articulo.descripcion as nombrearticulo',
                                'articulo.mventa_id as mventa_id',
                                'combinacion.nombre as nombrecombinacion',
                                'combinacion.fondo_id as fondo_id',
                                'combinacion.colorfondo_id as colorfondo_id',
                                'linea.nombre as nombrelinea',
                                'fondo.nombre as nombrefondo',
                                'cliente.id as cliente_id',
                                'cliente.nombre as nombrecliente',
                                'cliente.codigo as codigocliente',
                                'tiposuspensioncliente.nombre as estadocliente',
                                'ordentrabajo.id as ordentrabajo_id',
                                'ordentrabajo.codigo as codigoot',
                                'vendedor.id as vendedor_id',
                                'vendedor.nombre as nombrevendedor',
                                'color.nombre as nombrecolorfondo')
                        ->with('clientes:id,nombre')
                        ->with('mventas:id,nombre')
                        ->join('pedido_combinacion', 'pedido_combinacion.pedido_id', '=', 'pedido.id')
                        ->leftjoin('pedido_combinacion_talle', 'pedido_combinacion_talle.pedido_combinacion_id', '=', 'pedido_combinacion.id')
                        ->leftjoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id', '=', 'pedido_combinacion_talle.id')
                        ->leftjoin('ordentrabajo', 'ordentrabajo.id', '=', 'ordentrabajo_combinacion_talle.ordentrabajo_id')
                        ->join('articulo', 'articulo.id', '=', 'pedido_combinacion.articulo_id')
                        ->join('combinacion', 'combinacion.id', '=', 'pedido_combinacion.combinacion_id')
                        ->join('linea', 'linea.id', '=', 'articulo.linea_id')
                        ->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
                        ->leftjoin('fondo', 'fondo.id', '=', 'combinacion.fondo_id')
                        ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
                        ->leftjoin('tiposuspensioncliente', 'tiposuspensioncliente.id', 'cliente.tiposuspension_id')
                        ->leftjoin('vendedor', 'vendedor.id', '=', 'pedido.vendedor_id')
                        ->leftjoin('color', 'color.id', '=', 'combinacion.colorfondo_id')
                        ->whereBetween('pedido.fecha', [$desdefecha, $hastafecha])
                        ->whereBetween('pedido.cliente_id', [$desdecliente_id, $hastacliente_id])
                        ->whereBetween('pedido.vendedor_id', [$desdevendedor_id, $hastavendedor_id])
                        ->whereBetween('pedido_combinacion.articulo_id', [$desdearticulo_id, $hastaarticulo_id])
                        ->whereBetween('linea.id', [$desdelinea_id, $hastalinea_id]);

        if ($desdefondo_id != 0 || $hastafondo_id != 99999999)
            $pedido = $pedido->whereBetween('combinacion.fondo_id', [$desdefondo_id, $hastafondo_id]);

        // Selecciona marca
        if ($mventa_id > 0)
            $pedido = $pedido->where('articulo.mventa_id', $mventa_id);

        switch($tipolistado)
        {
            case 'CLIENTE':
                $pedido = $pedido->orderBy('pedido.cliente_id')
                            ->orderBy('pedido_combinacion.id')->get();
                break;
            case 'ARTICULO':
                $pedido = $pedido->orderBy('sku')
                            ->orderBy('nombrecombinacion')
                            ->orderBy('pedido_combinacion.id')->get();
                break;
            case 'LINEA':
                $pedido = $pedido->orderBy('articulo.linea_id')
                            ->orderBy('pedido_combinacion.id')->get();
                break;
            case 'VENDEDOR':
                $pedido = $pedido->orderBy('pedido.vendedor_id')
                            ->orderBy('pedido_combinacion.id')->get();
                break;
            case 'FONDO':
                $pedido = $pedido->orderBy('combinacion.fondo_id')
                            ->orderBy('combinacion.colorfondo_id')
                            ->orderBy('pedido_combinacion.id')->get();
                break;
        }
        return $pedido;
    }

    // Query para reporte de consumo de materiales

    public function findPorMaterialCapellada($tipolistado, $tipocapellada, 
                                $desdefecha, $hastafecha, 
                                $desdecliente_id, $hastacliente_id,
                                $desdearticulo_id, $hastaarticulo_id,
                                $desdelinea_id, $hastalinea_id,
                                $desdecolor_id, $hastacolor_id,
                                $desdematerialcapellada_id, $hastamaterialcapellada_id)
    {
        $pedido = Capeart::select('capeart.*',
                                'pedido.id as pedido_id',
                                'pedido.fecha',
                                'pedido.cliente_id',
                                'pedido_combinacion.id as pedido_combinacion_id',
                                'pedido_combinacion.articulo_id',
                                'pedido_combinacion.combinacion_id',
                                'pedido_combinacion_talle.talle_id as talle_id',
                                'talle.nombre as nombretalle',
                                'pedido_combinacion_talle.cantidad as cantidadportalle',
                                'articulo.linea_id as linea_id',
                                'articulo.sku as sku',
                                'articulo.id as articulo_id',
                                'articulo.descripcion as nombrearticulo',
                                'combinacion.nombre as nombrecombinacion',
                                'combinacion.fondo_id as fondo_id',
                                'linea.nombre as nombrelinea',
                                'fondo.nombre as nombrefondo',
                                'cliente.id as cliente_id',
                                'cliente.nombre as nombrecliente',
                                'cliente.codigo as codigocliente',
                                'ordentrabajo.id as ordentrabajo_id',
                                'ordentrabajo.codigo as codigoot',
                                'vendedor.id as vendedor_id',
                                'vendedor.nombre as nombrevendedor',
                                'materialcapellada.id as materialcapellada_id',
                                'materialcapellada.nombre as nombrematerialcapellada',
                                'color.nombre as nombrecolor')
                        ->join('pedido_combinacion', function($join)
                            {
                                $join->on('pedido_combinacion.articulo_id', 'capeart.articulo_id')
                                     ->on('pedido_combinacion.combinacion_id', 'capeart.combinacion_id');
                            })
                        ->join('pedido_combinacion_talle', 'pedido_combinacion_talle.pedido_combinacion_id', 'pedido_combinacion.id')
                        ->join('pedido', 'pedido.id', 'pedido_combinacion.pedido_id')
                        ->leftjoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id', 'pedido_combinacion_talle.id')
                        ->leftjoin('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_combinacion_talle.ordentrabajo_id')
                        ->join('articulo', 'articulo.id', 'pedido_combinacion.articulo_id')
                        ->join('combinacion', 'combinacion.id', 'pedido_combinacion.combinacion_id')
                        ->leftjoin('materialcapellada', 'materialcapellada.id', 'capeart.material_id')
                        ->join('color', 'color.id', 'capeart.color_id')
                        ->join('linea', 'linea.id', 'articulo.linea_id')
                        ->join('talle', 'talle.id', 'pedido_combinacion_talle.talle_id')
                        ->leftjoin('fondo', 'fondo.id', 'combinacion.fondo_id')
                        ->join('cliente', 'cliente.id', 'pedido.cliente_id')
                        ->leftjoin('vendedor', 'vendedor.id', '=', 'pedido.vendedor_id')
                        ->whereBetween('pedido.fecha', [$desdefecha, $hastafecha])
                        ->whereBetween('pedido.cliente_id', [$desdecliente_id, $hastacliente_id])
                        ->whereBetween('capeart.material_id', [$desdematerialcapellada_id, $hastamaterialcapellada_id])
                        ->whereBetween('capeart.color_id', [$desdecolor_id, $hastacolor_id])
                        ->whereBetween('pedido_combinacion.articulo_id', [$desdearticulo_id, $hastaarticulo_id])
                        ->whereBetween('linea.id', [$desdelinea_id, $hastalinea_id])
                        ->where(function ($query) use ($tipocapellada) {
                                if ($tipocapellada != 'TODOS')
                                    $query->where('tipo', substr($tipocapellada,0,1));
                            })
                        ->orderBy('materialcapellada.id')
                        ->orderBy('color.nombre')
                        ->orderBy('capeart.tipo')
                        ->orderBy('pedido_combinacion.id')->get();

        return $pedido;
    }

    // Query para reporte de consumo de avios

    public function findPorMaterialAvio($tipolistado, $tipoavio, 
                                $desdefecha, $hastafecha, 
                                $desdecliente_id, $hastacliente_id,
                                $desdearticulo_id, $hastaarticulo_id,
                                $desdelinea_id, $hastalinea_id,
                                $desdecolor_id, $hastacolor_id,
                                $desdematerialavio_id, $hastamaterialavio_id)
    {
        $pedido = Avioart::select('avioart.*',
                                'pedido_combinacion.*',
                                'pedido.id as pedido_id',
                                'pedido.*',
                                'pedido_combinacion.id as pedido_combinacion_id',
                                'pedido_combinacion_talle.talle_id as talle_id',
                                'talle.nombre as nombretalle',
                                'pedido_combinacion_talle.cantidad as cantidadportalle',
                                'articulo.linea_id as linea_id',
                                'articulo.sku as sku',
                                'articulo.id as articulo_id',
                                'articulo.descripcion as nombrearticulo',
                                'combinacion.nombre as nombrecombinacion',
                                'combinacion.fondo_id as fondo_id',
                                'linea.nombre as nombrelinea',
                                'fondo.nombre as nombrefondo',
                                'cliente.id as cliente_id',
                                'cliente.nombre as nombrecliente',
                                'cliente.codigo as codigocliente',
                                'ordentrabajo.id as ordentrabajo_id',
                                'ordentrabajo.codigo as codigoot',
                                'vendedor.id as vendedor_id',
                                'vendedor.nombre as nombrevendedor',
                                'materialavio.id as materialavio_id',
                                'materialavio.nombre as nombrematerialavio',
                                'color.nombre as nombrecolor')
                        ->join('pedido_combinacion', function($join)
                            {
                                $join->on('pedido_combinacion.articulo_id', 'avioart.articulo_id')
                                     ->on('pedido_combinacion.combinacion_id', 'avioart.combinacion_id');
                            })
                        ->join('pedido_combinacion_talle', 'pedido_combinacion_talle.pedido_combinacion_id', 'pedido_combinacion.id')
                        ->join('pedido', 'pedido.id', 'pedido_combinacion.pedido_id')
                        ->leftjoin('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id', 'pedido_combinacion_talle.id')
                        ->leftjoin('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_combinacion_talle.ordentrabajo_id')
                        ->join('articulo', 'articulo.id', 'pedido_combinacion.articulo_id')
                        ->join('combinacion', 'combinacion.id', 'pedido_combinacion.combinacion_id')
                        ->leftjoin('materialavio', 'materialavio.id', 'avioart.material_id')
                        ->join('color', 'color.id', 'avioart.color_id')
                        ->join('linea', 'linea.id', 'articulo.linea_id')
                        ->join('talle', 'talle.id', 'pedido_combinacion_talle.talle_id')
                        ->leftjoin('fondo', 'fondo.id', 'combinacion.fondo_id')
                        ->join('cliente', 'cliente.id', 'pedido.cliente_id')
                        ->leftjoin('vendedor', 'vendedor.id', '=', 'pedido.vendedor_id')
                        ->whereBetween('pedido.fecha', [$desdefecha, $hastafecha])
                        ->whereBetween('pedido.cliente_id', [$desdecliente_id, $hastacliente_id])
                        ->whereBetween('avioart.material_id', [$desdematerialavio_id, $hastamaterialavio_id])
                        ->whereBetween('avioart.color_id', [$desdecolor_id, $hastacolor_id])
                        ->whereBetween('pedido_combinacion.articulo_id', [$desdearticulo_id, $hastaarticulo_id])
                        ->whereBetween('linea.id', [$desdelinea_id, $hastalinea_id])
                        ->where(function ($query) use ($tipoavio) {
                                if ($tipoavio != 'TODOS')
                                    $query->where('tipo', substr($tipoavio,0,1));
                            })
                        ->orderBy('avioart.material_id')
                        ->orderBy('color.nombre')
                        ->orderBy('avioart.tipo')
                        ->orderBy('pedido_combinacion.id')->get();

        return $pedido;
    }

    // Lee para reporte de pedidos
    
    public function findPorPedido($desdefecha, $hastafecha,
                                    $desdevendedor_id, $hastavendedor_id)
    {
        $pedido = $this->model
                        ->select('pedido.fecha as fecha',
                                \DB::raw("SUBSTR(pedido.codigo, 1, 3) as tipo"),
                                \DB::raw("SUBSTR(pedido.codigo, 5, 1) as letra"),
                                'pedido.mventa_id as sucursal',
                                \DB::raw("SUBSTR(pedido.codigo, 13, 8) as numero"),
                                'cliente.nombre as nombre',
                                'cliente.codigo as cliente',
                                'articulo.sku as sku',
                                'combinacion.codigo as combinacion',
                                'combinacion.nombre as desc_combinacion',
                                'pedido_combinacion_talle.cantidad as cantidad',
                                'pedido.estado as estado',
                                'mventa.nombre as marca',
                                'linea.nombre as linea',
                                'ordentrabajo.codigo as nro_orden',
                                'vendedor.id as vendedor',
                                'vendedor.nombre as nombre_vendedor',
                                'talle.nombre as penv_medida',
                                'ordentrabajo_tarea.tarea_id as cantfact')
                        ->join('pedido_combinacion', 'pedido_combinacion.pedido_id', '=', 'pedido.id')
                        ->join('pedido_combinacion_talle', 'pedido_combinacion_talle.pedido_combinacion_id', '=', 'pedido_combinacion.id')
                        ->join('articulo', 'articulo.id', '=', 'pedido_combinacion.articulo_id')
                        ->join('combinacion', 'combinacion.id', '=', 'pedido_combinacion.combinacion_id')
                        ->join('linea', 'linea.id', '=', 'articulo.linea_id')
                        ->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
                        ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
                        ->leftjoin('vendedor', 'vendedor.id', '=', 'pedido.vendedor_id')
                        ->leftjoin('color', 'color.id', '=', 'combinacion.colorfondo_id')
                        ->leftjoin('mventa', 'mventa.id', 'articulo.mventa_id')
                        ->leftjoin('ordentrabajo', 'ordentrabajo.id', 'pedido_combinacion.ot_id')
                        ->leftjoin('ordentrabajo_tarea', 'ordentrabajo_tarea.pedido_combinacion_id', 'pedido_combinacion.id')
                        ->whereBetween('pedido.fecha', [$desdefecha, $hastafecha])
                        ->whereBetween('pedido.vendedor_id', [$desdevendedor_id, $hastavendedor_id])
                        ->orderBy('vendedor')
                        ->orderBy('cliente')
                        ->orderBy('fecha')
                        ->orderBy('pedido.codigo')
                        ->orderby('sku')
                        ->orderBy('combinacion')
                        ->get();
        return $pedido;
    }
}
