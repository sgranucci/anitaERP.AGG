<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Pais_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use App\Repositories\Uif\Actividad_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Archivo_UifRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\ApiAnita;
use Auth;
use DB;

class Cliente_UifRepository implements Cliente_UifRepositoryInterface
{
    protected $model;
	protected $cliente_premio_uifRepository;
	protected $cliente_archivo_uifRepository;
	protected $tipodocumentoRepository;
	protected $localidad_uifRepository;
	protected $pais_uifRepository;
	protected $provincia_uifRepository;
	protected $actividad_uifRepository;
    protected $tableAnita = 'clientes_uif';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'inroclienteid';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Uif $cliente_uif,
								Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository,
								Cliente_Archivo_UifRepositoryInterface $cliente_archivo_uifrepository,
								TipodocumentoRepositoryInterface $tipodocumentorepository,
								Localidad_UifRepositoryInterface $localidad_uifrepository,
								Pais_UifRepositoryInterface $pais_uifrepository,
								Provincia_UifRepositoryInterface $provincia_uifrepository,
								Actividad_UifRepositoryInterface $actividad_uifrepository,
								)
    {
        $this->model = $cliente_uif;
		$this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
		$this->cliente_archivo_uifRepository = $cliente_archivo_uifrepository;
		$this->tipodocumentoRepository = $tipodocumentorepository;
		$this->localidad_uifRepository = $localidad_uifrepository;
		$this->pais_uifRepository = $pais_uifrepository;
		$this->provincia_uifRepository = $provincia_uifrepository;
		$this->actividad_uifRepository = $actividad_uifrepository;
    }

    public function leeCliente_Uif($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $cliente_uifs = $this->model->select('cliente_uif.id as id',
                                        'cliente_uif.nombre as nombre',
                                        'tipodocumento.abreviatura as abreviaturatipodocumento',
                                        'cliente_uif.numerodocumento as numerodocumento',
                                        'cliente_uif.domicilio as domicilio',
                                        'localidad_uif.nombre as nombrelocalidad',
                                        'provincia_uif.nombre as nombreprovincia',
										'pais_uif.nombre as nombrepais',
										'cliente_uif.telefono as telefono',
                                        'cliente_uif.email as email')
                                ->join('tipodocumento', 'tipodocumento.id', '=', 'cliente_uif.tipodocumento_id')
                                ->join('localidad_uif', 'localidad_uif.id', '=', 'cliente_uif.localidad_uif_id')
                                ->join('provincia_uif', 'provincia_uif.id', '=', 'cliente_uif.provincia_uif_id')
								->join('pais_uif', 'pais_uif.id', '=', 'cliente_uif.pais_uif_id')
								->where('cliente_uif.deleted_at', null)
                                ->where('cliente_uif.id', $busqueda)
                                ->orWhere('tipodocumento.abreviatura', 'like', '%'.$busqueda.'%')
                                ->orWhere('cliente_uif.numerodocumento', 'like', '%'.$busqueda.'%')  
								->orWhere('cliente_uif.domicilio', 'like', '%'.$busqueda.'%')
								->orWhere('localidad_uif.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('provincia_uif.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('pais_uif.nombre', 'like', '%'.$busqueda.'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $cliente_uifs = $cliente_uifs->paginate(10);
            else
                $cliente_uifs = $cliente_uifs->get();
        }
        else
            $cliente_uifs = $cliente_uifs->get();

        return $cliente_uifs;
    }

    public function create(array $data)
    {
		$data['usuario_id'] = Auth::user()->id;
		
		$cliente_uif = $this->model->create($data);

		return $cliente_uif;
    }

    public function update(array $data, $id)
    {
		$data['usuario_id'] = Auth::user()->id;
		
		$cliente_uif = $this->model->findOrFail($id)->update($data);

		return $cliente_uif;
    }

    public function delete($id)
    {
		$cliente_uif = $this->model->findOrFail($id);

		if ($cliente_uif)
        	$cliente_uif = $this->model->destroy($id);

		return $cliente_uif;
    }

    public function find($id)
    {
        if (null == $cliente_uif = $this->model->with("cliente_archivos_uif")
									->with("cliente_premios_uif")
									->with("cliente_riesgos_uif")
									->with("provincia_nacimientos")
									->with("localidad_nacimientos")
									->with("localidades_uif")
									->with("provincias_uif")
									->with("peps_uif")
									->with("sos_uif")
									->with("actividades_uif")
									->with("estadociviles_uif")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_uif = $this->model->with("cliente_archivos_uif")
												->with("cliente_premios_uif")
												->with("cliente_riesgos_uif")
												->with("provincia_nacimientos")
												->with("localidad_nacimientos")
												->with("localidades_uif")
												->with("provincias_uif")
												->with("peps_uif")
												->with("sos_uif")
												->with("actividades_uif")
												->with("estadociviles_uif")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $cliente_uif;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'base_admin',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->tableAnita );

        $dataAnita = json_decode($apiAnita->apiCall($data));
        foreach ($dataAnita as $value) {
			//if ($value->{$this->keyFieldAnita} == 4453)
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    public function traerRegistroDeAnita($key){
		ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'base_admin',
            'campos' => '
			    inroclienteid,
				ctipodocumento,
				inrodocumento,
				ccuit,
				cnombre,
				ifechanac,
				ilocalidadnac,
				ipaisnac,
				csexo,
				cestadocivil,
				cdomicilio,
				cpiso,
				cdepto,
				clocalidad,
				ccodigopostal,
				ctelefono,
				cemail,
				iprovincia,
				ipais,
				iprofesion,
				fpremio,
				cmoneda,
				cdescpremio,
				ifechaentrega,
				cobservfisicas,
				ifechaalta,
				choraalta,
				iusuarioalta,
				cestado,
				ifechabaja,
				iusuariobaja,
				ifechaultmodif,
				choraultmodif,
				iusuarioultmodif,
				ilocalidad,
				ipep,
				iparaiso,
				iexterior,
				ifechafirmapep,
				ifeconfirmapep,
				ifeinformepep,
				ifeinformenosis,
				ifevtodni,
				cso,
				cactividadso,
				ccumplenormativaso,
				criesgo,
				inivelsocecon,
				cdecljur,
				ifevtoactividad
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0)
        {
            $data = $dataAnita[0];

			// Busca tipo de documento
			$tipodocumento = $this->tipodocumentoRepository->findPorAbreviatura($data->ctipodocumento);

			$tipodocumento_id = 1;
			if ($tipodocumento)
				$tipodocumento_id = $tipodocumento->id;

			// Lee la localidad de nacimiento
			try {
				$localidad = $this->localidad_uifRepository->findPorCodigo($data->ilocalidadnac);

				$localidadNacimiento_id = null;

				if ($localidad)
					$localidadNacimiento_id = $localidad->id;
			} catch (Exception $e) {
				$localidadNacimiento_id = null;
			}

			// Lee pais de nacimiento
			try {
				$pais = $this->pais_uifRepository->findPorCodigo($data->ipaisnac);

				$paisNacimiento_id = null;
				if ($pais)
					$paisNacimiento_id = $pais->id;
			} catch (Exception $e) {
				$paisNacimiento_id = null;
			}

			// Lee la localidad
			try {
				$localidad = $this->localidad_uifRepository->findPorCodigo($data->ilocalidad);

				$localidad_id = null;
				if ($localidad)
					$localidad_id = $localidad->id;
			} catch (Exception $e) {
				$localidad_id = 337;
			}

			// Lee la provincia
			try {
				$provincia = $this->provincia_uifRepository->findPorCodigo($data->iprovincia);

				$provincia_id = null;
				if ($provincia)
					$provincia_id = $provincia->id;
			} catch (Exception $e) {
				$provincia_id = 26;
			}
			// Lee pais 
			try {
				$pais = $this->pais_uifRepository->findPorCodigo($data->ipais);

				$pais_id = null;
				if ($pais)
					$pais_id = $pais->id;
			} catch (Exception $e) {
				$pais_id = 257;
			}

			// Lee actividad 
			try {
				$actividad = $this->actividad_uifRepository->find($data->iprofesion);

				$actividad_id = null;
				if ($actividad)
					$actividad_id = $actividad->id;
			} catch (Exception $e) {
				$actividad_id = 1;
			}

			$estadoCivil_id = 1;
			switch($data->cestadocivil)
			{
				case 'S':
					$estadoCivil_id = 1;
					break;
				case 'C':
					$estadoCivil_id = 2;
					break;
				case 'D':
					$estadoCivil_id = 3;
					break;
				case 'V':
					$estadoCivil_id = 4;
					break;
				case 'E':
					$estadoCivil_id = 5;
					break;
			}

			switch($data->ipep)
			{
				case 1:
					$pep_id = 2;
					break;
				default:
					$pep_id = 1;
					break;
			}
			
			switch($data->iparaiso)
			{
				case 1:
					$resideParaisoFiscal = 'S';
					break;
				default:
					$resideParaisoFiscal = 'N';
					break;
			}

			switch($data->iexterior)
			{
				case 1:
					$resideExterior = 'S';
					break;
				default:
					$resideExterior = 'N';
					break;
			}			

			switch($data->cdecljur)
			{
				case '1':
					$firmoDeclaracionJurada = 'S';
					break;
				default:
					$firmoDeclaracionJurada = 'N';
			}

			// Lee SO
			switch($data->cso)
			{
				case '1':
					$so_id = 2;
					break;
				default:
					$so_id = 1;
					break;
			}

			// Cumple normativa de sujeto obligado
			switch($data->ccumplenormativaso)
			{
				case '1':
					$cumpleNormativaSo = 'S';
					break;
				default:
					$cumpleNormativaSo = 'N';
					break;
			}

			$riesgoPep = 'BAJO';
			switch($data->criesgo)
			{
				case 'B':
					$riesgoPep = 'BAJO';
					break;
				case 'M':
					$riesgoPep = 'MEDIO';
					break;
				case 'A':
					$riesgoPep = 'ALTO';
					break;
			}

			// Lee nivel socioeconomico 
			$nivelsocioeconomico_id = 8;
			
			switch($data->inivelsocecon)
			{
				case 1:
					$nivelsocioeconomico_id = 1;
					break;
				case 2:
					$nivelsocioeconomico_id = 2;
					break;
				case 3:
					$nivelsocioeconomico_id = 3;
					break;
				case 4:
					$nivelsocioeconomico_id = 4;
					break;
				case 5:
					$nivelsocioeconomico_id = 6;
					break;					
				case 6:
					$nivelsocioeconomico_id = 7;
					break;
				case 7:
					$nivelsocioeconomico_id = 5;
					break;					
			}

			$sexo = '';
			switch($data->csexo)
			{
				case 'M':
					$sexo = 'MASCULINO';
					break;
				case 'F':
					$sexo = 'FEMENINO';
					break;
			}

			$inroclienteid = $data->inroclienteid;

//dd($data);
			$cliente_uif = $this->model->create([
                "nombre" => $data->cnombre,
                "tipodocumento_id" => $tipodocumento_id,
                "numerodocumento" => $data->inrodocumento,
                "cuit" => $data->ccuit,
            	"fechanacimiento" => $data->ifechanac,
				"localidadnacimiento_id" => $localidadNacimiento_id,
				"paisnacimiento_id" => $paisNacimiento_id,
				"sexo" => $sexo,
				"estadocivil_uif_id" => $estadoCivil_id,
				"domicilio" => $data->cdomicilio,
				"piso" => $data->cpiso,
				"departamento" => $data->cdepto,
				"localidad_uif_id" => $localidad_id,
				"codigopostal" => $data->ccodigopostal,
				"provincia_uif_id" => $provincia_id,
				"pais_uif_id" => $pais_id,
				"telefono" => $data->ctelefono,
				"email" => $data->cemail,
				"actividad_uif_id" => $actividad_id,
				"estado" => $data->cestado,
				"pep_uif_id" => $pep_id,
				"resideparaisofiscal" => $resideParaisoFiscal,
				"resideexterior" => $resideExterior,
				"fechafirmapep" => $data->ifechafirmapep,
				"fechaconfirmapep" => $data->ifeconfirmapep,
				"fechainformepep" => $data->ifeinformepep,
				"fechainformenosis" => $data->ifeinformenosis,
				"fechavencimientodni" => $data->ifevtodni,
				"fechavencimientoactividad" => $data->ifevtoactividad,
				"firmodeclaracionjurada" => $firmoDeclaracionJurada,
				"so_uif_id" => $so_id,
				"cumplenormativaso" => $cumpleNormativaSo,
				"riesgopep" => $riesgoPep,
				"nivelsocioeconomico_uif_id" => $nivelsocioeconomico_id,
				"usuario_id" => Auth::user()->id
            ]);

			// Lee los premios
			$apiAnita = new ApiAnita();
			$data = array( 
				'acc' => 'list', 'tabla' => 'premios_uif', 
				'sistema' => 'base_admin',
				'campos' => '
					inropremioid,
					inroclienteid,
					ctipodocumento,
					inrodocumento,
					ifechaentrega,
					fpremio,
					cmoneda,
					cdescpremio,
					ifechaalta,
					choraalta,
					iusuarioalta,
					ifechaultmodif,
					choraultmodif,
					iusuarioultmodif,
					isupervisoralta ,
					choraentrega,
					cnroticket,
					cposicion,
					ifechatito, 
					ctipomov,
					cmediopago,
					crecibo_pago,
					cextfoto
				' , 
            	'whereArmado' => " WHERE inroclienteid = '".$inroclienteid."' " 
        	);
        	$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach($dataAnita as $data)
			{
				$data = $dataAnita[0];

				$juego_id = 1;
				$mediopago_id = 1;

				if ($data->ifechaentrega < 20000000)
					$fechaEntrega = '01-01-2001';
				else
					$fechaEntrega = substr($data->ifechaentrega,6,2).'-'.substr($data->ifechaentrega,4,2).'-'.substr($data->ifechaentrega,0,4);

				$horaEntrega = $data->choraentrega.':00';

				if (!validarHora($horaEntrega))
					$horaEntrega = '01:00';

				$this->cliente_premio_uifRepository->createUnique([
					'cliente_uif_id' => $cliente_uif->id,
					'sala_id' => 1,
					'juego_uif_id' => $juego_id,
					'fechaentrega' => $fechaEntrega.' '.$horaEntrega,
					'detalle' => $data->cdescpremio,
					'monto' => $data->fpremio,
					'moneda_id' => 1,
					'posicion' => $data->cposicion,
					'numerotito' => $data->cnroticket,
					'fechatito' => $data->ifechatito,
					'mediopago_id' => $mediopago_id,
					'piderecibopago' => $data->crecibo_pago,
					'creousuario_id' => Auth::user()->id
				]);
			}

			// Lee los archivos
			$apiAnita = new ApiAnita();
			$data = array( 
				'acc' => 'list', 'tabla' => 'clientes_uif_arch', 
				'sistema' => 'base_admin',
				'campos' => '
					id,
					cliente_id,
					nombrearchivo
				' , 
            	'whereArmado' => " WHERE cliente_id = '".$inroclienteid."' " 
        	);
        	$dataAnita = json_decode($apiAnita->apiCall($data));

			foreach($dataAnita as $data)
			{
				$this->cliente_archivo_uifRepository->createUnique($cliente_uif->id,
					$data->nombrearchivo);
			}
        }
    }

}
