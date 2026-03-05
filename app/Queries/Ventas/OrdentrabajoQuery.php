<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Ordentrabajo;
use App\ApiAnita;

class OrdentrabajoQuery implements OrdentrabajoQueryInterface
{
    protected $model;
	protected $tableAnita = ['ordtmae','ordtmov'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordentrabajo $cliente)
    {
        $this->model = $cliente;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
		return $this->model->with('ordentrabajo_combinacion_talles')->with('ordentrabajo_tareas')->get();
    }

	public function allPaginando($busqueda, $flPaginar)
    {
		$ordenes = $this->model->with(['ordentrabajo_combinacion_talles', 'ordentrabajo_tareas']);
		
		$boletasJuntas = "BOLETAS JUNTAS";
		if (substr_compare (strtoupper($busqueda) , $boletasJuntas , 0, strlen($busqueda), true) == 0)
			$ordenes = $ordenes->whereRaw(
				'(select count(distinct(ordentrabajo_combinacion_talle.cliente_id)) from ordentrabajo_combinacion_talle 
				where ordentrabajo.id=ordentrabajo_combinacion_talle.ordentrabajo_id) >= 2');
		else
			$ordenes = $ordenes->WhereHas('ordentrabajo_combinacion_talles.clientes', function ($query) use ($busqueda) {
								$query->where('nombre', 'like', '%'.$busqueda.'%');
							});
							
		$ordenes = $ordenes->orWhereHas('ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.articulos', function ($query) use ($busqueda) {
								$query->where('descripcion', 'like', '%'.$busqueda.'%');
							})
							->orWhereHas('ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.combinaciones', function ($query) use ($busqueda) {
								$query->where('nombre', 'like', '%'.$busqueda.'%');
							})					
							->orWhereHas('ordentrabajo_tareas.tareas', function ($query) use ($busqueda) {
								$query->latest()->where('nombre', 'like', '%'.$busqueda.'%');
							})						
							->orWhere('ordentrabajo.id', '=', $busqueda)
							->orderByDesc('id');
							
		if ($flPaginar)
			$ordenes = $ordenes->paginate(10);
		else
			$ordenes = $ordenes->get();

		return $ordenes;
    }

    public function allQuery(array $campos)
    {
        return $this->model->select($campos)->get();
    }

    public function leeOrdenTrabajo($id)
    {
		$mod = $this->model->with('ordentrabajo_combinacion_talles')->with('ordentrabajo_tareas')->where('id',$id)->first();

		return $mod;
    }

    public function leeOrdenTrabajoPorCodigo($codigo)
    {
		$mod = $this->model->with('ordentrabajo_combinacion_talles')->with('ordentrabajo_tareas')->where('codigo',$codigo)->first();

		return $mod;
    }

    public function allPendiente()
    {
		$mod = $this->model->with('ordentrabajo_combinacion_talles')->with('ordentrabajo_tareas')->where('estado','P')->get();

		return $mod;
    }
	
	public function findConsumoOt($desdefecha, $hastafecha, $ordenestrabajo)
	{
		$dataCapellada = $this->model
			->select('ordentrabajo.id as ordentrabajo_id',
				'ordentrabajo.fecha as fecha',
				'pedido_combinacion.id as pedido_combinacion_id',
				'pedido_combinacion_talle.talle_id as talle_id',
				'talle.nombre as nombretalle',
				'pedido_combinacion_talle.cantidad as cantidadportalle',
				'materialcapellada.id as materialcapellada_id',
                'materialcapellada.nombre as nombrematerialcapellada',
				'colorcapellada.nombre as nombrecolorcapellada',
                'capeart.consumo1 as consumocapellada1',
				'capeart.consumo2 as consumocapellada2',
				'capeart.consumo3 as consumocapellada3',
				'capeart.consumo4 as consumocapellada4',
				'capeart.tipo as tipomaterial')
			->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo.id')
			->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
			->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
			->leftjoin('capeart', function($join)
				{
					$join->on('capeart.articulo_id', 'pedido_combinacion.articulo_id')
						 ->on('capeart.combinacion_id', 'pedido_combinacion.combinacion_id');
				})
			->leftjoin('materialcapellada', 'materialcapellada.id', 'capeart.material_id')
            ->leftjoin('color as colorcapellada', 'colorcapellada.id', 'capeart.color_id')
			->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
			->whereBetween('ordentrabajo.fecha', [$desdefecha, $hastafecha])
			->orderBy('tipomaterial')
			->orderBy('materialcapellada.nombre');
		
		if ($ordenestrabajo != '')
		{
			$ot = explode(',', $ordenestrabajo);
			$dataCapellada = $dataCapellada->whereIn('ordentrabajo.codigo', $ot);
		}	
		$dataCapellada = $dataCapellada->get();

		$dataAvio = $this->model
			->select('ordentrabajo.id as ordentrabajo_id',
				'ordentrabajo.fecha as fecha',
				'pedido_combinacion.id as pedido_combinacion_id',
				'pedido_combinacion_talle.talle_id as talle_id',
				'talle.nombre as nombretalle',
				'pedido_combinacion_talle.cantidad as cantidadportalle',
				'avioart.consumo1 as consumoavio1',
				'avioart.consumo2 as consumoavio2',
				'avioart.consumo3 as consumoavio3',
				'avioart.consumo4 as consumoavio4',
				'materialavio.id as materialavio_id',
                'materialavio.nombre as nombrematerialavio',
				'coloravio.nombre as nombrecoloravio')
			->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo.id')
			->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
			->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
			->leftjoin('avioart', function($join)
				{
					$join->on('avioart.articulo_id', 'pedido_combinacion.articulo_id')
						 ->on('avioart.combinacion_id', 'pedido_combinacion.combinacion_id');
				})
			->leftjoin('materialavio', 'materialavio.id', 'avioart.material_id')
			->leftjoin('color as coloravio', 'coloravio.id', 'avioart.color_id')
			->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
			->whereBetween('ordentrabajo.fecha', [$desdefecha, $hastafecha])
			->orderBy('materialavio.nombre');
					
		if ($ordenestrabajo != '')
		{
			$ot = explode(',', $ordenestrabajo);
			$dataAvio = $dataAvio->whereIn('ordentrabajo.codigo', $ot);
		}
		$dataAvio = $dataAvio->get();

		return ['datacapellada' => $dataCapellada, 'dataavio' => $dataAvio];
	}

	public function findConsumoCaja($desdefecha, $hastafecha, $ordenestrabajo)
	{
		$data = $this->model
			->select('ordentrabajo.id as ordentrabajo_id',
				'ordentrabajo.fecha as fecha',
				'pedido_combinacion.id as pedido_combinacion_id',
				'pedido_combinacion_talle.talle_id as talle_id',
				'cliente.cajaespecial as cajaespecial',
				'talle.nombre as nombretalle',
				'pedido_combinacion_talle.cantidad as cantidadportalle',
				'articulo_caja.caja_id as caja_id',
				'caja.nombre as nombrecaja',
				'articulo_caja.desdenro as desdenumero',
				'articulo_caja.hastanro as hastanumero',
				'articulo.descripcion as nombrearticulocaja')
			->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo.id')
			->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
			->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
			->join('pedido', 'pedido.id', 'pedido_combinacion.pedido_id')
			->join('cliente', 'cliente.id', 'pedido.cliente_id')
			->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
			->join('articulo_caja', 'articulo_caja.articulo_id', 'pedido_combinacion.articulo_id')
			->join('cajaproducto', 'cajaproducto.id', 'articulo_caja.caja_id')
			->join('articulo', 'articulo.id', 'cajaproducto.articulo_id')
			->whereIn('ordentrabajo_combinacion_talle.ordentrabajo_stock_id', [0, null]);

		if ($ordenestrabajo != '')
		{
			$ot = explode(',', $ordenestrabajo);
			$data = $data->whereIn('ordentrabajo.codigo', $ot);
		}
		else
		{
			$data = $data->whereBetween('ordentrabajo.fecha', [$desdefecha, $hastafecha]);
		}

		$data = $data->orderBy('cajaproducto.nombre')->get();

		return $data;
	}

	public function findProgArmado($ordenestrabajo)
	{
		$data = $this->model
			->select('ordentrabajo.id as ordentrabajo_id',
				'ordentrabajo.codigo as codigoot',
				'ordentrabajo.fecha as fecha',
				'pedido_combinacion.id as pedido_combinacion_id',
				'pedido_combinacion_talle.cantidad as cantidad',
				'articulo.sku as sku',
				'articulo.descripcion as nombrearticulo',
				'linea.nombre as nombrelinea',
				'combinacion.nombre as nombrecombinacion',
				'cliente.nombre as nombrecliente')
			->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo.id')
			->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
			->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
			->join('cliente', 'cliente.id', 'ordentrabajo_combinacion_talle.cliente_id')
			->join('articulo', 'articulo.id', 'pedido_combinacion.articulo_id')
			->join('combinacion', 'combinacion.id', 'pedido_combinacion.combinacion_id')
			->join('linea', 'linea.id', 'articulo.linea_id');
				
		if ($ordenestrabajo != '')
		{
			$ot = explode(',', $ordenestrabajo);
			$data = $data->whereIn('ordentrabajo.codigo', $ot);
		}
					
		$data = $data->get();

		return $data;
	}

    public function traeOrdentrabajoPorId($id)
    {
        $apiAnita = new ApiAnita();
        $data = array( 
		  'acc' => 'list', 
            'campos' => '
    			ordtm_cliente,
    			ordtm_nro_orden,
    			ordtm_tipo,
    			ordtm_letra,
    			ordtm_sucursal,
    			ordtm_nro,
    			ordtm_nro_renglon,
    			ordtm_fecha,
    			ordtm_estado,
    			ordtm_observacion,
    			ordtm_alfa_cliente,
    			ordtm_articulo,
    			ordtm_color,
    			ordtm_forro,
    			ordtm_alfa_art,
    			ordtm_linea,
    			ordtm_fondo,
    			ordtm_color_fondo,
    			ordtm_capellada,
    			ordtm_color_cap,
    			ordtm_color_forro,
    			ordtm_tipo_fact,
    			ordtm_letra_fact,
    			ordtm_suc_fact,
    			ordtm_nro_fact,
    			ordtm_aplique,
    			ordtm_fl_impresa,
				ordtm_fl_stock,
				ordtv_articulo,
				ordtv_color,
				ordtv_medida,
				ordtv_cantidad,
				ordtv_forro
			',
		  	'tabla' => $this->tableAnita[1].", ".$this->tableAnita[0]." ",
			  'whereArmado' => " WHERE ordtv_nro_orden = '".$id."' 
			  						AND ordtv_nro_orden=ordtm_nro_orden"
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        return $dataAnita;
    }

	public function traeOrdentrabajoPorIdERP($id)
    {
		$data = $this->model
					->select('ordentrabajo.id as ordentrabajo_id',
						'ordentrabajo.codigo as ordtm_nro_orden',
						'ordentrabajo.fecha as fecha',
						'pedido_combinacion.id as pedido_combinacion_id',
						'pedido_combinacion_talle.cantidad as ordtv_cantidad',
						'pedido_combinacion_talle.talle_id as talle_id',
						'talle.nombre as ordtv_medida',
						'articulo.sku as ordtv_articulo',
						'combinacion.codigo as ordtm_capellada',
						'combinacion.nombre as nombrecombinacion')
					->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.ordentrabajo_id', 'ordentrabajo.id')
					->join('pedido_combinacion_talle', 'pedido_combinacion_talle.id', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id')
					->join('talle', 'talle.id', '=', 'pedido_combinacion_talle.talle_id')
					->join('pedido_combinacion', 'pedido_combinacion.id', 'pedido_combinacion_talle.pedido_combinacion_id')
					->join('articulo', 'articulo.id', 'pedido_combinacion.articulo_id')
					->join('combinacion', 'combinacion.id', 'pedido_combinacion.combinacion_id')
					->where('ordentrabajo.codigo',$id);
						
		$data = $data->get();

		return $data;
    }

    public function allOrdentrabajoPorEstado($estado){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
            'campos' => '
    			ordtm_cliente,
    			ordtm_nro_orden,
    			ordtm_tipo,
    			ordtm_letra,
    			ordtm_sucursal,
    			ordtm_nro,
    			ordtm_nro_renglon,
    			ordtm_fecha,
    			ordtm_estado,
    			ordtm_observacion,
    			ordtm_alfa_cliente,
    			ordtm_articulo,
    			ordtm_color,
    			ordtm_forro,
    			ordtm_alfa_art,
    			ordtm_linea,
    			ordtm_fondo,
    			ordtm_color_fondo,
    			ordtm_capellada,
    			ordtm_color_cap,
    			ordtm_color_forro,
    			ordtm_tipo_fact,
    			ordtm_letra_fact,
    			ordtm_suc_fact,
    			ordtm_nro_fact,
    			ordtm_aplique,
    			ordtm_fl_impresa,
    			ordtm_fl_stock
			',
            'whereArmado' => " WHERE ordtm_fecha>20210600 and ordtm_estado = '".$estado."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		return($dataAnita);
	}

    public function allOrdentrabajo(){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
            'campos' => '
    			ordtm_cliente,
    			ordtm_nro_orden
						',
            'whereArmado' => " WHERE ordtm_fecha>20210600"
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		return($dataAnita);
	}
}

