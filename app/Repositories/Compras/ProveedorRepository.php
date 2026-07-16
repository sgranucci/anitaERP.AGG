<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Exclusion;
use App\Models\Compras\Proveedor_Formapago;
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
use App\Repositories\Configuracion\LocalidadRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Support\Compras\ProveedorExclusionAnitaSupport;
use App\Support\Compras\ProveedorListadoFiltros;
use App\Models\Seguridad\Usuario;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class ProveedorRepository implements ProveedorRepositoryInterface
{
    use AnitaBridgeEscritura;

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
	private $localidadRepository;

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
								MonedaRepositoryInterface $monedarepository,
								LocalidadRepositoryInterface $localidadrepository
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
		$this->localidadRepository = $localidadrepository;
    }

    public function create(array $data)
    {
		$data = \App\Support\Compras\ProveedorImpuestosRetencionRules::normalizar($data);

		$data['codigo'] = $this->resolverCodigoAlta($data);

		if (substr(config("proveedor.tipoalta"),0,1) == 'P')
			$data['estado'] = 'Alta Pendiente';
		else
			$data['estado'] = 'Activo';

		if ($data['retieneiva'] == null)
			$data['retieneiva'] = 'N';

		if ($data['retieneganancia'] == null)
			$data['retieneganancia'] = 'N';

		if ($data['retienesuss'] == null)
			$data['retienesuss'] = 'N';
        $proveedor = $this->model->create($data);

		self::guardarAnita($data);

		return $proveedor;
    }

    public function update(array $data, $id)
    {
		$data = \App\Support\Compras\ProveedorImpuestosRetencionRules::normalizar($data);

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

    public function existeProveedorPorCodigo(string $codigo): bool
    {
        $codigo = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita($codigo);

        return $this->model->where('codigo', $codigo)->exists();
    }

    public function previewSincronizacionDesdeAnita(string $codigoAnita): ?array
    {
        $key = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($codigoAnita);
        $codigoErp = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita($key);
        $apiAnita = new ApiAnita();

        $promae = $this->consultarPromaeAnita($apiAnita, $key);
        if ($promae === null) {
            return null;
        }

        $filasProexcl = $this->consultarProexclAnita($apiAnita, $key);
        $lineasExclusion = ProveedorExclusionAnitaSupport::lineasErpDesdeAnita($filasProexcl, $promae);
        $filasPropago = $this->consultarPropagoAnita($apiAnita, $key);
        $existe = $this->existeProveedorPorCodigo($codigoErp);

        return [
            'codigo' => $codigoErp,
            'codigo_anita' => $key,
            'nombre_anita' => trim((string) ($promae->prom_nombre ?? '')),
            'accion' => $existe ? 'actualizar' : 'insertar',
            'exclusiones_anita' => count($lineasExclusion),
            'formapagos_anita' => count($filasPropago),
            'proexcl_filas' => count($filasProexcl),
            'proexcl_tipo_invalido' => count($filasProexcl) - count(array_filter($filasProexcl, function ($fila) use ($promae) {
                $tipo = ProveedorExclusionAnitaSupport::tipoRetencionAnitaErpCodigo((string) ($fila->proex_tipo_ret ?? ''));

                return $tipo !== null
                    || ProveedorExclusionAnitaSupport::inferirTipoRetencionDesdePromaePorFechas($fila, $promae) !== null
                    || ProveedorExclusionAnitaSupport::inferirTipoRetencionDesdeComentario((string) ($fila->proex_comentario ?? '')) !== null;
            })),
        ];
    }

    /**
     * @return array{
     *     insertados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     errores: int,
     *     solo_en_erp: list<string>,
     *     dry_run: bool
     * }
     */
    public function resincronizarDesdeAnita(bool $dryRun = false): array
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '512M');

        $stats = [
            'insertados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => 0,
            'solo_en_erp' => [],
            'dry_run' => $dryRun,
        ];

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'compras',
            'campos' => "{$this->keyFieldAnita} as {$this->keyField}, {$this->keyFieldAnita}",
            'tabla' => $this->tableAnita[0],
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita)) {
            return $stats;
        }

        $codigosAnita = [];
        foreach ($dataAnita as $value) {
            $codigoAnita = (string) ($value->{$this->keyFieldAnita} ?? '');
            if ($codigoAnita === '') {
                continue;
            }
            $codigosAnita[] = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita($codigoAnita);

            if ($dryRun) {
                $preview = $this->previewSincronizacionDesdeAnita($codigoAnita);
                if ($preview === null) {
                    $stats['omitidos']++;

                    continue;
                }
                if ($preview['accion'] === 'insertar') {
                    $stats['insertados']++;
                } else {
                    $stats['actualizados']++;
                }

                continue;
            }

            try {
                $resultado = $this->traerRegistroDeAnita($codigoAnita);
                if ($resultado === 'insertado') {
                    $stats['insertados']++;
                } elseif ($resultado === 'actualizado') {
                    $stats['actualizados']++;
                } else {
                    $stats['omitidos']++;
                }
            } catch (\Throwable) {
                $stats['errores']++;
            }
        }

        $codigosAnitaFlip = array_flip($codigosAnita);
        $this->model->newQuery()
            ->whereNull('deleted_at')
            ->orderBy('codigo')
            ->pluck('codigo')
            ->each(function ($codigoErp) use (&$stats, $codigosAnitaFlip) {
                $codigo = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita((string) $codigoErp);
                if (! isset($codigosAnitaFlip[$codigo])) {
                    $stats['solo_en_erp'][] = $codigo;
                }
            });

        return $stats;
    }

    public function sincronizarConAnita(){
		$this->resincronizarDesdeAnita(false);
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public function traerRegistroDeAnita(string $codigoAnita, ?bool $fl_crea_registro = null): ?string
    {
        $key = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($codigoAnita);
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

		$usuario_id = Auth::id() ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if (! is_array($dataAnita) || ! isset($dataAnita[0])) {
            return null;
        }

        if (isset($dataAnita)) {
            $data = $dataAnita[0];
            $codigoErp = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita((string) $data->prom_proveedor);
            if ($fl_crea_registro === null) {
                $fl_crea_registro = ! $this->existeProveedorPorCodigo($codigoErp);
            }
            $resultado = null;

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
				"codigo" => $codigoErp,
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
	
			if ($fl_crea_registro) {
            	$proveedor = $this->model->create($arr_campos);
                $resultado = 'insertado';
            } else {
                $proveedor = $this->model->where('codigo', $codigoErp)->first();
                if ($proveedor === null) {
                    $proveedor = $this->model->create($arr_campos);
                    $resultado = 'insertado';
                } else {
                    $proveedor->update($arr_campos);
                    $proveedor = $proveedor->fresh();
                    $resultado = 'actualizado';
                }
            }

            $filasProexcl = $this->consultarProexclAnita($apiAnita, $key);
            $this->reemplazarExclusionesDesdeAnita($proveedor, $filasProexcl, $data);

            $filasPropago = $this->consultarPropagoAnita($apiAnita, $key);
            $this->reemplazarFormapagosDesdeAnita($proveedor, $filasPropago);

            return $resultado;
        }

        return null;
    }

    private function consultarPromaeAnita(ApiAnita $apiAnita, string $key): ?object
    {
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[0],
            'sistema' => 'compras',
            'campos' => '
				prom_proveedor,
				prom_nombre,
				prom_excl_retiva,
				prom_fecha_excl,
				prom_fe_ini_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_fe_ini_exclrg,
				prom_excl_retib,
				prom_fecha_exclib,
				prom_fe_ini_exclib
			',
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '{$key}' ",
        ];
        $filas = ApiAnita::decodificarListaFilas($apiAnita->apiCall($data));

        return $filas[0] ?? null;
    }

    /**
     * @return list<object>
     */
    private function consultarProexclAnita(ApiAnita $apiAnita, string $key): array
    {
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[2],
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
            'whereArmado' => " WHERE proex_proveedor = '{$key}' ",
        ];

        return ApiAnita::decodificarListaFilas($apiAnita->apiCall($data));
    }

    /**
     * @return list<object>
     */
    private function consultarPropagoAnita(ApiAnita $apiAnita, string $key): array
    {
        $data = [
            'acc' => 'list',
            'tabla' => $this->tableAnita[3],
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
            'whereArmado' => " WHERE prop_proveedor = '{$key}' ",
        ];

        return ApiAnita::decodificarListaFilas($apiAnita->apiCall($data));
    }

    private function reemplazarExclusionesDesdeAnita(Proveedor $proveedor, array $filasProexcl, object $promae): void
    {
        Proveedor_Exclusion::query()->where('proveedor_id', $proveedor->id)->delete();

        $lineas = ProveedorExclusionAnitaSupport::lineasErpDesdeAnita($filasProexcl, $promae);
        foreach ($lineas as $linea) {
            Proveedor_Exclusion::query()->create([
                'proveedor_id' => $proveedor->id,
                'comentario' => $linea['comentario'],
                'tiporetencion' => $linea['tiporetencion'],
                'desdefecha' => $linea['desdefecha'],
                'hastafecha' => $linea['hastafecha'],
                'porcentajeexclusion' => $linea['porcentajeexclusion'],
            ]);
        }
    }

    private function reemplazarFormapagosDesdeAnita(Proveedor $proveedor, array $filasPropago): void
    {
        Proveedor_Formapago::query()->where('proveedor_id', $proveedor->id)->delete();

        foreach ($filasPropago as $formapago) {
            $forma = $this->formapagoRepository->findPorAbreviatura($formapago->prop_forma_pago);
            $formapago_id = $forma ? $forma->id : 1;

            $tipocuentacaja = $this->tipocuentacajaRepository->find($formapago->prop_tipo_cta);
            $tipocuentacaja_id = $tipocuentacaja ? $tipocuentacaja->id : 1;

            $banco = $this->bancoRepository->findPorCodigo($formapago->prop_cod_banco);
            $banco_id = $banco ? $banco->id : null;

            $mediopago = $this->mediopagoRepository->findPorCodigo($formapago->prop_tipo_comp);
            $mediopago_id = $mediopago ? $mediopago->id : null;

            $moneda = $this->monedaRepository->findPorCodigo($formapago->prop_cod_mon);
            $moneda_id = $moneda ? $moneda->id : 1;

            Proveedor_Formapago::query()->create([
                'proveedor_id' => $proveedor->id,
                'nombre' => $formapago->prop_nombre,
                'formapago_id' => $formapago_id,
                'cbu' => $formapago->prop_cbu,
                'tipocuentacaja_id' => $tipocuentacaja_id,
                'moneda_id' => $moneda_id,
                'numerocuenta' => $formapago->prop_nro_cuenta,
                'nroinscripcion' => $formapago->prop_cuit,
                'banco_id' => $banco_id,
                'mediopago_id' => $mediopago_id,
                'email' => $formapago->prop_e_mail_conf,
            ]);
        }
    }

	protected function anitaBridgeLogEvento(): string
	{
		return 'proveedor.anita_bridge.fallo';
	}

	private function guardarAnita($request) {
        $apiAnita = new ApiAnita();
		$codigoAnita = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge((string) $request['codigo']);

		// Reintento / huérfano Anita: actualizar cabecera en lugar de insertar de nuevo.
		if ($this->consultarPromaeAnitaConReintento($apiAnita, $codigoAnita) !== null) {
			$this->actualizarAnita($request, $request['codigo']);

			return;
		}

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

		$localidad_id = $request['localidad_id'] ?? null;
		$localidad = ($localidad_id !== null && $localidad_id !== '' && $localidad_id !== '0')
			? $this->localidadRepository->findPorId($localidad_id)
			: null;

		if ($localidad)
			$codigolocalidad = $localidad->codigo;
		else
			$codigolocalidad = 0;

		$estado = '0';
		switch($request['estado'])
		{
			case 'Activo':
				$estado = '0';
				break;
			case 'Suspendido':
				$estado = '1';
				break;
			case 'Alta Pendiente':
				$estado = '2';
				break;
			case 'Regularizado':
				$estado = '3';
				break;
		}			

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
				'".$codigoAnita."', 
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
				'".$exclusionretiva."',
				'".($request['pais_id']>0?$request['pais_id']:0)."',
				'".$fecha."',
				'".$estado."',
				'".$request['fantasia']."',
				'".$regimenfacturacion."',
				'".$fechaexclusionretiva."',
				'".$exclusionretgan."',
				'".$fechaexclusionretgan."',
				'".$codigolocalidad."',
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
        try {
			$this->apiCallAnitaEscritura($apiAnita, $data, 'promae insert');
		} catch (\RuntimeException $e) {
			// Carrera / lectura Anita flaky: si la clave ya existe, completar como actualización.
			if (stripos($e->getMessage(), 'duplicate') === false) {
				throw $e;
			}
			$this->actualizarAnita($request, $request['codigo']);

			return;
		}

		$this->reemplazarDependientesAnita($request, $apiAnita, $codigoAnita);
	}

	private function limpiarTablasDependientesAnita(ApiAnita $apiAnita, string $codigoAnita): void
	{
		$tablas = [
			[$this->tableAnita[1], 'prol_proveedor', 'proley delete previo alta'],
			['promadic', 'proad_proveedor', 'promadic delete previo alta'],
			[$this->tableAnita[2], 'proex_proveedor', 'proexcl delete previo alta'],
			[$this->tableAnita[3], 'prop_proveedor', 'propago delete previo alta'],
		];

		foreach ($tablas as [$tabla, $campo, $contexto]) {
			$this->borrarDependienteAnitaVerificado($apiAnita, $tabla, $campo, $codigoAnita, $contexto);
		}
	}

	/**
	 * Borra filas de una tabla dependiente y confirma que quedaron en cero.
	 * El bridge Anita a veces responde OK sin borrar; sin esta verificación el
	 * insert posterior choca con clave duplicada (proley/propago).
	 */
	private function borrarDependienteAnitaVerificado(
		ApiAnita $apiAnita,
		string $tabla,
		string $campo,
		string $codigoAnita,
		string $contexto
	): void {
		for ($intento = 1; $intento <= 4; $intento++) {
			$this->apiCallAnitaEscritura($apiAnita, [
				'acc' => 'delete',
				'tabla' => $tabla,
				'sistema' => 'compras',
				'whereArmado' => " WHERE {$campo} = '{$codigoAnita}' ",
			], $contexto);

			$restantes = ApiAnita::decodificarListaFilas($apiAnita->apiCall([
				'acc' => 'list',
				'tabla' => $tabla,
				'sistema' => 'compras',
				'campos' => $campo,
				'whereArmado' => " WHERE {$campo} = '{$codigoAnita}' ",
			]));

			if ($restantes === []) {
				return;
			}

			usleep(150000);
		}

		throw new \RuntimeException(
			"No se pudieron limpiar filas previas en Anita ({$tabla} {$campo}={$codigoAnita})."
		);
	}

	private function reemplazarDependientesAnita(array $request, ApiAnita $apiAnita, string $codigoAnita): void
	{
		$this->limpiarTablasDependientesAnita($apiAnita, $codigoAnita);

		$leyenda = explode("\n", (string) ($request['leyenda'] ?? ''));
		$linea = 0;
		foreach ($leyenda as $ley) {
			$textoLeyenda = ProveedorExclusionAnitaSupport::escaparSqlAnita(preg_replace("/\r/", '', $ley));
			$data = [
				'tabla' => $this->tableAnita[1],
				'acc' => 'insert',
				'sistema' => 'compras',
				'campos' => '
								prol_proveedor,
								prol_linea,
								prol_leyenda
										',
				'valores' => "
								'".$codigoAnita."',
								'".$linea++."',
								'".$textoLeyenda."' ",
			];
			$this->apiCallAnitaEscritura($apiAnita, $data, 'proley insert');
		}

		$data = [
			'tabla' => 'promadic',
			'acc' => 'insert',
			'sistema' => 'compras',
			'campos' => '
					proad_proveedor,
					proad_e_mail_oc,
					proad_semaforo
							',
			'valores' => "
					'".$codigoAnita."',
					'".ProveedorExclusionAnitaSupport::escaparSqlAnita((string) ($request['emailoc'] ?? ''))."',
					'".substr((string) ($request['semaforo'] ?? ' '), 0, 1)."' ",
		];
		$this->apiCallAnitaEscritura($apiAnita, $data, 'promadic insert');

		self::grabaExclusion($request, $apiAnita);
		self::grabaFormaDePago($request, $apiAnita);
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

		$localidad = ($localidad_id !== null && $localidad_id !== '' && $localidad_id !== '0')
			? $this->localidadRepository->findPorId($localidad_id)
			: null;

		if ($localidad)
			$codigolocalidad = $localidad->codigo;
		else
			$codigolocalidad = 0;

		$nombre = preg_replace('([^A-Za-z0-9 ])', '', $request['nombre']);
		$contacto = preg_replace('([^A-Za-z0-9 ])', '', $request['contacto']);
		$domicilio = preg_replace('([^A-Za-z0-9 ])', '', $request['domicilio']);

		$estado = '0';
		switch($request['estado'])
		{
			case 'Activo':
				$estado = '0';
				break;
			case 'Suspendido':
				$estado = '1';
				break;
			case 'Alta Pendiente':
				$estado = '2';
				break;
			case 'Regularizado':
				$estado = '3';
				break;
		}			

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
					prom_estado_pro   = '".$estado."',
					prom_fantasia     = '".$request['fantasia']."',
					prom_fecha_excl   = '".$fechaexclusionretiva."',
					prom_excl_retgan  = '".$exclusionretgan."',
					prom_fecha_exclrg = '".$fechaexclusionretgan."',
					prom_cod_localidad= '".$codigolocalidad."',
					prom_tipo_emp_alfa= '".$tipoempresaalfa."',
					prom_e_mail       = '".$request['email']."',
					prom_cod_ret_suss = '".$retencionsuss."',
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

        $this->apiCallAnitaEscritura($apiAnita, $data, 'promae update');

		$codigoAnita = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge((string) $id);
		$this->reemplazarDependientesAnita($request, $apiAnita, $codigoAnita);
	}

	private function grabaExclusion($request, ?ApiAnita $apiAnita = null)
	{
		$lineas = ProveedorExclusionAnitaSupport::lineasDesdeRequest($request);
		if ($lineas === []) {
			return;
		}

		$apiAnita = $apiAnita ?? new ApiAnita();

		foreach ($lineas as $iExclusion => $linea) {
			$data = [
				'tabla' => $this->tableAnita[2],
				'acc' => 'insert',
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
								'".str_pad($request['codigo'], 6, '0', STR_PAD_LEFT)."',
								'".$iExclusion."',
								'".$linea['tipo_anita']."',
								'".ProveedorExclusionAnitaSupport::fechaAnitaInformix($linea['desde'])."',
								'".ProveedorExclusionAnitaSupport::fechaAnitaInformix($linea['hasta'])."',
								'".$linea['porcentaje']."',
								'".ProveedorExclusionAnitaSupport::escaparSqlAnita($linea['comentario'])."' ",
			];
			$this->apiCallAnitaEscritura($apiAnita, $data, 'proexcl insert');
		}
	}

	private function grabaFormaDePago($request, ?ApiAnita $apiAnita = null)
	{
		if (isset($request['nombres']))
		{
			$apiAnita = $apiAnita ?? new ApiAnita();

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
				$this->apiCallAnitaEscritura($apiAnita, $data, 'propago insert');
			}
		}
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prom_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'promae delete');

		// Borra leyenda
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prol_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'proley delete');

		// Borra exclusiones
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[2], 
			'sistema' => 'compras',
			'whereArmado' => " WHERE proex_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'proexcl delete');

		// Borra formas de pago
		$data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[3], 
				'sistema' => 'compras',
				'whereArmado' => " WHERE prop_proveedor = '".str_pad($id, 6, "0", STR_PAD_LEFT)."' " );
        $this->apiCallAnitaEscritura($apiAnita, $data, 'propago delete');
	}

	// Devuelve ultimo codigo de proveedors + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$codigo = $this->leerProximoCodigoDesdeMaxAnita();
	}

	private function leerProximoCodigoDesdeMaxAnita(): int
	{
		$apiAnita = new ApiAnita();
		$ultimoError = null;

		for ($intento = 1; $intento <= 3; $intento++) {
			$raw = (string) $apiAnita->apiCall([
				'acc' => 'list',
				'tabla' => $this->tableAnita[0],
				'sistema' => 'compras',
				'campos' => " max({$this->keyFieldAnita}) as {$this->keyFieldAnita} ",
			]);
			$ultimoError = ApiAnita::extraerMensajeError($raw === '' ? null : $raw);
			$filas = ApiAnita::decodificarListaFilas($raw);
			if ($ultimoError === null && $filas !== [] && isset($filas[0]->{$this->keyFieldAnita})) {
				$max = (int) ltrim((string) $filas[0]->{$this->keyFieldAnita}, '0');

				return max(1, $max + 1);
			}
			usleep(150000);
		}

		throw new \RuntimeException(
			'No se pudo leer el último código de proveedor en Anita'
			.($ultimoError ? ': '.$ultimoError : '.')
		);
	}

	private function resolverCodigoAlta(array $data): string
	{
		$cuit = trim((string) ($data['nroinscripcion'] ?? ''));
		if ($cuit !== '') {
			$codigoHuerfano = $this->reutilizarCodigoHuerfanoAnitaPorCuit($cuit);
			if ($codigoHuerfano !== null) {
				return $codigoHuerfano;
			}
		}

		return $this->proximoCodigoDisponible();
	}

	/**
	 * Si Anita quedó con promae del mismo CUIT sin fila ERP (alta fallida), reutiliza ese código
	 * para completar el alta sin generar otra clave nueva.
	 */
	private function reutilizarCodigoHuerfanoAnitaPorCuit(string $cuit): ?string
	{
		$apiAnita = new ApiAnita();
		$cuitEsc = str_replace("'", "''", $cuit);
		$filas = [];

		for ($intento = 1; $intento <= 3; $intento++) {
			$raw = (string) $apiAnita->apiCall([
				'acc' => 'list',
				'tabla' => $this->tableAnita[0],
				'sistema' => 'compras',
				'campos' => 'prom_proveedor,prom_cuit',
				'whereArmado' => " WHERE prom_cuit = '{$cuitEsc}' ",
			]);
			if (ApiAnita::extraerMensajeError($raw === '' ? null : $raw) !== null) {
				usleep(150000);
				continue;
			}
			$filas = ApiAnita::decodificarListaFilas($raw);
			if ($filas !== []) {
				break;
			}
			usleep(150000);
		}

		$mejor = null;
		$mejorNum = -1;
		foreach ($filas as $fila) {
			$codigoAnita = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge((string) ($fila->prom_proveedor ?? ''));
			$codigoErp = ProveedorExclusionAnitaSupport::codigoErpDesdeAnita($codigoAnita);
			if ($this->model->where('codigo', $codigoErp)->exists()) {
				continue;
			}
			$num = (int) $codigoErp;
			if ($num > $mejorNum) {
				$mejorNum = $num;
				$mejor = $codigoErp;
			}
		}

		return $mejor;
	}

	private function proximoCodigoDisponible(): string
	{
		$apiAnita = new ApiAnita();
		$candidato = $this->leerProximoCodigoDesdeMaxAnita();
		$intentos = 0;

		while ($intentos < 100) {
			$codigoErp = (string) $candidato;
			$codigoAnita = ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($codigoErp);

			$existeErp = $this->model->where('codigo', $codigoErp)->exists();
			$existeAnita = $this->consultarPromaeAnitaConReintento($apiAnita, $codigoAnita);
			if (! $existeErp && $existeAnita === null) {
				return $codigoErp;
			}

			$candidato++;
			$intentos++;
		}

		throw new \RuntimeException('No se pudo reservar un código libre de proveedor en ERP/Anita.');
	}

	private function consultarPromaeAnitaConReintento(ApiAnita $apiAnita, string $key): ?object
	{
		for ($intento = 1; $intento <= 3; $intento++) {
			$fila = $this->consultarPromaeAnita($apiAnita, $key);
			if ($fila !== null) {
				return $fila;
			}
			// Confirmación: una lectura vacía puede ser flaky del bridge; reintenta antes de asumir libre.
			usleep(100000);
		}

		return null;
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
			$cuentacontable = 0;
		
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

		$camposExclusionPromae = ProveedorExclusionAnitaSupport::camposPromaeDesdeLineas(
			ProveedorExclusionAnitaSupport::lineasDesdeRequest($data)
		);
		$exclusionretiva = $camposExclusionPromae['exclusionretiva'];
		$fechaexclusionretiva = $camposExclusionPromae['fechaexclusionretiva'];
		$fechainicioexclusionretiva = $camposExclusionPromae['fechainicioexclusionretiva'];
		$exclusionretgan = $camposExclusionPromae['exclusionretgan'];
		$fechaexclusionretgan = $camposExclusionPromae['fechaexclusionretgan'];
		$fechainicioexclusionretgan = $camposExclusionPromae['fechainicioexclusionretgan'];
		$exclusionretib = $camposExclusionPromae['exclusionretib'];
		$fechaexclusionretib = $camposExclusionPromae['fechaexclusionretib'];
		$fechainicioexclusionretib = $camposExclusionPromae['fechainicioexclusionretib'];

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
			$cuentacontableme = 0;
			
		$cuenta = $this->cuentacontableRepository->findPorId($data['cuentacontablecompra_id']);
		if ($cuenta)
			$cuentacontablecompra = $cuenta->codigo;
		else
			$cuentacontablecompra = 0;
		
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
		if ($retiva)
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
		}		
	}

	public function leeProveedor($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ProveedorListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ProveedorListadoFiltros::filtrosVacios();
        }

        $proveedor = $this->model->select('proveedor.id as id',
                                        'proveedor.nombre as nombre',
										'proveedor.fantasia as fantasia',
										'proveedor.nroinscripcion as numerodocumento',
                                        'proveedor.domicilio as domicilio',
										'proveedor.codigo as codigo',
                                        'localidad.nombre as nombrelocalidad',
										'provincia.nombre as nombreprovincia',
										'proveedor.estado as estado',
                                        'proveedor.facturas_apocrifas as facturas_apocrifas',
                                        'proveedor.facturas_apocrifas_consulta_at as facturas_apocrifas_consulta_at')
                                ->leftjoin('localidad', 'localidad.id', 'proveedor.localidad_id')
								->leftjoin('provincia', 'provincia.id', 'proveedor.provincia_id');

        if (ProveedorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ProveedorListadoFiltros::aplicar($proveedor, $filtros);
        }

		$proveedor = $proveedor->orderby('id', 'DESC');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $proveedor = $proveedor->paginate(10);
            } else {
                $proveedor = $proveedor->get();
            }
        } else {
            $proveedor = $proveedor->get();
        }

        return $proveedor;
    }

}
