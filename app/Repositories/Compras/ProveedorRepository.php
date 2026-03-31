<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Pais;
use App\Repositories\Compras\Proveedor_ExclusionRepositoryInterface;
use App\Repositories\Compras\Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Proveedor_FormapagoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Caja\TipocuentacajaRepositoryInterface;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class ProveedorRepository implements ProveedorRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['promae', 'proley', 'proexcl', 'propago'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'prom_proveedor';

	private $tipoempresaRepository;
    private $retenciongananciaRepository;
    private $retencionsussRepository;
    private $retencionivaRepository;
    private $condicionpagoRepository;
    private $condicioncompraRepository;
    private $condicionentregaRepository;
    private $condicionIIBBRepository;
	private $centrocostoRepository;
	private $conceptogastoRepository;
	private $proveedor_formapagoRepository;
	private $proveedor_exclusionRepository;
	private $formapagoRepository;
	private $tipocuentacajaRepository;
	private $bancoRepository;
	private $mediopagoRepository;
	private $cuentacontableRepository;
	private $monedaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor $proveedor,
								TipoempresaRepositoryInterface $tipoempresarepository,
								RetenciongananciaRepositoryInterface $retenciongananciarepository,
								RetencionivaRepositoryInterface $retencionivarepository,
								RetencionsussRepositoryInterface $retencionsussrepository,
								CondicionpagoRepositoryInterface $condicionpagorepository,
								CondicioncompraRepositoryInterface $condicioncomprarepository,
								CondicionentregaRepositoryInterface $condicionentregarepository,
								CondicionIIBBRepositoryInterface $condicionIIBBrepository,
								CentrocostoRepositoryInterface $centrocostorepository,
								ConceptogastoRepositoryInterface $conceptogastorepository,
								Proveedor_FormaPagoRepositoryInterface $proveedor_formapagorepository,
								Proveedor_ExclusionRepositoryInterface $proveedor_exclusionrepository,
								FormapagoRepositoryInterface $formapagorepository,
								TipocuentacajaRepositoryInterface $tipocuentacajarepository,
								BancoRepositoryInterface $bancorepository,
								MediopagoRepositoryInterface $mediopagorepository,
								CuentacontableRepositoryInterface $cuentacontablerepository,
								MonedaRepositoryInterface $monedarepository
								)
    {
        $this->model = $proveedor;
		$this->tipoempresaRepository = $tipoempresarepository;
        $this->retenciongananciaRepository = $retenciongananciarepository;
        $this->retencionivaRepository = $retencionivarepository;
        $this->retencionsussRepository = $retencionsussrepository;
        $this->condicionpagoRepository = $condicionpagorepository;
        $this->condicioncompraRepository = $condicioncomprarepository;
        $this->condicionentregaRepository = $condicionentregarepository;
        $this->condicionIIBBRepository = $condicionIIBBrepository;
		$this->centrocostoRepository = $centrocostorepository;
		$this->conceptogastoRepository = $conceptogastorepository;
		$this->proveedor_formapagoRepository = $proveedor_formapagorepository;
		$this->proveedor_exclusionRepository= $proveedor_exclusionrepository;
		$this->formapagoRepository = $formapagorepository;
		$this->bancoRepository = $bancorepository;
		$this->mediopagoRepository = $mediopagorepository;
		$this->tipocuentacajaRepository = $tipocuentacajarepository;
		$this->cuentacontableRepository = $cuentacontablerepository;
		$this->monedaRepository = $monedarepository;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;
		$data['estado'] = '0';

		if ($data['retieneiva'] == null)
			$data['retieneiva'] = 'N';

		if ($data['retieneganancia'] == null)
			$data['retieneganancia'] = 'N';

		if ($data['retienesuss'] == null)
			$data['retienesuss'] = 'N';
        $proveedor = $this->model->create($data);

		// Graba anita
		self::guardarAnita($data);

		return $proveedor;
    }

    public function update(array $data, $id)
    {
        $proveedor = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $proveedor;
    }

    public function delete($id)
    {
    	$proveedor = Proveedor::find($id);

		// Elimina anita
		if ($proveedor)
			self::eliminarAnita($proveedor->codigo);

        $proveedor = $this->model->destroy($id);

		return $proveedor;
    }

    public function find($id)
    {
        return $this->model->with("proveedor_exclusiones")
									->with("proveedor_archivos")
									->with("proveedor_formapagos")
									->with("tipossuspensionproveedores")
									->with("tipoempresas")
									->with("condicionIIBBs")
									->with("condicionivas")
									->with("condicionpagos")
									->with("cuentascontables")
									->with("cuentascontablesme")
									->with("cuentascontablescompra")
									->with("retencionganancias")
									->with("retencionivas")
									->with("retencionsusss")
									->with("conceptogastos")
									->with("centrocostocompras")
									->find($id);
    }

    public function findPorDocumento($numerodocumento)
    {
        return $this->model->with("proveedor_exclusiones")
									->with("proveedor_archivos")
									->with("proveedor_formapagos")
									->with("tipossuspensionproveedores")
									->with("tipoempresas")
									->with("condicionIIBBs")
									->with("condicionivas")
									->with("condicionpagos")
									->with("cuentascontables")
									->with("cuentascontablesme")
									->with("cuentascontablescompra")
									->with("retencionganancias")
									->with("retencionivas")
									->with("retencionsusss")
									->with("conceptogastos")
									->with("centrocostocompras")
									->where('nroinscripcion', $numerodocumento)->get();
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with("proveedor_exclusiones")
									->with("proveedor_archivos")
									->with("proveedor_formapagos")
									->with("tipossuspensionproveedores")
									->with("tipoempresas")
									->with("condicionIIBBs")
									->with("condicionivas")
									->with("condicionpagos")
									->with("cuentascontables")
									->with("cuentascontablesme")
									->with("cuentascontablescompra")
									->with("retencionganancias")
									->with("retencionivas")
									->with("retencionsusss")
									->with("conceptogastos")
									->with("centrocostocompras")
									->where('codigo',$codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $proveedor = $this->model->with("proveedor_exclusiones")
											->with("proveedor_archivos")
											->with("proveedor_formapagos")
											->with("tipossuspensionproveedores")
											->with("tipoempresas")
											->with("condicionIIBBs")
											->with("condicionivas")
											->with("condicionpagos")
											->with("cuentascontables")
											->with("cuentascontablesme")
											->with("cuentascontablescompra")
											->with("retencionganancias")
											->with("retencionivas")
											->with("retencionsusss")
											->with("conceptogastos")
											->with("centrocostocompras")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $proveedor;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Proveedor::all();
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
			'sistema' => 'compras',
            'campos' => '
				prom_proveedor ,
				prom_nombre,
				prom_contacto,
				prom_direccion,
				prom_localidad,
				prom_cod_postal,
				prom_provincia,
				prom_telefono,
				prom_cuit,
				prom_cond_iva,
				prom_letra,
				prom_cond_pago,
				prom_cta_contable,
				prom_credito,
				prom_dias_atraso,
				prom_nro_interno,
				prom_agente_ret,
				prom_cond_gan,
				prom_incl_impuesto,
				prom_cond_compra,
				prom_cond_entrega,
				prom_tipo_empresa,
				prom_prov_vario,
				prom_retiene_iva,
				prom_cod_retgan,
				prom_cod_retiva,
				prom_a_nombre_de,
				prom_ret_suss,
				prom_ret_ibr,
				prom_nro_ret_ibr,
				prom_nro_reemp_ib,
				prom_excl_retiva,
				prom_pais,
				prom_fecha_alta,
				prom_estado_pro,
				prom_fantasia,
				prom_regimen,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_cod_localidad,
				prom_tipo_emp_alfa,
				prom_e_mail,
				prom_fax,
				prom_fecha_boletin,
				prom_cod_ret_suss,
				prom_cta_cont_me,
				prom_cta_default,
				prom_cc_default,
				prom_concepto,
				prom_descuento,
				prom_fecha_exclib,
				prom_excl_retib,
				prom_fe_ini_excl,
				prom_fe_ini_exclrg,
				prom_fe_ini_exclib,
				prom_ag_perc_ib,
				prom_ag_perc_iva
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
			'sistema' => 'compras',
            'campos' => '
			prol_proveedor,
    		prol_leyenda
			',
            'whereArmado' => " WHERE prol_proveedor = '".$key."' " 
        );
        $dataleyAnita = json_decode($apiAnita->apiCall($data));

		$data = array( 
            'acc' => 'list', 'tabla' => 'promadic', 
			'sistema' => 'compras',
            'campos' => '
			proad_proveedor,
    		proad_e_mail_oc,
			proad_semaforo
			',
            'whereArmado' => " WHERE proad_proveedor = '".$key."' " 
        );
        $dataAdicionalAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (isset($dataAnita)) {
            $data = $dataAnita[0];

			$localidad_id = NULL;
			$provincia_id = NULL;
	
			$localidad = Localidad::select('id', 'nombre', 'provincia_id')
									->where('nombre' , $data->prom_localidad)
									->orwhere('codigo',$data->prom_cod_localidad)->first();
			if ($localidad)
			{
				$localidad_id = $localidad->id;
				$provincia_id = $localidad->provincia_id;
			}
	
        	$pais = Pais::select('id', 'nombre')->where('id' , $data->prom_pais)->first();
			if ($pais)
				$pais_id = $pais->id;
			else
				$pais_id = 1;
	
			$tipoempresa = $this->tipoempresaRepository->findPorCodigo($data->prom_tipo_empresa);
			if ($tipoempresa)
				$tipoempresa_id = $tipoempresa->id;
			else
				$tipoempresa_id = 1;
						
			$cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->prom_cta_contable);
			if ($cuenta)
				$cuentacontable_id = $cuenta->id;
			else
				$cuentacontable_id = NULL;

			$cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->prom_cta_cont_me);				
			if ($cuenta)
				$cuentacontableme_id = $cuenta->id;
			else
				$cuentacontableme_id = NULL;
			
			$cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->prom_cta_default);
			if ($cuenta)
				$cuentacontablecompra_id = $cuenta->id;
			else
				$cuentacontablecompra_id = NULL;
				
			$centrocosto = $this->centrocostoRepository->findPorCodigo($data->prom_cc_default);
			if ($centrocosto)
				$centrocostocompra_id = $centrocosto->id;
			else
				$centrocostocompra_id = 1;
				
			$condicioniva_id = 1;
			switch($data->prom_cond_iva)
			{
			case '1': // Inscripto
				$condicioniva_id = 1;
				break;
			case '2': // No inscripto
				$condicioniva_id = 7;
				break;
			case '3': // Exento
				$condicioniva_id = 2;
				break;
			case '4': // Monotributo
				$condicioniva_id = 4;
				break;
			}

			$condicionganancia = 'I';
			switch($data->prom_cond_gan)
			{
			case '1':
				$condicionganancia = 'I';
				break;
			case '2':
				$condicionganancia = 'N';
				break;
			case '3':
				$condicionganancia = 'C';
				break;	
			}
        	
			$retencioniva = $this->retencionivaRepository->findPorCodigo($data->prom_cod_retiva);
			if ($retencioniva)
				$retencioniva_id = $retencioniva->id;
			else
				$retencioniva_id = null;

			$retencionganancia = $this->retenciongananciaRepository->findPorCodigo($data->prom_cod_retgan);
			if ($retencionganancia)
				$retencionganancia_id = $retencionganancia->id;
			else
				$retencionganancia_id = null;
	
			$retencionsuss = $this->retencionsussRepository->findPorCodigo($data->prom_cod_ret_suss);
			if ($retencionsuss)
				$retencionsuss_id = $retencionsuss->id;
			else
				$retencionsuss_id = null;
	
			$condicioniibb_id = 1;
			switch($data->prom_ret_ibr)
			{
			case 'S':
			case 'C':
				$condicioniibb_id = 1;
				break;
			case 'L':
			case 'B':
				$condicioniibb_id = 2;
				break;
			case 'I':
			case 'E':
			case 'N':
				$condicioniibb_id = 3;
				break;
			}

			$condpago = $this->condicionpagoRepository->findPorCodigo($data->prom_cond_pago);
			if ($condpago)
				$condicionpago_id = $condpago->id;
			else
				$condicionpago_id = null;

			$condentrega = $this->condicionentregaRepository->findPorCodigo($data->prom_cond_entrega);
			if ($condentrega)
				$condicionentrega_id = $condentrega->id;
			else
				$condicionentrega_id = null;
			
			$condcompra = $this->condicioncompraRepository->findPorCodigo($data->prom_cond_compra);
			if ($condcompra)
				$condicioncompra_id = $condcompra->id;
			else
				$condicioncompra_id = null;

			if ($data->prom_concepto == 0)
				$concepto_id = 66;
			else
				$concepto_id = $data->prom_concepto;

			$concepto = $this->conceptogastoRepository->findPorId($concepto_id);

			if ($concepto)
				$conceptogasto_id = $concepto->id;
			else
				$conceptogasto_id = null;
			
			$estado = 'Activo';
			switch($data->prom_estado_pro)
			{
				case '0':
					$estado = 'Activo';
					break;
				case '1':
					$estado = 'Suspendido';
					break;
				case '2':
					$estado = 'Alta Pendiente';
					break;
				case '3':
					$estado = 'Regularizado';
					break;
			}

			$semaforo = null;
			if (isset($dataAdicionalAnita[0]->proad_semaforo))
			switch($dataAdicionalAnita[0]->proad_semaforo)
			{
				case 'V':
					$semaforo = 'Verde';
					break;
				case 'A':
					$semaforo = 'Amarillo';
					break;
				case 'R':
					$semaforo = 'Rojo';
					break;
			}

			$emailOc = null;
			if (isset($dataAdicionalAnita[0]->proad_e_mail_oc))
				$emailOc = $dataAdicionalAnita[0]->proad_e_mail_oc;

			$regimenFacturacion = 'RG 3419';
			switch($data->prom_regimen)
			{
			case '1':
				$regimenFacturacion = 'FCE';
				break;
			}

			$tipoServicio_id = 1;
			switch($data->prom_prov_vario)
			{
				case 'S':
					$tipoServicio_id = 2; // Servicios
					break;
				case 'V':
					$tipoServicio_id = 3; // Eventual
			}

			// Lee las leyendas
			$leyenda = "";
			foreach ($dataleyAnita as $ley)
				$leyenda .= $ley->prol_leyenda;

			$arr_campos = [
				"nombre" => $data->prom_nombre,
				"codigo" => ltrim($data->prom_proveedor, '0'),
            	"contacto" => $data->prom_contacto,
            	"fantasia" => $data->prom_fantasia,
				"email" => $data->prom_e_mail,
				"telefono" => $data->prom_telefono.' '.$data->prom_fax,
				"urlweb" => ' ',
				"domicilio" => $data->prom_direccion,
				"localidad_id" => $localidad_id,
				"provincia_id" => $provincia_id,
				"pais_id" => $pais_id,
				"codigopostal" => $data->prom_cod_postal,
				"tipoempresa_id" => $tipoempresa_id,
				"nroinscripcion" => $data->prom_cuit,
				"condicioniva_id" => $condicioniva_id,
				"agentepercepcioniva" => $data->prom_ag_perc_iva,
				"retieneiva" => $data->prom_retiene_iva,
				"retencioniva_id" => $retencioniva_id,
				"retieneganancia" => ($data->prom_agente_ret == 'N' ? 'S' : 'N'),
				"condicionganancia" => $condicionganancia,
				"retencionganancia_id" => $retencionganancia_id,
				"retienesuss" => $data->prom_ret_suss,
				"retencionsuss_id" => $retencionsuss_id,
				"condicionIIBB_id" => $condicioniibb_id,
				"agentepercepcionIIBB" => $data->prom_ag_perc_ib,
				"nroIIBB" => $data->prom_nro_ret_ibr,
				"condicionpago_id" => $condicionpago_id,
				"condicionentrega_id" => $condicionentrega_id,
				"condicioncompra_id" => $condicioncompra_id,
				"cuentacontable_id" => $cuentacontable_id,
				"cuentacontableme_id" => $cuentacontableme_id,
				"cuentacontablecompra_id" => $cuentacontablecompra_id,
				"centrocostocompra_id" => $centrocostocompra_id,
				"conceptogasto_id" => $conceptogasto_id,
				"estado" => $estado,
				"leyenda" => $leyenda,
				"semaforo" => $semaforo,
				"emailoc" => $emailOc,
				"regimenfacturacion" => $regimenFacturacion,
				"tiposervicio_proveedor_id" => $tipoServicio_id,
				"usuario_id" => $usuario_id,
            	];
	
			if ($fl_crea_registro)
            	$proveedor = $this->model->create($arr_campos);
			else
            	$proveedor = $this->model->findOrFail(ltrim($data->prom_proveedor, '0'))->update($arr_campos);

			// Graba tabla de exclusiones
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'compras',
				'campos' => '
					proex_proveedor,
					proex_nro_linea,
					proex_tipo_ret,
					proex_desde_fecha,
					proex_hasta_fecha,
					proex_porc_excl,
					proex_comentario
				',
				'whereArmado' => " WHERE proex_proveedor = '".$key."' " 
			);
			$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach ($dataAnita as $exclusion)
			{
				switch($exclusion->proex_tipo_ret)
				{
					case '0': // ganancias
						$tipoRetencion = 'G';
						break;
					case '1': // iva
						$tipoRetencion = 'I';
						break;
					case '2': // ingresos brutos
						$tipoRetencion = 'B';
						break;
				}
				$arr_proexcl = [
					"proveedor_id" => $proveedor->id,
					"comentario" => $exclusion->proex_comentario,
					"tiporetencion" => $tipoRetencion,
					"desdefecha" => $exclusion->proex_desde_fecha,
					"hastafecha" => $exclusion->proex_hasta_fecha,
					"porcentajeexclusion" => $exclusion->proex_porc_excl
				];
				if ($fl_crea_registro)
					$this->proveedor_exclusionRepository->createUnique($arr_proexcl);
			}
	
			// Graba tabla de formas de pago
			$data = array( 
				'acc' => 'list', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'compras',
				'campos' => '
					prop_proveedor,
					prop_nombre,
					prop_forma_pago,
					prop_cbu,
					prop_tipo_cta,
					prop_cod_mon,
					prop_nro_cuenta,
					prop_cuit,
					prop_cod_banco,
					prop_tipo_comp,
					prop_e_mail_conf,
					prop_offset
				',
				'whereArmado' => " WHERE prop_proveedor = '".$key."' " 
			);
			$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach ($dataAnita as $formapago)
			{
				// Busca forma de pago
				$forma = $this->formapagoRepository->findPorAbreviatura($formapago->prop_forma_pago);

				if ($forma)
					$formapago_id = $forma->id;
				else
					$formapago_id = 1;

				// Busca tipo de cuenta de caja
				$tipocuentacaja = $this->tipocuentacajaRepository->find($formapago->prop_tipo_cta);
				if ($tipocuentacaja)
					$tipocuentacaja_id = $tipocuentacaja->id;
				else
					$tipocuentacaja_id = 1;

				// Busca banco
				$banco = $this->bancoRepository->findPorCodigo($formapago->prop_cod_banco);
				if ($banco)
					$banco_id = $banco->id;
				else
					$banco_id = null;

				// Busca medio de pago
				$mediopago = $this->mediopagoRepository->findPorCodigo($formapago->prop_tipo_comp);
				if ($mediopago)
					$mediopago_id = $mediopago->id;
				else
					$mediopago_id = null;

				// Busca moneda
				$moneda = $this->monedaRepository->findPorCodigo($formapago->prop_cod_mon);
				if ($moneda)
					$moneda_id = $moneda->id;
				else
					$moneda_id = 1;

				$arr_formapago = [
					"proveedor_id" => $proveedor->id,
					"nombre" => $formapago->prop_nombre,
					"formapago_id" => $formapago_id,
					"cbu" => $formapago->prop_cbu,
					"tipocuentacaja_id" => $tipocuentacaja_id,
					"moneda_id" => $moneda_id,
					"numerocuenta" => $formapago->prop_nro_cuenta,
					"nroinscripcion" => $formapago->prop_cuit,
					"banco_id" => $banco_id,
					"mediopago_id" => $mediopago_id,
					"email" => $formapago->prop_e_mail_conf,
				];
				if ($fl_crea_registro)
					$this->proveedor_formapagoRepository->createUnique($arr_formapago);
			}			
        }
    }

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$cuentacontable = $condicioniva = $retieneganancia = $condicionganancia = '';
		$retieneiva = $retienesuss = $retieneiibb = $exclusionretiva = '';
		$fechaexclusionretiva = $exclusionretgan = $fechaexclusionretgan = '';
		$exclusionretib = $fechaexclusionretib = '';
		$tipoempresa = $tipoempresaalfa = '';
		$cuentacontableme = $cuentacontablecompra = $centrocostocompra = $conceptogasto = '';
		$fechainicioexclusionretiva = $fechainicioexclusionretgan = '';
		$fechainicioexclusionretib  = '';
		$retencioniva = $retencionganancia = $retencionsuss = 0;
		$condicionpago = $condicioncompra = $condicionentrega = 0;
		$tiposervicio = $regimenfacturacion = '';
		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $retieneganancia, $condicionganancia,
							$retieneiva, $retienesuss, $retieneiibb, 
							$exclusionretiva, $fechaexclusionretiva, 
							$exclusionretgan, $fechaexclusionretgan,
							$exclusionretib, $fechaexclusionretib, 
							$tipoempresa, $tipoempresaalfa,
							$cuentacontableme, $cuentacontablecompra, $centrocostocompra, $conceptogasto,
							$fechainicioexclusionretiva, $fechainicioexclusionretgan,
							$fechainicioexclusionretib,
							$retencioniva, $retencionganancia, $retencionsuss,
							$condicionpago, $condicioncompra, $condicionentrega, $tiposervicio, $regimenfacturacion);

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

        $data = array( 'tabla' => $this->tableAnita[0], 'acc' => 'insert',
			'sistema' => 'compras',
            'campos' => ' 
				prom_proveedor,
				prom_nombre,
				prom_contacto,
				prom_direccion,
				prom_localidad,
				prom_cod_postal,
				prom_provincia,
				prom_telefono,
				prom_cuit,
				prom_cond_iva,
				prom_letra,
				prom_cond_pago,
				prom_cta_contable,
				prom_credito,
				prom_dias_atraso,
				prom_nro_interno,
				prom_agente_ret,
				prom_cond_gan,
				prom_incl_impuesto,
				prom_cond_compra,
				prom_cond_entrega,
				prom_tipo_empresa,
				prom_prov_vario,
				prom_retiene_iva,
				prom_cod_retgan,
				prom_cod_retiva,
				prom_a_nombre_de,
				prom_ret_suss,
				prom_ret_ibr,
				prom_nro_ret_ibr,
				prom_nro_reemp_ib,
				prom_excl_retiva,
				prom_pais,
				prom_fecha_alta,
				prom_estado_pro,
				prom_fantasia,
				prom_regimen,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_cod_localidad,
				prom_tipo_emp_alfa,
				prom_e_mail,
				prom_fax,
				prom_fecha_boletin,
				prom_cod_ret_suss,
				prom_cta_cont_me,
				prom_cta_default,
				prom_cc_default,
				prom_concepto,
				prom_descuento,
				prom_fecha_exclib,
				prom_excl_retib,
				prom_fe_ini_excl,
				prom_fe_ini_exclrg,
				prom_fe_ini_exclib,
				prom_ag_perc_ib,
				prom_ag_perc_iva
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
				'".$request['letra']."',
				'".$condicionpago."',
				'".$cuentacontable."',
				'0',
				'0',
				'0',
				'".$retieneganancia."',
				'".$condicionganancia."',
				'N',
				'".$condicioncompra."',
				'".$condicionentrega."',
				'".$tipoempresa."',
				'".$tiposervicio."',
				'".$retieneiva."',
				'".$retencionganancia."',
				'".$retencioniva."',
				'".$nombre."',
				'".$retienesuss."',
				'".$retieneiibb."',
				'".$request['nroIIBB']."',
				' ',
				'".$exclusionretiva."'
				'".($request['pais_id']>0?$request['pais_id']:0)."',
				'".$fecha."',
				'".$request['estado']."',
				'".$request['fantasia']."',
				'".$regimenfacturacion."',
				'".$fechaexclusionretiva."',
				'".$exclusionretgan."',
				'".$fechaexclusionretgan."',
				'".$request['localidad_id']."',
				'".$tipoempresaalfa."',
				'".$request['email']."',
				'0',
				'0',
				'".$request['retencionsuss_id']."',
				'".$cuentacontableme."',
				'".$cuentacontablecompra."',
				'".$centrocostocompra."',
				'".$conceptogasto."',
				'0',
				'".$fechaexclusionretib."',
				'".$exclusionretib."',
				'".$fechainicioexclusionretiva."',
				'".$fechainicioexclusionretgan."',
				'".$fechainicioexclusionretib."',
				'".$request['agentepercepcionIIBB']."',
				'".$request['agentepercepcioniva']."' "
        );
        $apiAnita->apiCall($data);

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'compras',
            				'campos' => '
								prol_proveedor,
								prol_linea,
								prol_leyenda
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$apiAnita->apiCall($data);
		}

		// Graba datos adicionales promadic
		$data = array( 'tabla' => 'promadic', 
				'acc' => 'insert',
				'sistema' => 'compras',
				'campos' => '
					proad_proveedor,
					proad_e_mail_oc,
					proad_semaforo
							',
				'valores' => " 
					'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
					'".$request['emailoc']."', 
					'".substr($request['semaforo'],0,1)."' "
			);

		$apiAnita->apiCall($data);

		// Graba exclusiones
		Self::grabaExclusion($request);

		// Graba formas de pago
		Self::grabaFormaDePago($request);
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		$cuentacontable = $condicioniva = $retieneganancia = $condicionganancia = '';
		$retieneiva = $retienesuss = $retieneiibb = $exclusionretiva = '';
		$fechaexclusionretiva = $exclusionretgan = $fechaexclusionretgan = '';
		$exclusionretib = $fechaexclusionretib = '';
		$tipoempresa = $tipoempresaalfa = '';
		$cuentacontableme = $cuentacontablecompra = $centrocostocompra = $conceptogasto = '';
		$fechainicioexclusionretiva = $fechainicioexclusionretgan = '';
		$fechainicioexclusionretib  = '';		
		$retencioniva = $retencionganancia = $retencionsuss = 0;
		$condicionpago = $condicioncompra = $condicionentrega = 0;
		$tiposervicio = $regimenfacturacion = '';
		$this->setCamposAnita($request, $cuentacontable, $condicioniva, $retieneganancia, $condicionganancia,
							$retieneiva, $retienesuss, $retieneiibb, 
							$exclusionretiva, $fechaexclusionretiva, 
							$exclusionretgan, $fechaexclusionretgan,
							$exclusionretib, $fechaexclusionretib, 
							$tipoempresa, $tipoempresaalfa,
							$cuentacontableme, $cuentacontablecompra, $centrocostocompra, $conceptogasto,
							$fechainicioexclusionretiva, $fechainicioexclusionretgan,
							$fechainicioexclusionretib,
							$retencioniva, $retencionganancia, $retencionsuss,
							$condicionpago, $condicioncompra, $condicionentrega, $tiposervicio, $regimenfacturacion);
		
		if (array_key_exists('localidad_id', $request))
			$localidad_id = $request['localidad_id'];
		else
			$localidad_id = 0;

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'compras',
				'valores' => " 
					prom_proveedor 	  = '".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."',
					prom_nombre       = '".$nombre."',
					prom_contacto     = '".$contacto."',
					prom_direccion    = '".$domicilio."',
					prom_localidad    = '".$request['desc_localidad']."',
					prom_cod_postal   = '".$request['codigopostal']."',
					prom_provincia    = '".$request['desc_provincia']."',
					prom_telefono     = '".$request['telefono']."',
					prom_cuit         =	'".$request['nroinscripcion']."',
					prom_cond_iva     = '".$condicioniva."',
					prom_letra        = '".$request['letra']."',
					prom_cond_pago    = '".$condicionpago."',
					prom_cta_contable = '".$cuentacontable."',
					prom_agente_ret   = '".$retieneganancia."',
					prom_cond_gan     = '".$condicionganancia."',
					prom_cond_compra  = '".$condicioncompra."',
					prom_cond_entrega = '".$condicionentrega."',
					prom_tipo_empresa = '".$tipoempresa."',
					prom_retiene_iva  = '".$retieneiva."',
					prom_cod_retgan   = '".$retencionganancia."',
					prom_cod_retiva   = '".$retencioniva."',
					prom_ret_suss     = '".$retienesuss."',
					prom_ret_ibr      = '".$retieneiibb."',
					prom_nro_ret_ibr  = '".$request['nroIIBB']."',
					prom_excl_retiva  = '".$exclusionretiva."',
					prom_pais         = '".($request['pais_id']>0?$request['pais_id']:0)."',
					prom_estado_pro   = '".$request['estado']."',
					prom_fantasia     = '".$request['fantasia']."',
					prom_fecha_excl   = '".$fechaexclusionretiva."',
					prom_excl_retgan  = '".$exclusionretgan."',
					prom_fecha_exclrg = '".$fechaexclusionretgan."',
					prom_cod_localidad= '".$request['localidad_id']."',
					prom_tipo_emp_alfa= '".$tipoempresaalfa."',
					prom_e_mail       = '".$request['email']."',
					prom_cod_ret_suss = '".$request['retencionsuss_id']."',
					prom_cta_cont_me  = '".$cuentacontableme."',
					prom_cta_default  = '".$cuentacontablecompra."',
					prom_cc_default   = '".$centrocostocompra."',
					prom_concepto     = '".$conceptogasto."',
					prom_fecha_exclib = '".$fechaexclusionretib."',
					prom_excl_retib   = '".$exclusionretib."',
					prom_fe_ini_excl  = '".$fechainicioexclusionretiva."',
					prom_fe_ini_exclrg= '".$fechainicioexclusionretgan."',
					prom_fe_ini_exclib= '".$fechainicioexclusionretib."',
					prom_ag_perc_ib   = '".$request['agentepercepcionIIBB']."',
					prom_ag_perc_iva  = '".$request['agentepercepcioniva']."',
					prom_prov_vario   = '".$tiposervicio."',
					prom_regimen  = '".$regimenfacturacion."' "
					,
				'whereArmado' => " WHERE prom_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prol_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Graba leyenda
		$leyenda = explode("\n", $request['leyenda']);
		$linea = 0;
		foreach ($leyenda as $ley)
		{
        	$data = array( 'tabla' => $this->tableAnita[1], 'acc' => 'insert',
							'sistema' => 'compras',
            				'campos' => '
								prol_proveedor,
								prol_linea,
								prol_leyenda
										',
            				'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$linea++."', 
								'".preg_replace("/\r/", "", $ley)."' "
						);

        	$apiAnita->apiCall($data);
		}

		// Graba promadic
		$data = array( 'tabla' => 'promadic', 
				'acc' => 'update',
				'sistema' => 'compras',
				'valores' => " 
					proad_e_mail_oc = '".$request['emailoc']."',
					proad_semaforo  = '".substr($request['semaforo'],0,1)."' "
					,
				'whereArmado' => " WHERE proad_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );

		$apiAnita->apiCall($data);

		// Borra exclusiones
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE proex_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Graba exclusiones
		Self::grabaExclusion($request);

		// Borra formas de pago
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prop_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Graba formas de pago
		Self::grabaFormaDePago($request);
	}

	private function grabaExclusion($request)
	{
		// Graba exclusiones
		if (isset($request['desdefechas']))
		{
			$apiAnita = new ApiAnita();

			$desdefechas = $request['desdefechas'];
			$hastafechas = $request['hastafechas'];
			$porcentajeexclusiones = $request['porcentajeexclusiones'];
			$tiporetenciones = $request['tiporetenciones'];
			$comentarios = $request['comentarios'];
			
			if ($desdefechas[0] != null)
				$qExclusion = count($desdefechas);
			else
				$qExclusion = 0;
			for ($i_exclusion=0; $i_exclusion < $qExclusion; $i_exclusion++) 
			{
				$desdeFecha = Carbon::createFromFormat( 'Y-m-d', $desdefechas[$i_exclusion])->format('Ymd');
				$hastaFecha = Carbon::createFromFormat( 'Y-m-d', $hastafechas[$i_exclusion])->format('Ymd');
				$data = array( 'tabla' => $this->tableAnita[2], 'acc' => 'insert',
						'sistema' => 'compras',
							'campos' => '
							proex_proveedor,
							proex_nro_linea,
							proex_tipo_ret,
							proex_desde_fecha,
							proex_hasta_fecha,
							proex_porc_excl,
							proex_comentario
							',
						'valores' => " 
								'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
								'".$i_exclusion."', 
								'".$tiporetenciones[$i_exclusion]."',
								'".$desdeFecha."',
								'".$hastaFecha."',
								'".$porcentajeexclusiones[$i_exclusion]."',
								'".$comentarios[$i_exclusion]."' "
						);
				$apiAnita->apiCall($data);
			}
		}
	}

	private function grabaFormaDePago($request)
	{
		if (isset($request['nombres']))
		{
			$apiAnita = new ApiAnita();

			// Graba formas de pago
			$nombres = $request['nombres'];
			$formapago_ids = $request['formapago_ids'];
			$cbus = $request['cbus'];
			$tipocuentacaja_ids = $request['tipocuentacaja_ids'];
			$monedas_ids = $request['moneda_ids'];
			$numerocuentas = $request['numerocuentas'];
			$nroinscripciones = $request['nroinscripciones'];
			$banco_ids = $request['banco_ids'];
			$mediopago_ids = $request['mediopago_ids'];
			$emails = $request['emails'];
			if ($formapago_ids[0] != null)
				$qFormaPago = count($formapago_ids);
			else
				$qFormaPago = 0;
			for ($i_formapago=0; $i_formapago < $qFormaPago; $i_formapago++) 
			{
				// Busca forma de pago
				$formapago = $this->formapagoRepository->find($formapago_ids[$i_formapago]);
				if ($formapago)
					$formaPago = $formapago->abreviatura;
				else
					$formaPago = null;

				// Busca tipo de cuenta de caja
				$tipocuentacaja = $this->tipocuentacajaRepository->find($tipocuentacaja_ids[$i_formapago]);
				if ($tipocuentacaja)
					$tipoCuenta = $tipocuentacaja->id;
				else
					$tipoCuenta = null;

				// Busca banco
				$banco = $this->bancoRepository->find($banco_ids[$i_formapago]);
				if ($banco)
					$codigoBanco = $banco->codigo;
				else
					$codigoBanco = null;

				// Busca medio de pago
				$mediopago = $this->mediopagoRepository->find($mediopago_ids[$i_formapago]);
				if ($mediopago)
					$tipoComprobante = $mediopago->codigo;
				else
					$tipoComprobante = null;

				$data = array( 'tabla' => $this->tableAnita[3], 'acc' => 'insert',
				'sistema' => 'compras',
				'campos' => '
						prop_proveedor,
						prop_nombre,
						prop_forma_pago,
						prop_cbu,
						prop_tipo_cta,
						prop_cod_mon,
						prop_nro_cuenta,
						prop_cuit,
						prop_cod_banco,
						prop_tipo_comp,
						prop_e_mail_conf,
						prop_offset
					',
				'valores' => " 
						'".str_pad($request['codigo'], 6, "0", STR_PAD_LEFT)."', 
						'".$nombres[$i_formapago]."', 
						'".$formaPago."',
						'".$cbus[$i_formapago]."',
						'".$tipoCuenta."',
						'".$monedas_ids[$i_formapago]."',
						'".$numerocuentas[$i_formapago]."', 
						'".$nroinscripciones[$i_formapago]."', 
						'".$codigoBanco."',
						'".$tipoComprobante."',
						'".$emails[$i_formapago]."',
						'".$i_formapago."' "
				);
				$apiAnita->apiCall($data);
			}
		}
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prom_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prol_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra exclusiones
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
			'sistema' => 'compras',
			'whereArmado' => " WHERE proex_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

		// Borra formas de pago
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prop_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de proveedors + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita[0], 
				'campos' => " max(prom_proveedor) as $this->keyFieldAnita "
				);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$codigo = 0;
        if (isset($dataAnita)) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}

	private function setCamposAnita($data, &$cuentacontable, 
									&$condicioniva, &$retieneganancia, 
									&$condicionganancia, &$retieneiva, &$retienesuss, &$retieneiibb, 
									&$exclusionretiva, &$fechaexclusionretiva, 
									&$exclusionretgan, &$fechaexclusionretgan, 
									&$exclusionretib, &$fechaexclusionretib,
									&$tipoempresa, &$tipoempresaalfa,
									&$cuentacontableme, &$cuentacontablecompra, &$centrocostocompra, 
									&$conceptogasto, &$fechainicioexclusionretiva, &$fechainicioexclusionretgan,
									&$fechainicioexclusionretib,
									&$retencioniva, &$retencionganancia, &$retencionsuss,
									&$condicionpago, &$condicioncompra, &$condicionentrega, &$tiposervicio, &$regimenfacturacion) 
	{
		$cuenta = $this->cuentacontableRepository->find($data['cuentacontable_id']);
		if ($cuenta)
			$cuentacontable = $cuenta->codigo;
		else
			$cuentacontable = NULL;
		
		$condicioniva = 1;
		switch($data['condicioniva_id'])
		{
		case '1': // Inscripto
			$condicioniva = 1;
			break;
		case '7': // No inscripto
			$condicioniva = 2;
			break;
		case '2': // Exento
			$condicioniva = 3;
			break;
		case '4': // Monotributo
			$condicioniva = 4;
			break;
		}
					
		$retieneganancia = ($data['retieneganancia'] == 'S' ? 'N' : 'S');

		switch($data['condicionganancia'])
		{
		case 'I':
			$condicionganancia = '1';
			break;
		case 'N':
			$condicionganancia = '2';
			break;
		case 'C':
			$condicionganancia = '3';
			break;
		}

		$retieneiva = $data['retieneiva'];
		$retienesuss = $data['retienesuss'];

		$exclusionretiva = $exclusionretgan = $exclusionretib = 0;
		$fechaexclusionretiva = $fechaexclusionretgan = $fechaexclusionretib = 0;
		$fechainicioexclusionretiva = $fechainicioexclusionretgan = $fechainicioexclusionretib = 0;
		if (isset($data['desdefechas']))
		{
			$desdefechas = $data['desdefechas'];
			$hastafechas = $data['hastafechas'];
			$porcentajeexclusiones = $data['porcentajeexclusiones'];
			$tiporetenciones = $data['tiporetenciones'];

			for ($i = 0; $i < count($desdefechas); $i++)
			{
				switch($tiporetenciones[$i])
				{
					case 'I':
						$exclusionretiva = $porcentajeexclusiones[$i];
						$fechaexclusionretiva = $hastafechas[$i];
						$fechainicioexclusionretiva = $desdefechas[$i];
						break;
					case 'G': 
						$exclusionretgan = $porcentajeexclusiones[$i];
						$fechaexclusionretgan = $hastafechas[$i];
						$fechainicioexclusionretgan = $desdefechas[$i];
						break;					
					case 'B':
						$exclusionretib = $porcentajeexclusiones[$i];
						$fechaexclusionretib = $hastafechas[$i];
						$fechainicioexclusionretib = $desdefechas[$i];
						break;
				}
			}
		}

		switch($data['condicionIIBB_id'])
		{
		case 1:
			$retieneiibb = 'S';
			break;
		case 2:
			$retieneiibb = 'L';
			break;
		case 3:
			$retieneiibb = 'E';
			break;
		}

		$tipoemp = $this->tipoempresaRepository->find($data['tipoempresa_id']);
		if ($tipoemp)
		{
			$tipoempresa = $tipoemp->codigo;
			$tipoempresaalfa = $tipoemp->nombre;
		}
		else
		{
			$tipoempresa = 0;
			$tipoempresaalfa = '';
		}

		$cuenta = $this->cuentacontableRepository->findPorId($data['cuentacontableme_id']);
		if ($cuenta)
			$cuentacontableme = $cuenta->codigo;
		else
			$cuentacontableme = NULL;
			
		$cuenta = $this->cuentacontableRepository->findPorId($data['cuentacontablecompra_id']);
		if ($cuenta)
			$cuentacontablecompra = $cuenta->codigo;
		else
			$cuentacontablecompra = NULL;
		
		$centrocosto = $this->centrocostoRepository->findPorId($data['centrocostocompra_id']);
		if ($centrocosto)
			$centrocostocompra = $centrocosto->codigo;
		else
			$centrocostocompra = 0;

		if ($data['conceptogasto_id'] == 66) // Sin clasificar
			$conceptogasto = 0;
		else
			$conceptogasto = $data['conceptogasto_id'];
			
		$retiva = $this->retencionivaRepository->findPorId($data['retencioniva_id']);
		if ($retencioniva)
			$retencioniva = $retiva->codigo;
		else
			$retencioniva = 0;

		$retganancia = $this->retenciongananciaRepository->findPorId($data['retencionganancia_id']);
		if ($retganancia)
			$retencionganancia = $retganancia->codigo;
		else
			$retencionganancia = 0;

		$retsuss = $this->retencionsussRepository->findPorId($data['retencionsuss_id']);
		if ($retsuss)
			$retencionsuss  = $retsuss->codigo;
		else
			$retencionsuss  = 0;

		$condpago = $this->condicionpagoRepository->findPorId($data['condicionpago_id']);
		if ($condpago)
			$condicionpago = $condpago->codigo;
		else
			$condicionpago = 0;

		$condentrega = $this->condicionentregaRepository->findPorId($data['condicionentrega_id']);
		if ($condentrega)
			$condicionentrega  = $condentrega->codigo;
		else
			$condicionentrega  = 0;
		
		$condcompra = $this->condicioncompraRepository->findPorId($data['condicioncompra_id']);
		if ($condcompra)
			$condicioncompra  = $condcompra->codigo;
		else
			$condicioncompra  = 0;

		$regimenfacturacion = '0';
		switch($data['regimenfacturacion'])
		{
			case 'RG 3419':
				$regimenfacturacion = '0';
				break;
			case 'FCE':
				$regimenfacturacion = '1';
				break;
		}

		$tiposervicio = 'P';
		switch($data['tiposervicio_proveedor_id'])
		{
			case 1:
				$tiposervicio = 'P';
				break;
			case 2:
				$tiposervicio = 'S';
				break;
			case 3:
				$tiposervicio = 'V';
				break;
		}		
	}

	public function leeProveedor($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $proveedor = $this->model->select('proveedor.id as id',
                                        'proveedor.nombre as nombre',
										'proveedor.fantasia as fantasia',
										'proveedor.nroinscripcion as numerodocumento',
                                        'proveedor.domicilio as domicilio',
										'proveedor.codigo as codigo',
                                        'localidad.nombre as nombrelocalidad',
										'provincia.nombre as nombreprovincia',
										'proveedor.estado as estado')
                                ->leftjoin('localidad', 'localidad.id', 'proveedor.localidad_id')
								->leftjoin('provincia', 'provincia.id', 'proveedor.provincia_id');
		
		$proveedor = $proveedor->where(function ($query) use ($busqueda) {
                	$query->orWhere('proveedor.id', $busqueda)
							->orWhere('proveedor.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('proveedor.fantasia', 'like', '%'.$busqueda.'%')
							->orWhere('proveedor.nroinscripcion', 'like', '%'.$busqueda.'%')
							->orWhere('proveedor.domicilio', 'like', '%'.$busqueda.'%')
							->orWhere('proveedor.codigo', 'like', '%'.$busqueda.'%')
							->orWhere('proveedor.estado', 'like', '%'.$busqueda.'%')
							->orWhere('localidad.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('provincia.nombre', 'like', '%'.$busqueda.'%');
					});

		$proveedor = $proveedor->orderby('id', 'DESC');
                                
		//dd($permisos['permisos']);
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $proveedor = $proveedor->paginate(10);
            else
                $proveedor = $proveedor->get();
        }
        else
            $proveedor = $proveedor->get();

        return $proveedor;
    }

}
