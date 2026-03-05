<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Configuracion\Impuesto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Modulo;
use App\Models\Stock\Talle;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Pedido_CombinacionRepository implements Pedido_CombinacionRepositoryInterface
{
    protected $model;
	protected $pedidoQuery;
    protected $tableAnita = 'pendmov';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = ['penm_sucursal', 'penm_nro'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Combinacion $pedido_combinacion,
								PedidoQueryInterface $pedidoquery,
								ClienteQueryInterface $clientequery)
    {
        $this->model = $pedido_combinacion;
        $this->pedidoQuery = $pedidoquery;
        $this->clienteQuery = $clientequery;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create(array $data, $pedido_id, $articulo_id, $combinacion_id, $numeroitem, $modulo_id, 
					$cantidad, $precio, $listaprecio_id, $incluyeimpuesto, $moneda_id, $descuento, 
					$categoria_id, $subcategoria_id, $linea_id, $ot_id, $observacion, $medidas, $lote_id, $funcion)
    {
		$pedido_combinacion = $this->model->create([
						'pedido_id' => $pedido_id,
						'articulo_id' => $articulo_id,
						'combinacion_id' => $combinacion_id,
						'numeroitem' => $numeroitem,
						'modulo_id' => $modulo_id,
						'cantidad' => $cantidad,
						'precio' => $precio,
						'listaprecio_id' => $listaprecio_id,
						'incluyeimpuesto' => $incluyeimpuesto,
						'moneda_id' => $moneda_id,
						'descuento' => ($descuento == '' ? 0 : $descuento),
						'categoria_id' => $categoria_id,
						'subcategoria_id' => $subcategoria_id,
						'linea_id' => $linea_id,
						'ot_id' => $ot_id,
						'lote_id' => $lote_id,
						'observacion' => $observacion
								]);

		// Graba anita
		//self::guardarAnita($data, $articulo_id, $combinacion_id, $numeroitem, $modulo_id, $cantidad, $precio, $listaprecio_id, 
		  //	$incluyeimpuesto, $moneda_id, $descuento, $medidas, $observacion, $funcion);

	  	return $pedido_combinacion;
	}

    public function update($pedido_id, $articulo_id, $combinacion_id, $numeroitem, $modulo_id, 
					$cantidad, $precio, $listaprecio_id, $incluyeimpuesto, $moneda_id, $descuento, 
					$categoria_id, $subcategoria_id, $linea_id, $ot_id, $observacion, $lote_id, $id)
    {
		$pedido_combinacion = $this->model->findOrFail($id)->update([
			'pedido_id' => $pedido_id,
			'articulo_id' => $articulo_id,
			'combinacion_id' => $combinacion_id,
			'numeroitem' => $numeroitem,
			'modulo_id' => $modulo_id,
			'cantidad' => $cantidad,
			'precio' => $precio,
			'listaprecio_id' => $listaprecio_id,
			'incluyeimpuesto' => $incluyeimpuesto,
			'moneda_id' => $moneda_id,
			'descuento' => ($descuento == '' ? 0 : $descuento),
			'categoria_id' => $categoria_id,
			'subcategoria_id' => $subcategoria_id,
			'linea_id' => $linea_id,
			'ot_id' => $ot_id,
			'lote_id' => $lote_id,
			'observacion' => $observacion
					]);
		//
		// Actualiza anita
		//self::actualizarAnita($data, $data['codigo']);

		return $pedido_combinacion;
    }

	public function updatePorOtId($id)
    {
    	$pedido = $this->model->find($id)->update(['ot_id'=>0]);

        return $pedido;
    }

    public function updatePorId(array $data, $id)
    {
        $pedido = $this->model->findOrFail($id)->update($data);
		//
		return $pedido;
    }

    public function delete($id)
    {
    	$pedido = $this->model->find($id);

        $pedido = $this->model->destroy($id);
		return $pedido;
    }

    public function deleteporpedido($pedido_id)
    {
    	$pedido = $this->model->where('pedido_id', $pedido_id)->delete();

		return $pedido;
    }

	public function leePedidosSinOtPorFecha($hastafecha) {
		return $this->model->select('pedido_combinacion.id as id',
									'pedido_combinacion.pedido_id as pedido_id')
			->join('pedido', 'pedido_combinacion.pedido_id', 'pedido.id')
			->where('pedido.fecha','<=',$hastafecha)
			->where('pedido_combinacion.ot_id',0)
			->get();
	}

    public function find($id)
    {
        if (null == $pedido = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido;
    }

    public function findOrFail($id)
    {
        if (null == $pedido = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido;
    }

	// Trae las referencias por item de pedido

    public function findPorPedidoCombinacionId($pedido_combinacion_id)
    {
    	$pedido_combinacion = $this->model->select('pedido_combinacion.id as id',  
									'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id as pedido_combinacion_talle_id',
									'ordentrabajo_combinacion_talle.ordentrabajo_stock_id as ordentrabajo_stock_id',
									'pedido_combinacion_talle.pedido_combinacion_id as pedido_combinacion_id',
									'ordentrabajo.id as ordentrabajo_id',
									'ordentrabajo.codigo as codigo',
									'articulo_movimiento.deposito_id as deposito_id')
							->join('pedido_combinacion_talle', 'pedido_combinacion_talle.pedido_combinacion_id', 'pedido_combinacion.id')
							->join('ordentrabajo_combinacion_talle', 'ordentrabajo_combinacion_talle.pedido_combinacion_talle_id', 'pedido_combinacion_talle.id')
							->join('ordentrabajo', 'ordentrabajo.id', 'ordentrabajo_combinacion_talle.ordentrabajo_id')
							->leftjoin('articulo_movimiento', 'articulo_movimiento.ordentrabajo_id', 'ordentrabajo.id')
							->where('pedido_combinacion.id', $pedido_combinacion_id)
							->get();

		return $pedido_combinacion;
    }

	public function findPorPedidoId($pedido_id)
    {
    	$pedido_combinacion = $this->model->select('pedido_combinacion.id as id', 
										'pedido_combinacion.ot_id as ot_id')
										->where('pedido_combinacion.pedido_id', $pedido_id)
										->get();

		return $pedido_combinacion;
    }

	public function findPorOrdenTrabajoId($ordentrabajo_id)
    {
    	$pedido_combinacion = $this->model->select('pedido_combinacion.id as id', 
										'pedido_combinacion.pedido_id as ot_id')
										->where('pedido_combinacion.ot_id', $ordentrabajo_id)
										->get();

		return $pedido_combinacion;
    }

    public function sincronizarConAnita()
	{
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "penm_sucursal, penm_nro", 
            			'whereArmado' => " WHERE penm_tipo='PED' and penm_fecha>20230624 ",
						'tabla' => "pendmae" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = self::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
			$claveAnita = $value->{$this->keyFieldAnita[0]}.'-'.$value->{$this->keyFieldAnita[1]};
            if (!in_array(ltrim($claveAnita, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, true);
            }
			else
			{
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, false);
			}
        }
    }

    private function traerRegistroDeAnita($sucursal, $nro, $fl_crea_registro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			    penv_cliente, penv_tipo, penv_letra, penv_sucursal, penv_nro, penv_orden, penv_articulo, penv_desc,
    			penv_agrupacion, penv_unidad_medida, penv_cantidad, penv_cantaentr, penv_cantentr, penv_cantfact, penv_precio,
    			penv_dto_art, penv_deposito, penv_tipo_iva, penv_fecha, penv_incl_impuesto, penv_cod_mon, penv_vendedor,
    			penv_zonavta, penv_zonamult, penv_partida, penv_fecha_ent, penv_color, penv_medida, penv_linea,
    			penv_forro, penv_imprime_ped, penv_observacion, penv_nro_orden, penv_fondo, penv_color_fondo, penv_capellada,
    			penv_color_cap, penv_color_forro, penv_aplique, penv_modulo1, penv_modulo2, penv_modulo3
			',
            'whereArmado' => " WHERE penv_tipo='PED' and penv_letra='A' and penv_sucursal='".
				$sucursal."' and penv_nro='".$nro."' " 
        );
        $data = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($data) > 0) 
		{
			$i = 0;
        	while ($i < count($data))
			{
			  	$codigo = $data[$i]->penv_tipo.'-'.$data[$i]->penv_letra.'-'.
						str_pad($data[$i]->penv_sucursal, 5, "0", STR_PAD_LEFT).'-'.
						str_pad($data[$i]->penv_nro, 8, "0", STR_PAD_LEFT);
        		$pedido = $this->pedidoQuery->leePedidoporCodigo($codigo);
				if ($pedido)
					$pedido_id = $pedido->id;
				else
					return;
					//$pedido_id = NULL;
	
        		$articulo = Articulo::select('id', 'subcategoria_id', 'linea_id', 'sku')
							->where('sku', ltrim($data[$i]->penv_articulo, '0'))->first();
				if ($articulo)
				{
					$articulo_id = $articulo->id;
					$subcategoria_id = $articulo->subcategoria_id;
					$linea_id = $articulo->linea_id;
					$sku = $articulo->sku;
				}
				else
				{
					$Articulo = new Articulo();
        			$Articulo->traerRegistroDeAnita($data[$i]->penv_articulo, true);

        			$articulo = Articulo::select('id', 'subcategoria_id', 'linea_id', 'sku')
							->where('sku', ltrim($data[$i]->penv_articulo, '0'))->first();

					if ($articulo)
					{
						$articulo_id = $articulo->id;
						$subcategoria_id = $articulo->subcategoria_id;
						$linea_id = $articulo->linea_id;
						$sku = $articulo->sku;
					}
					else
					{
						$articulo_id = NULL;
						$subcategoria_id = NULL;
						$linea_id = NULL;
						$sku = NULL;
					}
				}
	
        		$combinacion = Combinacion::select('id')->where('articulo_id', $articulo_id)->where('codigo' , $data[$i]->penv_capellada)->first();
				if ($combinacion)
					$combinacion_id = $combinacion->id;
				else
				{
					$Combinacion = new Combinacion();
        			$Combinacion->traerRegistroDeAnita($data[$i]->penv_articulo, $data[$i]->penv_capellada);

					$Capeart = new Capeart();
    				$Capeart->sincronizarConAnita($data[$i]->penv_articulo, $data[$i]->penv_capellada);

					$Avioart = new Avioart();
    				$Avioart->sincronizarConAnita($data[$i]->penv_articulo, $data[$i]->penv_capellada);

        			$combinacion = Combinacion::select('id')->where('articulo_id', $articulo_id)->where('codigo' , 
						$data[$i]->penv_capellada)->first();
					if ($combinacion)
						$combinacion_id = $combinacion->id;
					else
					{
        				$combinacion = Combinacion::select('id')->where('articulo_id', $articulo_id)->first();

						$combinacion_id = $combinacion->id;
					}
				}
	
        		$modulo = Modulo::select('id')->where('codigo', $data[$i]->penv_modulo1)->first();
				if ($modulo)
					$modulo_id = $modulo->id;
				else
				{
					if ($data[$i]->penv_modulo1 == 0)
				  		$modulo_id = 30;
			  		else
				  		$modulo_id = $data[$i]->penv_modulo1;
				}
	
				$listaprecio_id = 5;
				if ($data[$i]->penv_medida <= 26)
					$listaprecio_id = 1;
				if ($data[$i]->penv_medida >= 27 && $data[$i]->penv_medida <= 33)
					$listaprecio_id = 2;
				if ($data[$i]->penv_medida >= 34 && $data[$i]->penv_medida <= 40)
					$listaprecio_id = 3;

				$incluyeimpuesto = $data[$i]->penv_incl_impuesto;
				$moneda_id = $data[$i]->penv_cod_mon;
	
        		$categoria = Categoria::select('id')->where('codigo' , ltrim($data[$i]->penv_agrupacion, '0'))->first();
				if ($categoria)
					$categoria_id = $categoria->id;
				else
					$categoria_id = NULL;
	
				// Acumula para grabar renglon entero ya que de anita viene por medida
				$cantidad = $aentregar = $entregada = $facturada = $precio = 0.;
				$anter_orden = $data[$i]->penv_orden;
				$precio = $data[$i]->penv_precio;
				$descuento = $data[$i]->penv_dto_art;
				$ot_id = $data[$i]->penv_nro_orden;
				$observacion = $data[$i]->penv_observacion;
				$codigo = $data[$i]->penv_tipo.'-'.$data[$i]->penv_letra.'-'.
							str_pad($data[$i]->penv_sucursal, 5, "0", STR_PAD_LEFT).'-'.
							str_pad($data[$i]->penv_nro, 8, "0", STR_PAD_LEFT);

				while (($i < count($data)) ? $anter_orden == $data[$i]->penv_orden : false) 
				{
					$cantidad += $data[$i]->penv_cantidad;
					$i ++;
				}

				$arr_campos = [
					"pedido_id" => $pedido_id,
					"combinacion_id" => $combinacion_id,
            		"articulo_id" => $articulo_id,
            		"numeroitem" => $anter_orden,
            		"modulo_id" => $modulo_id,
					"cantidad" => $cantidad,
					"precio" => $precio,
					"listaprecio_id" => $listaprecio_id,
					"incluyeimpuesto" => $incluyeimpuesto,
					"moneda_id" => $moneda_id,
					"descuento" => $descuento,
					"categoria_id" => $categoria_id,
					"subcategoria_id" => $subcategoria_id,
					"linea_id" => $linea_id,
					"ot_id" => $ot_id,
					"observacion" => $observacion,
					"codigo" => $codigo
            		];

				if ($fl_crea_registro)
            		$this->model->create($arr_campos);
				else
            		$this->model->where('codigo', ltrim($data[$i]->clim_cliente, '0'))->update($arr_campos);
        	}
		}
    }

	private function guardarAnita(array $data, $articulo_id, $combinacion_id, $numeroitem,
	  								$modulo_id, $cantidad, $precio, $listaprecio_id, $incluyeimpuesto, 
	  								$moneda_id, $descuento, $medidas, $observacion, $funcion)
	{
		return 0;

        $apiAnita = new ApiAnita();

		$jtalles = json_decode($medidas);
		$cliente_id = $data['cliente_id'];
		$codigo_pedido = $data['codigo'];
		$fechapedido = $data['fecha'];
		$fechaentrega = $data['fechaentrega'];
		$vendedor = $data['vendedor_id'];
		$nro_orden = $data['nro_orden'];
		$cliente = '';
		$sku = '';

		foreach ($jtalles as $value)
		{
		  	$this->setCamposAnita($cliente_id, $cliente, $codigo_pedido, $tipo, $letra, $sucursal, $nro, 
								$value->talle_id, $medida,
								$articulo_id, $sku, $desc, $agrupacion, $linea, $unidad_medida, $tipoiva, 
								$combinacion_id, $codigo_combinacion,
								$fechapedido, $zonavta, $zonamult, $fechaentrega);

       		if ($value->cantidad != 0)
			{
       			$data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
           			'campos' => ' 
		    			penv_cliente, penv_tipo, penv_letra, penv_sucursal, penv_nro, penv_orden, penv_articulo, penv_desc,
   						penv_agrupacion, penv_unidad_medida, penv_cantidad, penv_cantaentr, penv_cantentr, penv_cantfact,
   						penv_precio, penv_dto_art, penv_deposito, penv_tipo_iva, penv_fecha, penv_incl_impuesto,
   						penv_cod_mon, penv_vendedor, penv_zonavta, penv_zonamult, penv_partida, penv_fecha_ent, penv_color,
   						penv_medida, penv_linea, penv_forro, penv_imprime_ped, penv_observacion, penv_nro_orden, penv_fondo,
   						penv_color_fondo, penv_capellada, penv_color_cap, penv_color_forro, penv_aplique, penv_modulo1,
   						penv_modulo2, penv_modulo3
							',
           			'valores' => " 
						'".str_pad($cliente, 6, "0", STR_PAD_LEFT)."', 
						'".$tipo."', 
						'".$letra."', 
						'".$sucursal."', 
						'".$nro."', 
						'".$numeroitem."', 
						'".str_pad($sku, 13, "0", STR_PAD_LEFT)."', 
						'".$desc."', 
						'".str_pad($agrupacion, 4, "0", STR_PAD_LEFT)."', 
						'".$unidad_medida."', 
						'".$value->cantidad."', 
						'".'0'."', 
						'".'0'."', 
						'".'0'."', 
						'".$value->precio."', 
						'".$descuento."', 
						'".'1'."', 
						'".$tipoiva."', 
						'".$fechapedido."', 
						'".$incluyeimpuesto."', 
						'".'1'."', 
						'".$vendedor."', 
						'".$zonavta."', 
						'".$zonamult."', 
						'".$listaprecio_id."', 
						'".$fechaentrega."', 
						'".'0'."', 
						'".$medida."', 
						'".str_pad($linea, 6, "0", STR_PAD_LEFT)."', 
						'".' '."', 
						'".'N'."', 
						'".$observacion."', 
						'".($funcion == 'create' ? '-1' : $nro_orden)."', 
						'".'0'."', 
						'".'0'."', 
						'".$codigo_combinacion."', 
						'".'0'."', 
						'".'0'."', 
						'".'0'."', 
						'".$modulo_id."', 
						'".'0'."', 
						'".'0'."' "
       				);
    			$apiAnita->apiCall($data);
			}
		}
	}

	public function actualizarAnitaEstado($estado, $codigo, $orden) {
		return 0;
        $apiAnita = new ApiAnita();

		// Convierte tipo
		$tipo = substr($codigo, 0, 3);
		$letra = substr($codigo, 4, 1);
		$sucursal = substr($codigo, 6, 5);
		$nro = substr($codigo, 12, 8);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
    			penv_imprime_ped       = '".$estado."' "
					,
				'whereArmado' => " WHERE penv_tipo = '".$tipo."' AND penv_letra = '".$letra."' 
									AND penv_sucursal = '".$sucursal."' AND penv_nro = '".$nro."'
									AND penv_orden = '".$orden."'" );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnitaNroOt($nro_orden, $codigo, $orden) {
		return 0;
        $apiAnita = new ApiAnita();

		// Convierte tipo
		$tipo = substr($codigo, 0, 3);
		$letra = substr($codigo, 4, 1);
		$sucursal = substr($codigo, 6, 5);
		$nro = substr($codigo, 12, 8);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
    			penv_nro_orden       = '".$nro_orden."' "
					,
				'whereArmado' => " WHERE penv_tipo = '".$tipo."' AND penv_letra = '".$letra."' 
									AND penv_sucursal = '".$sucursal."' AND penv_nro = '".$nro."'
									AND penv_orden = '".$orden."'" );
        $apiAnita->apiCall($data);
	}

	public function cierrePedidoAnita($hastafecha) {
		return 0;
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
    				penv_imprime_ped       = 'C',
					penv_cantentr          = penv_cantidad",
				'whereArmado' => " WHERE penv_fecha <= '".$hastafecha."' AND penv_nro_orden <= 0 AND penv_tipo='PED'");
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($tipo, $letra, $sucursal, $nro, $numeroitem = null) {
		return 0;
        $apiAnita = new ApiAnita();

		if ($numeroitem)
		{
        	$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE penv_tipo = '".$tipo."' AND penv_letra = '".$letra."' 
									AND penv_sucursal = '".$sucursal."' AND penv_nro = '".$nro."' 
									AND penv_orden = '".$numeroitem."' "
						);
		}
		else
		{
        	$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE penv_tipo = '".$tipo."' AND penv_letra = '".$letra."' 
									AND penv_sucursal = '".$sucursal."' AND penv_nro = '".$nro."' "
						);
		}
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo($tipo, $letra, $sucursal, &$nro) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE penm_tipo = '".$tipo."' AND penm_letra = '".$letra."' 
									AND penm_sucursal = '".$sucursal."' ",
				'campos' => " max(penm_nro) as ultnumero "
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) 
		{
			$nro = ltrim($dataAnita[0]->ultnumero, '0');
			$nro = $nro + 1;
		}
	}

	private function setCamposAnita($cliente_id, &$cliente, 
	  			$codigo_pedido, &$tipo, &$letra, &$sucursal, &$nro, 
				$talle_id, &$medida,
				$articulo_id, &$sku, &$desc, &$agrupacion, &$linea, &$unidadmedida, &$tipoiva, 
				$combinacion_id, &$codigo_combinacion,
				&$fechapedido, &$zonavta, &$zonamult, &$fechaentrega)
	{
    	$Cliente = $this->clienteQuery->traeClienteporId($cliente_id);
		if ($Cliente)
		{
			$cliente = $Cliente->codigo;
			$zonavta = $Cliente->zonavta_id;
			$zonamult = $Cliente->provincia_id;
		}
		else
		{
			$cliente = ' ';
			$zonavta = 0;
			$zonamult = 0;
		}
		if ($zonavta == '')
			$zonavta = 0;
		if ($zonamult == '')
			$zonamult = 0;

		$tipo = substr($codigo_pedido, 0, 3);
		$letra = substr($codigo_pedido, 4, 1);
		$sucursal = substr($codigo_pedido, 6, 5);
		$nro = substr($codigo_pedido, 12, 8);

		$Articulo = Articulo::select('id', 'sku', 'descripcion', 'categoria_id', 'unidadmedida_id', 'impuesto_id', 
		  					'linea_id')->where('id' , $articulo_id)->first();
		if ($Articulo)
		{
			$sku = $Articulo->sku;
			$desc = $Articulo->descripcion;

			// Lee agrupacion 
			$Categoria = Categoria::select('id', 'codigo')->where('id' , $Articulo->categoria_id)->first();
			if ($Categoria)
			  	$agrupacion = $Categoria->id;
		  	else
				$agrupacion = ' ';
			
			// Lee unidad de medida 
			$Unidadmedida = Unidadmedida::select('id', 'abreviatura')->where('id' , $Articulo->unidadmedida_id)->first();
			if ($Unidadmedida)
			  	$unidadmedida = $Unidadmedida->abreviatura;
		  	else
				$unidadmedida = "PAR";
			
			$tipoiva = $Articulo->impuesto_id;

			// Lee linea 
			$Linea = Linea::select('id', 'codigo')->where('id' , $Articulo->linea_id)->first();
			if ($Linea)
			  	$linea = $Linea->codigo;
		  	else
				$linea = ' ';

			$Combinacion = Combinacion::select('id', 'codigo')->where('id' , $combinacion_id)->first();
			if ($Combinacion)
			  	$codigo_combinacion = $Combinacion->codigo;
		  	else
			  	$codigo_combinacion = '0';
		}
		else
		{
			$sku = ' ';
			$desc = ' ';
			$agrupacion = ' ';
			$unidadmedida = ' ';
			$tipoiva = '3';
			$linea = ' ';
			$codigo_combinacion = '0';
		}

		$Talle = Talle::select('nombre')->where('id' , $talle_id)->first();

		if ($Talle)
			$medida = $Talle->nombre;
		else
			$medida = 0;

		$fechapedido = date('Ymd', strtotime($fechapedido));
		$fechaentrega = date('Ymd', strtotime($fechaentrega));
	}
}
