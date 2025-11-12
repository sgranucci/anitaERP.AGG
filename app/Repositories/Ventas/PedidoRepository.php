<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Pedido;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Transporte;
use App\Queries\Ventas\ClienteQueryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class PedidoRepository implements PedidoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'pendmae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = ['penm_sucursal', 'penm_nro'];

	private $clienteQuery;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido $pedido,
    							ClienteQueryInterface $clientequery)
    {
        $this->model = $pedido;
		$this->clienteQuery = $clientequery;
    }

    public function create(array $data)
    {
		// Graba ERP
        $pedido = $this->model->create($data);

		// Graba anita
		if ($pedido)
			self::guardarAnita($data);

		return $pedido;
    }

    public function update(array $data, $id)
    {
        $pedido = $this->model->findOrFail($id)->update($data);
		//
		return $pedido;
    }

    public function delete($id)
    {
    	$pedido = self::find($id);

		$tipo = substr($pedido->codigo, 0, 3);
		$letra = substr($pedido->codigo, 4, 1);
		$sucursal = substr($pedido->codigo, 6, 5);
		$nro = substr($pedido->codigo, 12, 8);

		//
		// Elimina anita
		self::eliminarAnita($tipo, $letra, $sucursal, $nro);

        $pedido = $this->model->destroy($id);
		return $pedido;
    }
    
	public function all()
    {
        return $this->model->get();
    }
	
    public function find($id)
    {
        if (null == $pedido = $this->model->with("pedido_articulos")->with("pedido_articulo_cajas")->with("clientes")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return $pedido;
    }

    public function findOrFail($id)
    {
        if (null == $pedido = $this->model->with("pedido_articulos")->with("pedido_articulo_cajas")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "penm_sucursal, penm_nro", 
            			'whereArmado' => " WHERE penm_tipo='PED' AND penm_fecha>20230624 ",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Pedido::all();
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
    			penm_cliente,
    			penm_tipo,
    			penm_letra,
    			penm_sucursal,
    			penm_nro,
    			penm_ref_tipo,
    			penm_ref_letra,
    			penm_ref_sucursal,
    			penm_ref_nro,
    			penm_fecha,
    			penm_fecha_ent,
    			penm_cond_vta,
    			penm_deposito,
    			penm_vendedor,
    			penm_zonavta,
    			penm_entrega,
    			penm_dto,
    			penm_expreso,
    			penm_o_compra,
    			penm_razon_susp,
    			penm_cod_mon,
    			penm_cotizacion,
    			penm_fecha_ing,
    			penm_hora_ing,
    			penm_estado,
    			penm_leyenda,
    			penm_tipo_fact,
    			penm_letra_fact,
    			penm_sucursal_fact,
    			penm_nro_fact,
    			penm_dto_integrado,
    			penm_cod_entrega
			',
            'whereArmado' => " WHERE penm_tipo='PED' AND penm_letra='A' AND penm_sucursal='".$sucursal."' AND penm_nro='".$nro."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			if ($data->penm_fecha < 20230100)
				return -1;

            if ($data->penm_cond_vta == 0)
				$condicionventa_id = 1;
			else
				$condicionventa_id = $data->penm_cond_vta;

			if ($condicionventa_id == 1)
				$condicionventa_id = 3;
				
			if ($data->penm_vendedor == 0)
				$vendedor_id = 1;
			else
				$vendedor_id = $data->penm_vendedor;

        	$cliente = $this->clienteQuery->traeClienteporCodigo(ltrim($data->penm_cliente, '0'));
			if ($cliente)
				$cliente_id = $cliente->id;
			else
				return -1;
	
        	$transporte = Transporte::select('id', 'codigo')->where('codigo' , $data->penm_expreso)->first();
			if ($transporte)
				$transporte_id = $transporte->id;
			else
				$transporte_id = NULL;

			$codigo = $data->penm_tipo.'-'.$data->penm_letra.'-'.
						str_pad($data->penm_sucursal, 5, "0", STR_PAD_LEFT).'-'.
						str_pad($data->penm_nro, 8, "0", STR_PAD_LEFT);
        	$fecha_hoy = Carbon::now();
	
			$arr_campos = [
				"fecha" => $data->penm_fecha,
				"fechaentrega" => $data->penm_fecha_ent,
            	"cliente_id" => $cliente_id,
            	"condicionventa_id" => $condicionventa_id,
				"vendedor_id" => $vendedor_id,
				"transporte_id" => $transporte_id,
				"mventa_id" => $data->penm_sucursal > 5 ? 1 : $data->penm_sucursal,
				"estado" => $data->penm_estado,
				"usuario_id" => $usuario_id,
				"leyenda" => $data->penm_leyenda,
				"descuento" => $data->penm_dto,
				"descuentointegrado" => $data->penm_dto_integrado,
				"lugarentrega" => $data->penm_entrega,
				"codigo" => $codigo,
				"created_at" => $fecha_hoy
            	];

			if ($fl_crea_registro)
            	//$this->model->create($arr_campos);
				$this->model->insert($arr_campos);
			else
            	$this->model->where('codigo', $codigo)->update($arr_campos);
        }
    }

	public function guardarAnita($request) {
		return 0;

        $apiAnita = new ApiAnita();

		$this->setCamposAnita($request, $cliente, $tipo, $letra, $sucursal, $nro, $fechapedido, $fechaentrega, $zonavta, $descuento, $fechahoy, $horahoy);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
    			penm_cliente,
    			penm_tipo,
    			penm_letra,
    			penm_sucursal,
    			penm_nro,
    			penm_ref_tipo,
    			penm_ref_letra,
    			penm_ref_sucursal,
    			penm_ref_nro,
    			penm_fecha,
    			penm_fecha_ent,
    			penm_cond_vta,
    			penm_deposito,
    			penm_vendedor,
    			penm_zonavta,
    			penm_entrega,
    			penm_dto,
    			penm_expreso,
    			penm_o_compra,
    			penm_razon_susp,
    			penm_cod_mon,
    			penm_cotizacion,
    			penm_fecha_ing,
    			penm_hora_ing,
    			penm_estado,
    			penm_leyenda,
    			penm_tipo_fact,
    			penm_letra_fact,
    			penm_sucursal_fact,
    			penm_nro_fact,
    			penm_dto_integrado,
    			penm_cod_entrega
				',
            'valores' => " 
				'".str_pad($cliente, 6, "0", STR_PAD_LEFT)."', 
				'".$tipo."', 
				'".$letra."', 
				'".$sucursal."', 
				'".$nro."', 
				'".' '."', 
				'".' '."', 
				'".'0'."', 
				'".'0'."', 
				'".$fechapedido."', 
				'".$fechaentrega."', 
				'".$request['condicionventa_id']."', 
				'".'1'."', 
				'".$request['vendedor_id']."', 
				'".$zonavta."', 
				'".$request['lugarentrega']."', 
				'".$descuento."', 
				'".$request['transporte_id']."', 
				'".' '."', 
				'".' '."', 
				'".'1'."', 
				'".'0'."', 
				'".$fechahoy."', 
				'".$horahoy."', 
    			'".$request['estado']."',
    			'".($request['leyenda']??' ')."',
				'".' '."', 
				'".' '."', 
				'".'0'."', 
				'".'0'."', 
    			'".($request['descuentointegrado']??' ')."',
				'".'0'."' "
        );
        $apiAnita->apiCall($data);
	}

	private function actualizarAnita($request, $id) {
		return 0;
        $apiAnita = new ApiAnita();

		$this->setCamposAnita($request, $cliente, $tipo, $letra, $sucursal, $nro, $fechapedido, $fechaentrega, $zonavta, $descuento, $fechahoy, $horahoy);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
                penm_cliente 	      = '".str_pad($cliente, 6, "0", STR_PAD_LEFT)."',
    			penm_fecha            = '".$fechapedido."', 
    			penm_fecha_ent        = '".$fechaentrega."',
    			penm_cond_vta         = '".$request['condicionventa_id']."',
    			penm_vendedor         = '".$request['vendedor_id']."',
    			penm_zonvta           = '".$zonavta."',
    			penm_entrega          = '".$request['lugarentrega']."',
    			penm_dto              = '".$descuento."',
    			penm_expreso          = '".$request['transporte_id']."', 
    			penm_fecha_ing        = '".$fechahoy."',
    			penm_hora_ing         = '".$horahoy."',
    			penm_estado           = '".$request['estado']."',
    			penm_leyenda          = '".$request['leyenda']??' '."',
    			penm_dto_integrado    = '".$request['descuentointegrado']."', 
    			penm_cod_entreg       = '".'0'."' "
					,
				'whereArmado' => " WHERE penm_tipo = '".$tipo."' AND penm_letra = '".$letra."' 
									AND penm_sucursal = ".$sucursal."' AND penm_nro = ".$nro."' " );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($tipo, $letra, $sucursal, $nro) {
		return 0;
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE penm_tipo = '".$tipo."' AND penm_letra = '".$letra."' 
									AND penm_sucursal = '".$sucursal."' AND penm_nro = '".$nro."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	public function ultimoCodigoAnita($tipo, $letra, $sucursal, &$nro) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(penm_nro) as ".$this->keyFieldAnita[1]." ",
				'whereArmado' => " WHERE penm_tipo = '".$tipo."' AND penm_letra = '".$letra."' 
									AND penm_sucursal = '".$sucursal."' " 
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if ($dataAnita)
		{
			$nro = ltrim($dataAnita[0]->{$this->keyFieldAnita[1]}, '0');
			$nro = $nro + 1;
		}
	}

	private function setCamposAnita($request, &$cliente, &$tipo, &$letra, &$sucursal, &$nro, &$fechapedido, &$fechaentrega, &$zonavta, &$descuento, &$fechahoy, &$horahoy)
	{
        $fecha = Carbon::now();
		$fechahoy = $fecha->format('Ymd');
		$horahoy = $fecha->format('His');

       	$clientes = $this->clienteQuery->traeClienteporId($request['cliente_id']);

		if ($clientes)
		{
			$cliente = $clientes->codigo;
			$zonavta = $clientes->zonavta_id;
		}
		else
		{
			$cliente = NULL;
			$zonavta = NULL;
		}

		$tipo = substr($request['codigo'], 0, 3);
		$letra = substr($request['codigo'], 4, 1);
		$sucursal = substr($request['codigo'], 6, 5);
		$nro = substr($request['codigo'], 12, 8);

		$fechapedido = date('Ymd', strtotime($request['fecha']));
		$fechaentrega = date('Ymd', strtotime($request['fechaentrega']));

		$descuento = $request['descuento'] ?? 0;
	}
}
