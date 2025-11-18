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
use App\Models\Ventas\Abasto;
use App\Models\Ventas\Coeficiente;
use App\Models\Ventas\Cliente_Articulo_Suspendido;
use App\Models\Ventas\Cliente_Seguimiento;
use App\Models\Ventas\Distribuidor;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Mventa;
use App\Models\Configuracion\Tipodocumento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class ClienteRepository implements ClienteRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['climae', 'cliley', 'clicomi', 'movscli', 'stksuspcli'];
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
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $cliente;

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function updateEmiteNc($id)
    {
        $cliente = $this->model->findOrFail($id);

		if ($cliente->emitenotadecredito == 'No Emite Nota de Credito')
            $emite = ['emitenotadecredito' => 'Emite Nota de Credito'];
		else
			$emite = ['emitenotadecredito' => 'No Emite Nota de Credito'];

		$this->model->find($id)->update($emite);

		// Actualiza anita
		if (config('app.empresa') == "EL BIERZO")
			$cliente = self::actualizarEmiteNc($emite, $cliente->codigo);

		return $cliente;
    }

    public function delete($id)
    {
    	$cliente = Cliente::find($id);

		// Elimina anita
		if ($cliente)
			self::eliminarAnita($cliente->codigo);

        $cliente = $this->model->destroy($id);

		return $cliente;
    }

    public function find($id)
    {
        if (null == $cliente = $this->model->with("cliente_entregas")->with("cliente_seguimientos")
										->with("cliente_cm05s")
										->with("cliente_articulo_suspendidos")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")
										->with("tipossuspensioncliente")
										->with("abastos")->with("coeficientes")
										->find($id)) 
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente;
    }

	public function findPorCodigo($codigo)
    {
        if (null == $cliente = $this->model->with("cliente_entregas")->with("cliente_seguimientos")
										->with("cliente_cm05s")
										->with("cliente_articulo_suspendidos")->with("cliente_archivos")
										->with("provincias")->with("localidades")->with("paises")
										->with("tipossuspensioncliente")
										->with("abastos")->with("coeficientes")
										->where('codigo', $codigo)->first())
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente;
    }

    public function findOrFail($id)
    {
        if (null == $cliente = $this->model->with("cliente_entregas")->with("cliente_seguimientos")
											->with("cliente_cm05s")
											->with("cliente_articulo_suspendidos")->with("cliente_archivos")
											->with("provincias")->with("localidades")->with("paises")
											->with("tipossuspensioncliente")
											->with("abastos")->with("coeficientes")
											->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cliente;
    }

	public function actualizaPadronMipymePorCuit($cuit, $modo)
	{
		$this->model->where('numerodocumento', $cuit)->update(['modofacturacion' => $modo]);
	}

	public function actualizaPadronMipyme($modo)
	{
		$this->model->query()->update(['modofacturacion' => $modo]);
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
					clim_fax,
					clim_abasto,
					clim_distribuidor,
					clim_coef,
					clim_logistica,
					clim_emite_cert,
					clim_emite_nc,
					clim_coef_extra,
					clim_referencia,
					clim_cod_localidad,
					clim_cod_provincia,
					clim_agrega_bonif,
					clim_e_mail2,
					clim_dfexcl_piva,
					clim_hfexcl_piva
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

		if (config("app.empresa") == "EL BIERZO")
		{
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'ventas',
				'campos' => '
				movsc_cliente,
				movsc_orden,
				movsc_fecha,
				movsc_estado,
				movsc_observacion,
				movsc_fec_ult_tra,
				movsc_usuario,
				movsc_hora_ult_tra
				',
				'whereArmado' => " WHERE movsc_cliente = '".$key."' " 
			);
			$dataseguimientoAnita = json_decode($apiAnita->apiCall($data));

			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[4], 
				'sistema' => 'ventas',
				'campos' => '
				stksc_cliente,
				stksc_articulo
				',
				'whereArmado' => " WHERE stksc_cliente = '".$key."' " 
			);
			$dataarticulo_suspendidoAnita = json_decode($apiAnita->apiCall($data));
		}

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			if ($data->clim_cod_localidad > 0)
				$localidad = Localidad::select('id', 'nombre')->where('codigo' , '=', $data->clim_cod_localidad)->first();
			else
				$localidad = Localidad::select('id', 'nombre')->where('nombre' , '=', $data->clim_localidad)->where('codigopostal','=',$data->clim_cod_postal)->first();
			if ($localidad)
				$localidad_id = $localidad->id;
			else
				$localidad_id = NULL;

			if ($data->clim_cod_provincia > 0)
				$provincia = Provincia::select('id', 'nombre')->where('codigo' , '=', $data->clim_cod_provincia)->first();
			else
				$provincia = Provincia::select('id', 'nombre')->where('nombre' , '=', $data->clim_provincia)->first();
			if ($provincia)
				$provincia_id = $provincia->id;
			else
				$provincia_id = NULL;

        	$pais = Pais::select('id', 'nombre')->where('codigo' , $data->clim_pais)->first();
			if ($pais)
				$pais_id = $pais->id;
			else
				$pais_id = 1;
	
        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->clim_cta_contable)->first();
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;
	
        	$zonavta = Zonavta::select('id')->where('codigo' , $data->clim_zonavta)->first();
			if ($zonavta)
				$zonavta_id = $zonavta->id;
			else
				$zonavta_id = NULL;
	
        	$subzonavta = Subzonavta::select('id')->where('id' , $data->clim_subzona)->first();
			if ($subzonavta)
				$subzonavta_id = $subzonavta->id;
			else
				$subzonavta_id = NULL;
	
        	$vendedor = Vendedor::select('id')->where('codigo' , $data->clim_vendedor)->first();
			if ($vendedor)
				$vendedor_id = $vendedor->id;
			else
				$vendedor_id = NULL;
	
        	$condicionventa = Condicionventa::select('id')->where('id' , $data->clim_cond_venta)->first();
			if ($condicionventa)
				$condicionventa_id = $condicionventa->id;
			else
				$condicionventa_id = NULL;
	
        	$listaprecio = Listaprecio::select('id')->where('codigo' , $data->clim_lista_precio)->first();
			if ($listaprecio)
				$listaprecio_id = $listaprecio->id;
			else
				$listaprecio_id = NULL;

       		$transporte = Transporte::select('id', 'codigo')->where('codigo' , $data->clim_expreso)->first();
			if ($transporte)
				$transporte_id = $transporte->id;
			else
				$transporte_id = NULL;

        	$abasto = Abasto::select('id')->where('codigo' , $data->clim_abasto)->first();
			if ($abasto)
				$abasto_id = $abasto->id;
			else
				$abasto_id = NULL;
				
        	$coeficiente = Coeficiente::select('id')->where('codigo' , $data->clim_coef)->first();
			if ($coeficiente)
				$coeficiente_id = $coeficiente->id;
			else
				$coeficiente_id = NULL;
							
        	$distribuidor = Distribuidor::select('id')->where('codigo' , $data->clim_distribuidor)->first();
			if ($distribuidor)
				$distribuidor_id = $distribuidor->id;
			else
				$distribuidor_id = NULL;
							
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
			case '6':
				$condicioniva_id = 6;
				break;
			case '8':
				$condicioniva_id = 7;
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

			if ($data->clim_emite_cert == 'S')
				$emiteCertificado = "Emite Certificado";
			else	
				$emiteCertificado = "No Emite Certificado";

			if ($data->clim_emite_nc == 'S')
				$emiteNotaDeCredito = "Emite Nota de Credito";
			else	
				$emiteNotaDeCredito = "No Emite Nota de Credito";

			if ($data->clim_agrega_bonif == 'S')
				$agregaBonificacion = "Agrega Bonificacion";
			else
				$agregaBonificacion = "No Agrega Bonificacion";

			if ($data->clim_regimen == '1')
				$modoFacturacion = 'C';
			else
				$modoFacturacion = 'N';

			$email = rtrim($data->clim_e_mail, ' ').rtrim($data->clim_e_mail2);

			$arr_campos = [
				"nombre" => $data->clim_nombre,
				"codigo" => ltrim($data->clim_cliente, '0'),
            	"contacto" => $data->clim_contacto,
            	"fantasia" => $data->clim_fantasia,
				"email" => $email,
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
				"numerodocumento" => $data->clim_cuit,
				"condicioniva_id" => $condicioniva_id,
				"retieneiva" => $data->clim_retiene_iva,
				"nroiibb" => $data->clim_nro_ing_bruto,
				"condicioniibb" => $condicioniibb,
				"condicionventa_id" => $condicionventa_id,
				"listaprecio_id" => $listaprecio_id,
				"descuento" => $data->clim_descuento,
				"cuentacontable_id" => $cuentacontable_id,
				"vaweb" => 'N',
				"estado" => $data->clim_estado_cli,
				"leyenda" => $leyenda,
				"modofacturacion" => $modoFacturacion,
				"usuario_id" => $usuario_id,
				'abasto_id' => $abasto_id, 
				'coeficiente_id' => $coeficiente_id, 
				'porcentajelogistica' => $data->clim_logistica, 
				'emitecertificado' => $emiteCertificado, 
				'emitenotadecredito' => $emiteNotaDeCredito,
                'coeficienteextra' => $data->clim_coef_extra,
				'agregabonificacion' => $agregaBonificacion,
				'desdefecha_exclusionpercepcioniva' => $data->clim_dfexcl_piva,
                'hastafecha_exclusionpercepcioniva' => $data->clim_hfexcl_piva,
				'distribuidor_id' => $distribuidor_id,
				'descuentoventa_id' => null,
				'tipodocumento_id' => 1,
				'lugarentrega' => $data->clim_lugar_entrega
            	];
	
			if ($fl_crea_registro)
            	$cliente = Cliente::create($arr_campos);
			else
            	$cliente = Cliente::where('codigo', ltrim($data->clim_cliente, '0'))->update($arr_campos);
        }

		if (count($dataseguimientoAnita) > 0) 
		{
			foreach($dataseguimientoAnita as $data)
			{
				Cliente_Seguimiento::create(['cliente_id' => $cliente->id,
											'fecha' => $data->movsc_fecha,
											'observacion' => $data->movsc_observacion,
											'leyenda' => '',
											'creousuario_id' => 1]);
			}
		}

		if (count($dataarticulo_suspendidoAnita) > 0) 
		{
			foreach($dataarticulo_suspendidoAnita as $data)
			{
				// Lee el producto
				$articulo = Articulo::select('sku', 'id')->where('sku', ltrim($data->stksc_articulo,'0'))->first();

				if ($articulo)
					Cliente_Articulo_Suspendido::create(['cliente_id' => $cliente->id,
													'articulo_id' => $articulo->id,
													'creousuario_id' => 1]);
			}
		}
    }

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte,
								$codigolocalidad, $codigoprovincia, $codigopais, $codigozonavta, $codigovendedor,
								$codigolistaprecio, $codigoabasto, $codigocoeficiente,
								$emitecertificado, $emitenotadecredito, $agregabonificacion, $regimen);

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		if (config("app.empresa") == "EL BIERZO")
		{
			$desdefecha_exclusionpercepcioniva = $request['desdefecha_exclusionpercepcioniva'];
			if ($desdefecha_exclusionpercepcioniva)
				$dfexcl_piva = $desdefecha_exclusionpercepcioniva->format('Ymd');
			else
				$dfexcl_piva = '0000-00-00';

			$hastafecha_exclusionpercepcioniva = $request['hastafecha_exclusionpercepcioniva'];
			if ($hastafecha_exclusionpercepcioniva)
				$hfexcl_piva = $hastafecha_exclusionpercepcioniva->format('Ymd');
			else
				$hfexcl_piva = '0000-00-00';
		}

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

		$tipodocumento = Tipodocumento::find($request['tipodocumento_id']);

		$documento = $request['numerodocumento'];
		if ($tipodocumento)
		{
			if ($tipodocumento->codigoexterno != "80")
				$documento = $tipodocumento->abreviatura.' '.$request['numerodocumento'];
		}

        $data = array( 'tabla' => $this->tableAnita[0], 'acc' => 'insert',
			'sistema' => 'ventas',
            'campos' => ' 
					clim_cliente, clim_nombre, clim_contacto, clim_direccion, clim_localidad, clim_cod_postal, clim_provincia, clim_telefono,
					clim_cuit, clim_cond_iva, clim_porc_excen, clim_letra, clim_cond_venta, clim_cta_contable, clim_credito, clim_dias_atraso,
					clim_zonavta, clim_subzona, clim_zonamult, clim_vendedor, clim_cobrador, clim_expreso, clim_tipo_empresa, clim_dir_cobranza,
					clim_hs_cobranza, clim_lugar_entrega, clim_retiene_iva, clim_lista_precio, clim_descuento, clim_nro_interno, clim_fecha_interes,
					clim_proveedor, clim_minimo_fact, clim_estado_cli, clim_dias_cobranza, clim_dias_atencion, clim_hs_atencion,clim_pais,
					clim_perc_ing_br, clim_nro_ing_br, clim_dir_postal, clim_loc_postal, clim_cp_postal, clim_fantasia, clim_fecha_alta,
					clim_ley_liberado, clim_regimen, clim_leyenda_fact, clim_prov_postal, clim_lugar_de_pago, clim_excl_perc_iva, clim_fe_excl_piva,
					clim_dto_integrado, clim_fecha_boletin, clim_e_mail, clim_fax'.(config("app.empresa") == 'EL BIERZO' ?
					',clim_abasto, clim_distribuidor, clim_coef, clim_logistica, clim_emite_cert, clim_emite_nc, clim_coef_extra,clim_referencia,
					clim_cod_localidad, clim_cod_provincia, clim_agrega_bonif, clim_e_mail2, clim_dfexcl_piva, clim_hfexcl_piva' : ''),
            'valores' => " 
				'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
				'".$nombre."',
				'".$contacto."',
				'".$domicilio."',
				'".$request['desc_localidad']."',
				'".$request['codigopostal']."',
				'".$request['desc_provincia']."',
				'".$request['telefono']."',
				'".$documento."',
				'".$condicioniva."',
				'0',
				'".$request['letra']."',
				'".($request['condicionventa_id']>0?$request['condicionventa_id']:0)."',
				'".$cuentacontable."',
				'0',
				'0',
				'".$codigozonavta."',
				'".($request['subzonavta_id']>0?$request['subzonavta_id']:0)."',
				'".$codigoprovincia."',
				'".$codigovendedor."',
				'".$codigovendedor."',
				'".$codigotransporte."',
				'0',
				' ',
				' ',
				'".$request['lugarentrega']."',
				'".$request['retieneiva']."',
				'".$codigolistaprecio."',
				'".($request['descuento'] > 0 ? $request['descuento'] : 0)."',
				'0',
				'0',
				' ',
				'0',
				'".$request['estado']."',
				' ',
				' ',
				' ',
				'".$codigopais."',
				'".$condicioniibb."',
				'".$request['nroiibb']."',
				' ',
				' ',
				' ',
				'".$request['fantasia']."',
				'".$fecha."',
				' ',
				'".$regimen."',
				'0',
				'".$request['desc_provincia']."',
				' ',
				' ',
				'0',
				' ',
				'0',
                '".substr($request['email'],0,40)."',
				'".'FAX'."'".
					(config('app.empresa') == "EL BIERZO" ? ",
					'".$codigoabasto."',
					'".'0'."',
					'".$codigocoeficiente."',
					'".$request['porcentajelogistica']."',
					'".$emitecertificado."',
					'".$emitenotadecredito."',
					'".$request['coeficienteextra']."',
					'".'0'."',
					'".$codigolocalidad."',
					'".$codigoprovincia."',
					'".$agregabonificacion."',
					'".substr($request['email'],40,40)."',
					'".$dfexcl_piva."',
					'".$hfexcl_piva."' " : "")
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

		// Graba articulos suspendidos
		if (isset($request['articulo_suspendido_ids']))
		{
			foreach($request['articulo_suspendido_ids'] as $articulo)
			{
				$articulo = Articulo::find($articulo);

				if ($articulo)
				{
					$data = array( 'tabla' => $this->tableAnita[4], 'acc' => 'insert',
						'sistema' => 'ventas',
						'campos' => '
							stksc_cliente,
							stksc_articulo
									',
						'valores' => " 
							'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
							'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' "
					);

        			$apiAnita->apiCall($data);
				}
			}
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

		if (config("app.empresa") == "EL BIERZO")
		{
			$desdefecha_exclusionpercepcioniva = $request['desdefecha_exclusionpercepcioniva'];
			if ($desdefecha_exclusionpercepcioniva)
				$dfexcl_piva = $desdefecha_exclusionpercepcioniva->format('Ymd');
			else
				$dfexcl_piva = '0000-00-00';

			$hastafecha_exclusionpercepcioniva = $request['hastafecha_exclusionpercepcioniva'];
			if ($hastafecha_exclusionpercepcioniva)
				$hfexcl_piva = $hastafecha_exclusionpercepcioniva->format('Ymd');
			else
				$hfexcl_piva = '0000-00-00';
		}

		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $condicioniibb, $codigotransporte,
								$codigolocalidad, $codigoprovincia, $codigopais, $codigozonavta, $codigovendedor,
								$codigolistaprecio, $codigoabasto, $codigocoeficiente,
								$emitecertificado, $emitenotadecredito, $agregabonificacion, $regimen);

		if (array_key_exists('localidad_id', $request))
			$localidad_id = $request['localidad_id'];
		else
			$localidad_id = 0;

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

		$tipodocumento = Tipodocumento::find($request['tipodocumento_id']);

		$documento = $request['numerodocumento'];
		if ($tipodocumento)
		{
			if ($tipodocumento->codigoexterno != "80")
				$documento = $tipodocumento->abreviatura.' '.$request['numerodocumento'];
		}		
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
                clim_cuit 	                    = '".$documento."',
                clim_cond_iva 	                = '".$condicioniva."',
                clim_letra 	                    = '".$request['letra']."',
                clim_cond_venta 	            = '".($request['condicionventa_id'] > 0 ? $request['condicionventa_id'] : 0)."',
                clim_cta_contable 	            = '".$cuentacontable."',
                clim_zonavta 	                = '".$codigozonavta."',
                clim_subzona 	                = '".($request['subzonavta_id']>0?$request['subzonavta_id']:0)."',
                clim_zonamult 	                = '".$codigoprovincia."',
                clim_vendedor 	                = '".$codigovendedor."',
                clim_expreso 	                = '".$codigotransporte."',
				clim_lugar_entrega              = '".$request['lugarentrega']."',
                clim_retiene_iva 	            = '".$request['retieneiva']."',
                clim_lista_precio 	            = '".$codigolistaprecio."',
                clim_descuento 	                = '".($request['descuento'] > 0 ? $request['descuento'] : 0)."',
                clim_estado_cli 	            = '".$request['estado']."',
                clim_pais 	                    = '".$codigopais."',
                clim_perc_ing_br 	            = '".$condicioniibb."',
                clim_nro_ing_br 	            = '".$request['nroiibb']."',
                clim_fantasia 	                = '".$request['fantasia']."',
                clim_fecha_alta 	            = '".$fecha."',
                clim_e_mail 	                = '".substr($request['email'],0,40)."'".
				(config("app.empresa") == "EL BIERZO" ? ",
				clim_abasto                     = '".$codigoabasto."',
				clim_distribuidor				= '".'0'."',
				clim_coef                       = '".$codigocoeficiente."',
				clim_logistica                  = '".$request['porcentajelogistica']."',
				clim_emite_cert                 = '".$emitecertificado."',
				clim_emite_nc                   = '".$emitenotadecredito."',
				clim_coef_extra                 = '".$request['coeficienteextra']."',
				clim_referencia                 = '".'0'."',
				clim_cod_localidad              = '".$codigolocalidad."',
				clim_cod_provincia              = '".$codigoprovincia."',
				clim_agrega_bonif               = '".$agregabonificacion."',
				clim_e_mail2                    = '".substr($request['email'],40,40)."',
				clim_dfexcl_piva                = .".$dfexcl_piva."',
				clim_hfexcl_piva                = .".$hfexcl_piva."' " : ""),
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $climae = $apiAnita->apiCall($data);

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
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$apiAnita->apiCall($data);
		}

		// Borra articulos suspendidos
		if (config("app.empresa") == "EL BIERZO")
		{
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[4], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE stksc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$apiAnita->apiCall($data);

			// Graba articulos suspendidos
			if (isset($request['articulo_suspendido_ids']))
			{
				foreach($request['articulo_suspendido_ids'] as $articulo)
				{
					$articulo = Articulo::find($articulo);

					if ($articulo)
					{
						$data = array( 'tabla' => $this->tableAnita[4], 'acc' => 'insert',
							'sistema' => 'ventas',
							'campos' => '
								stksc_cliente,
								stksc_articulo
										',
							'valores' => " 
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
								'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' "
						);

						$apiAnita->apiCall($data);
					}
				}		
			}
		}

		// Borra comisiones
		if ($request['vendedor_id'] > 0 && config('app.empresa', 'Calzados Ferli'))
		{
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE clico_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$apiAnita->apiCall($data);

			// Graba comisiones
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
								'".str_pad($id, 6, "0", STR_PAD_LEFT)."', 
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

		if (config("app.empresa") == "EL BIERZO")
		{
			// Borra articulos suspendidos
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[4], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE stksc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$apiAnita->apiCall($data);

			// Borra seguimiento de clientes
			$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[3], 
					'sistema' => 'ventas',
					'whereArmado' => " WHERE movsc_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
			$apiAnita->apiCall($data);
		}
	}

	private function actualizarEmiteNc($emite, $id) 
	{
        $apiAnita = new ApiAnita();

		if ($emite['emitenotadecredito'] == 'Emite Nota de Credito')
			$emitenotadecredito = 'S';
		else
			$emitenotadecredito = 'N';
				
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'ventas',
				'valores' => " 
				clim_emite_nc = '".$emitenotadecredito."' ",
				'whereArmado' => " WHERE clim_cliente = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $anita = $apiAnita->apiCall($data);

		return $anita;
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

	private function setCamposAnita($request, &$cuentacontable, &$condicioniva, &$condicioniibb, &$codigotransporte,
									&$codigolocalidad, &$codigoprovincia, &$codigopais, &$codigozonavta, &$codigovendedor,
									&$codigolistaprecio, &$codigoabasto, &$codigocoeficiente,
									&$emitecertificado, &$emitenotadecredito, &$agregabonificacion, &$regimen)
	{
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

		if (config("app.empresa") == "EL BIERZO")
		{
			if ($request['emitecertificado'] == 'Emite Certificado')
				$emitecertificado = 'S';
			else
				$emitecertificado = 'N';

			if ($request['emitenotadecredito'] == 'Emite Nota de Credito')
				$emitenotadecredito = 'S';
			else
				$emitenotadecredito = 'N';

			if ($request['agregabonificacion'] == 'Agrega Bonificacion')		
				$agregabonificacion = 'S';
			else
				$agregabonificacion = 'N';

			if ($request['modofacturacion'] == 'N')
				$regimen = '0';
			else
				$regimen = '1';
		}
		$localidad = Localidad::select('id', 'codigo')->where('id' , $request['cuentacontable_id'])->first();
		if ($localidad)
			$codigolocalidad = $localidad->codigo;
		else
			$codigolocalidad = 0;

		$provincia = Provincia::select('id', 'codigo')->where('id' , $request['provincia_id'])->first();
		if ($provincia)
			$codigoprovincia = $provincia->codigo;
		else
			$codigoprovincia = 0;

		$pais = Pais::select('id', 'codigo')->where('id' , $request['pais_id'])->first();
		if ($pais)
			$codigopais = $pais->codigo;
		else
			$codigopais = 1;
	
		$zonavta = Zonavta::select('id', 'codigo')->where('id' , $request['zonavta_id'])->first();
		if ($zonavta)
			$codigozonavta = $zonavta->codigo;
		else
			$codigozonavta = 0;

		$vendedor = Vendedor::select('id', 'codigo')->where('id' , $request['vendedor_id'])->first();
		if ($vendedor)
			$codigovendedor = $vendedor->codigo;
		else
			$codigovendedor = 0;
	
		$listaprecio = Listaprecio::select('id', 'codigo')->where('id' , $request['listaprecio_id'])->first();
		if ($listaprecio)
			$codigolistaprecio = $listaprecio->codigo;
		else
			$codigolistaprecio = 0;

		if (config("app.empresa") == "EL BIERZO")
		{
			$abasto = Abasto::select('id', 'codigo')->where('id' , $request['abasto_id'])->first();
			if ($abasto)
				$codigoabasto = $abasto->codigo;
			else
				$codigoabasto = 0;
				
			$coeficiente = Coeficiente::select('id', 'codigo')->where('id' , $request['coeficiente_id'])->first();
			if ($coeficiente)
				$codigocoeficiente = $coeficiente->codigo;
			else
				$codigocoeficiente = 0;
		}
	}

	public function leeCliente($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $cliente = $this->model->select('cliente.id as id',
                                        'cliente.nombre as nombre',
										'transporte.codigo as ctransporte',
										'transporte.nombre as nombretransporte',
										'cliente.numerodocumento as numerodocumento',
                                        'cliente.domicilio as domicilio',
										'cliente.codigo as codigo',
                                        'localidad.nombre as nombrelocalidad',
										'provincia.nombre as nombreprovincia')
                                ->leftjoin('localidad', 'localidad.id', 'cliente.localidad_id')
								->leftjoin('provincia', 'provincia.id', 'cliente.provincia_id')
								->leftjoin('transporte', 'transporte.id', 'cliente.transporte_id')
                                ->where('cliente.id', $busqueda)
                                ->orWhere('cliente.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('transporte.codigo', 'like', '%'.$busqueda.'%')
								->orWhere('transporte.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('cliente.numerodocumento', 'like', '%'.$busqueda.'%')
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
		$columns = ['cliente.id', 'cliente.nombre', 'cliente.codigo', 'cliente.domicilio', 'provincia.nombre', 'localidad.nombre'];
        $columnsOut = ['id', 'nombre', 'codigo', 'domicilio', 'provincia', 'localidad'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('cliente.id as id',
									'cliente.nombre as nombre',
                                    'cliente.codigo as codigo',
									'cliente.domicilio as domicilio',
									'provincia.nombre as provincia',
									'localidad.nombre as localidad')
							->leftjoin('provincia', 'provincia.id', '=', 'cliente.provincia_id')
							->leftjoin('localidad', 'localidad.id', '=', 'cliente.localidad_id')
							->where('deleted_at', null)
							->where('cliente.estado', '0');

		$data = $data->Where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                            })	
							->orderBy('cliente.nombre', 'asc')
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
