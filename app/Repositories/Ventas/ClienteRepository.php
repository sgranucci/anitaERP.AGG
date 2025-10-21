<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Models\Ventas\Zonavta;
use App\Models\Ventas\Subzonavta;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Transporte;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Mventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class ClienteRepository implements ClienteRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['climae', 'cliley', 'clicomi'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'clim_cliente';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente $cliente)
    {
        $this->model = $cliente;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;
		$data['estado'] = '0';

		if ($data['retieneiva'] == null)
			$data['retieneiva'] = 'N';

		if ($data['condicioniibb'] == null)
			$data['condicioniibb'] = 'N';

        $cliente = $this->model->create($data);

		// Graba anita
		self::guardarAnita($data);

		return $cliente;
    }

    public function update(array $data, $id)
    {
        $cliente = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $cliente;

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
    	$cliente = Cliente::find($id);

		// Elimina anita
		//if ($cliente)
			//self::eliminarAnita($cliente->codigo);

        $cliente = $this->model->destroy($id);

		return $cliente;
    }

    public function find($id)
    {
        if (null == $cliente = $this->model->with("cliente_entregas")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")->with("tipossuspensioncliente")->find($id)) 
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente;
    }

    public function findOrFail($id)
    {
        if (null == $cliente = $this->model->with("cliente_entregas")->with("cliente_archivos")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cliente;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'ventas',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		/*for ($ii = 994; $ii < count($dataAnita); $ii++)
		{
        	$this->traerRegistroDeAnita($dataAnita[$ii]->clim_cliente, true);
		}*/

        $datosLocal = Cliente::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, true);
            }
			else
			{
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, false);
			}
        }
    }

    private function traerRegistroDeAnita($key, $fl_crea_registro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
			'sistema' => 'ventas',
            'campos' => '
			clim_cliente,
    		clim_nombre,
    		clim_contacto,
    		clim_direccion,
    		clim_localidad,
    		clim_cod_postal,
    		clim_provincia,
    		clim_telefono,
    		clim_cuit,
    		clim_cond_iva,
    		clim_porc_excen,
    		clim_letra,
    		clim_cond_venta,
    		clim_cta_contable,
    		clim_credito,
    		clim_dias_atraso,
    		clim_zonavta,
    		clim_subzona,
    		clim_zonamult,
    		clim_vendedor,
    		clim_cobrador,
    		clim_expreso,
    		clim_tipo_empresa,
    		clim_dir_cobranza,
	    	clim_hs_cobranza,
    		clim_lugar_entrega,
    		clim_retiene_iva,
    		clim_lista_precio,
    		clim_descuento,
    		clim_nro_interno,
    		clim_fecha_interes,
    		clim_proveedor,
    		clim_minimo_fact,
    		clim_estado_cli,
    		clim_dias_cobranza,
    		clim_dias_atencion
    		clim_hs_atencion,
    		clim_pais,
    		clim_perc_ing_br,
    		clim_nro_ing_br,
    		clim_dir_postal,
    		clim_loc_postal,
			clim_cp_postal,
			clim_fantasia,
			clim_fecha_alta,
			clim_ley_liberado,
			clim_regimen,
			clim_leyenda_fact,
			clim_prov_postal,
			clim_lugar_de_pago,
			clim_excl_perc_iva,
			clim_fe_excl_piva,
			clim_dto_integrado,
			clim_fecha_boletin,
			clim_e_mail,
			clim_fax
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
			'sistema' => 'ventas',
            'campos' => '
			clil_cliente,
    		clil_leyenda
			',
            'whereArmado' => " WHERE clil_cliente = '".$key."' " 
        );
        $dataleyAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$provincia_id = NULL;
	
			$localidad = Localidad::select('id', 'nombre')->where('nombre' , '=', $data->clim_localidad)->where('codigopostal','=',$data->clim_cod_postal)->first();
			if ($localidad)
				$localidad_id = $localidad->id;
			else
				$localidad_id = NULL;
	
        	$pais = Pais::select('id', 'nombre')->where('id' , $data->clim_pais)->first();
			if ($pais)
				$pais_id = $pais->id;
			else
				$pais_id = 1;
	
        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->clim_cta_contable)->first();
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;
	
        	$zonavta = Zonavta::select('id')->where('id' , $data->clim_zonavta)->first();
			if ($zonavta)
				$zonavta_id = $zonavta->id;
			else
				$zonavta_id = NULL;
	
        	$subzonavta = Subzonavta::select('id')->where('id' , $data->clim_subzona)->first();
			if ($subzonavta)
				$subzonavta_id = $subzonavta->id;
			else
				$subzonavta_id = NULL;
	
        	$vendedor = Vendedor::select('id')->where('id' , $data->clim_vendedor)->first();
			if ($vendedor)
				$vendedor_id = $vendedor->id;
			else
				$vendedor_id = NULL;
	
        	$condicionventa = Condicionventa::select('id')->where('id' , $data->clim_cond_venta)->first();
			if ($condicionventa)
				$condicionventa_id = $condicionventa->id;
			else
				$condicionventa_id = NULL;
	
        	$listaprecio = Listaprecio::select('id')->where('id' , $data->clim_lista_precio)->first();
			if ($listaprecio)
				$listaprecio_id = $listaprecio->id;
			else
				$listaprecio_id = NULL;

       		$transporte = Transporte::select('id', 'codigo')->where('codigo' , $data->clim_expreso)->first();
			if ($transporte)
				$transporte_id = $transporte->id;
			else
				$transporte_id = NULL;

			$condicioniva_id = 1;
			switch($data->clim_cond_iva)
			{
			case '0':
				$condicioniva_id = 1;
				break;
			case '3':
				$condicioniva_id = 3;
				break;
			case '4':
				if ($data->clim_letra == 'E')
					$condicioniva_id = 5;
				else
					$condicioniva_id = 2;
				break;
			case '5':
				$condicioniva_id = 4;
				break;
			}
			$condicioniibb = 'C';
			switch($data->clim_perc_ing_br)
			{
			case '1':
				$condicioniibb = 'E';
				break;
			case '2':
			case '4':
			case '5':
			case 'C':
			case 'A':
				$condicioniibb = 'C';
				break;
			case '3':
			case '6':
				$condicioniibb = 'L';
				break;
			case 'N':
			case 'E':
				$condicioniibb = 'N';
				break;
			}

			// Lee las leyendas
			$leyenda = "";
			foreach ($dataleyAnita as $ley)
				$leyenda .= $ley->clil_leyenda;

			$arr_campos = [
				"nombre" => $data->clim_nombre,
				"codigo" => ltrim($data->clim_cliente, '0'),
            	"contacto" => $data->clim_contacto,
            	"fantasia" => $data->clim_fantasia,
				"email" => $data->clim_e_mail,
				"telefono" => $data->clim_telefono.' '.$data->clim_fax,
				"urlweb" => ' ',
				"domicilio" => $data->clim_direccion,
				"localidad_id" => $localidad_id,
				"provincia_id" => $provincia_id,
				"pais_id" => $pais_id,
				"codigopostal" => $data->clim_cod_postal,
				"zonavta_id" => $zonavta_id,
				"subzonavta_id" => $subzonavta_id,
				"vendedor_id" => $vendedor_id,
				"transporte_id" => $transporte_id,
				"nroinscripcion" => $data->clim_cuit,
				"condicioniva_id" => $condicioniva_id,
				"retieneiva" => $data->clim_retiene_iva,
				"nroiibb" => $data->clim_nro_ing_br,
				"condicioniibb" => $condicioniibb,
				"condicionventa_id" => $condicionventa_id,
				"listaprecio_id" => $listaprecio_id,
				"descuento" => $data->clim_descuento,
				"cuentacontable_id" => $cuentacontable_id,
				"vaweb" => 'N',
				"estado" => $data->clim_estado_cli,
				"leyenda" => $leyenda,
				"usuario_id" => $usuario_id,
            	];
	
			if ($fl_crea_registro)
            	Cliente::create($arr_campos);
			else
            	Cliente::where('codigo', ltrim($data->clim_cliente, '0'))->update($arr_campos);
        }
    }

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte);
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

        $data = array( 'tabla' => $this->tableAnita[0], 'acc' => 'insert',
			'sistema' => 'ventas',
            'campos' => ' 
				clim_cliente,
    			clim_nombre,
    			clim_contacto,
    			clim_direccion,
    			clim_localidad,
    			clim_cod_postal,
    			clim_provincia,
    			clim_telefono,
    			clim_cuit,
    			clim_cond_iva,
    			clim_porc_excen,
    			clim_letra,
    			clim_cond_venta,
    			clim_cta_contable,
    			clim_credito,
    			clim_dias_atraso,
    			clim_zonavta,
    			clim_subzona,
    			clim_zonamult,
    			clim_vendedor,
    			clim_cobrador,
    			clim_expreso,
    			clim_tipo_empresa,
    			clim_dir_cobranza,
	    		clim_hs_cobranza,
    			clim_lugar_entrega,
    			clim_retiene_iva,
    			clim_lista_precio,
    			clim_descuento,
    			clim_nro_interno,
    			clim_fecha_interes,
    			clim_proveedor,
    			clim_minimo_fact,
    			clim_estado_cli,
    			clim_dias_cobranza,
    			clim_dias_atencion,
    			clim_hs_atencion,
    			clim_pais,
    			clim_perc_ing_br,
    			clim_nro_ing_br,
    			clim_dir_postal,
    			clim_loc_postal,
				clim_cp_postal,
				clim_fantasia,
				clim_fecha_alta,
				clim_ley_liberado,
				clim_regimen,
				clim_leyenda_fact,
				clim_prov_postal,
				clim_lugar_de_pago,
				clim_excl_perc_iva,
				clim_fe_excl_piva,
				clim_dto_integrado,
				clim_fecha_boletin,
				clim_e_mail,
				clim_fax
				',
            'valores' => " 
				'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
				'".$nombre."',
				'".$contacto."',
				'".$domicilio."',
				'".$request['desc_localidad']."',
				'".$request['codigopostal']."',
				'".$request['desc_provincia']."',
				'".$request['telefono']."',
				'".$request['nroinscripcion']."',
				'".$condicioniva."',
				'0',
				'".$request['letra']."',
				'".($request['condicionventa_id']>0?$request['condicionventa_id']:0)."',
				'".$cuentacontable."',
				'0',
				'0',
				'".($request['zonavta_id']>0?$request['zonavta_id']:0)."',
				'".($request['subzonavta_id']>0?$request['subzonavta_id']:0)."',
				'".$request['provincia_id']."',
				'".($request['vendedor_id']>0?$request['vendedor_id']:0)."',
				'".($request['vendedor_id']>0?$request['vendedor_id']:0)."',
				'".$codigotransporte."',
				'0',
				' ',
				' ',
				' ',
				'".$request['retieneiva']."',
				'".($request['listaprecio_id'] > 0 ? $request['listaprecio_id'] : 0)."',
				'".($request['descuento'] > 0 ? $request['descuento'] : 0)."',
				'0',
				'0',
				' ',
				'0',
				'".$request['estado']."',
				' ',
				' ',
				' ',
				'".$request['pais_id']."',
				'".$condicioniibb."',
				'".$request['nroiibb']."',
				' ',
				' ',
				' ',
				'".$request['fantasia']."',
				'".$fecha."',
				' ',
				'0',
				'0',
				' ',
				' ',
				' ',
				'0',
				' ',
				'0',
                '".$request['email']."',
				'".'FAX'."' "
        );
        $climae = $apiAnita->apiCall($data);

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clil_cliente,
								clil_linea,
								clil_leyenda
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$apiAnita->apiCall($data);
		}

		// Graba comisiones
		if ($request['vendedor_id'] > 0 && config('app.empresa', 'Calzados Ferli'))
		{
			$mventa = Mventa::all();
			foreach ($mventa as $marca)
			{
        		$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clico_cliente,
								clico_marca,
								clico_vendedor
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$marca->id."', 
								'".$request['vendedor_id']."' "
						);

        		$apiAnita->apiCall($data);
			}
		}
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte);

		if (array_key_exists('localidad_id', $request))
			$localidad_id = $request['localidad_id'];
		else
			$localidad_id = 0;

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'valores' => " 
                clim_cliente 	                = '".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."',
                clim_nombre 	                = '".$nombre."',
                clim_contacto 	                = '".$contacto."',
                clim_direccion 	                = '".$domicilio."',
                clim_localidad 	                = '".$request['desc_localidad']."',
                clim_cod_postal 	            = '".$request['codigopostal']."',
                clim_provincia 	                = '".$request['desc_provincia']."',
                clim_telefono 	                = '".$request['telefono']."',
                clim_cuit 	                    = '".$request['nroinscripcion']."',
                clim_cond_iva 	                = '".$condicioniva."',
                clim_letra 	                    = '".$request['letra']."',
                clim_cond_venta 	            = '".($request['condicionventa_id'] > 0 ? $request['condicionventa_id'] : 0)."',
                clim_cta_contable 	            = '".$cuentacontable."',
                clim_zonavta 	                = '".($request['zonavta_id']>0?$request['zonavta_id']:0)."',
                clim_subzona 	                = '".($request['subzonavta_id']>0?$request['subzonavta_id']:0)."',
                clim_zonamult 	                = '".$request['provincia_id']."',
                clim_vendedor 	                = '".($request['vendedor_id']>0?$request['vendedor_id']:0)."',
                clim_expreso 	                = '".$codigotransporte."',
                clim_retiene_iva 	            = '".$request['retieneiva']."',
                clim_lista_precio 	            = '".($request['listaprecio_id'] > 0 ? $request['listaprecio_id'] : 0)."',
                clim_descuento 	                = '".($request['descuento'] > 0 ? $request['descuento'] : 0)."',
                clim_estado_cli 	            = '".$request['estado']."',
                clim_pais 	                    = '".$request['pais_id']."',
                clim_perc_ing_br 	            = '".$condicioniibb."',
                clim_nro_ing_br 	            = '".$request['nroiibb']."',
                clim_fantasia 	                = '".$request['fantasia']."',
                clim_fecha_alta 	            = '".$fecha."',
                clim_e_mail 	                = '".$request['email']."' "
					,
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'whereArmado' => " WHERE clil_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clil_cliente,
								clil_linea,
								clil_leyenda
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$apiAnita->apiCall($data);
		}

		// Borra comisiones
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clico_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Graba comisiones
		if ($request['vendedor_id'] > 0)
		{
			$mventa = Mventa::all();
			foreach ($mventa as $marca)
			{
        		$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
							'sistema' => 'ventas',
            				'campos' => '
								clico_cliente,
								clico_marca,
								clico_vendedor
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$marca->id."', 
								'".$request['vendedor_id']."' "
						);

        		$apiAnita->apiCall($data);
			}
		}
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'ventas',
				'whereArmado' => " WHERE clil_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'campos' => " max(clim_cliente) as $this->keyFieldAnita ",
				'whereArmado' => " WHERE clim_cliente[1,3] = 'ERP' " 
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita[0]->{$this->keyFieldAnita} != '')
		{
			$numero = filter_var($dataAnita[0]->{$this->keyFieldAnita}, FILTER_SANITIZE_NUMBER_INT);
			$numero = $numero + 1;

			$codigo = 'ERP'.str_pad($numero, 3, "0", STR_PAD_LEFT);
		}
		else
			$codigo = 'ERP001';
	}

	private function setCamposAnita($request, &$cuentacontable, &$condicioniva, &$condicioniibb, &$codigotransporte) {
       	$cuenta = Cuentacontable::select('id', 'codigo')->where('id' , $request['cuentacontable_id'])->first();
		if ($cuenta)
			$cuentacontable = $cuenta->codigo;
		else
			$cuentacontable = NULL;

       	$transporte = Transporte::select('id', 'codigo')->where('id' , $request['transporte_id'])->first();
		if ($transporte)
			$codigotransporte = $transporte->codigo;
		else
			$codigotransporte = 0;

		$condicioniva_id = 1;
		switch($request['condicioniva_id'])
		{
		case '1':
			$condicioniva = '0';
			break;
		case '3':
			$condicioniva = '3';
			break;
		case '2':
		case '5':
			$condicioniva = '4';
			break;
		case '4':
			$condicioniva = '5';
			break;
		}
		$condicioniibb = 'C';
		switch($request['condicioniibb'])
		{
		case 'E':
			$condicioniibb = '1';
			break;
		case 'C':
			$condicioniibb = '2';
			break;
		case 'L':
			$condicioniibb = '3';
			break;
		case 'N':
			$condicioniibb = 'N';
			break;
		}
	}

	public function leeCliente($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $cliente = $this->model->select('cliente.id as id',
                                        'cliente.nombre as nombre',
                                        'cliente.fantasia as fantasia',
										'cliente.nroinscripcion as nroinscripcion',
                                        'cliente.domicilio as domicilio',
										'cliente.codigo as codigo',
                                        'localidad.nombre as nombrelocalidad')
                                ->leftjoin('localidad', 'localidad.id', '=', 'cliente.localidad_id')
                                ->where('cliente.id', $busqueda)
                                ->orWhere('cliente.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('cliente.fantasia', 'like', '%'.$busqueda.'%')
								->orWhere('cliente.nroinscripcion', 'like', '%'.$busqueda.'%')
								->orWhere('cliente.domicilio', 'like', '%'.$busqueda.'%')
								->orWhere('cliente.codigo', 'like', '%'.$busqueda.'%')
								->orWhere('localidad.nombre', 'like', '%'.$busqueda.'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cliente = $cliente->paginate(10);
            else
                $cliente = $cliente->get();
        }
        else
            $cliente = $cliente->get();

        return $cliente;
    }

	public function consultaCliente($consulta)
    {
		$columns = ['cliente.id', 'cliente.nombre', 'condicioniva.nombre', 'cliente.domicilio', 'provincia.nombre', 'localidad.nombre'];
        $columnsOut = ['id', 'nombre', 'condicioniva', 'domicilio', 'provincia', 'localidad'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('cliente.id as id',
									'cliente.nombre as nombre',
                                    'condicioniva.nombre as condicioniva',
									'cliente.domicilio as domicilio',
									'provincia.nombre as provincia',
									'localidad.nombre as localidad')
                            ->leftjoin('condicioniva', 'condicioniva.id', '=', 'cliente.condicioniva_id')
							->leftjoin('provincia', 'provincia.id', '=', 'cliente.provincia_id')
							->leftjoin('localidad', 'localidad.id', '=', 'cliente.localidad_id')
							->where('deleted_at', null);

		$data = $data->Where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                            })	
                            ->get();								

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacliente">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }    

}
