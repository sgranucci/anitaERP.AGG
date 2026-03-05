<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Mediopago;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class MediopagoRepository implements MediopagoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'tctes';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'tctes_clave';

	private $cuentacajaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Mediopago $mediopago,
								CuentacajaRepositoryInterface $cuentacajarepository)
    {
        $this->model = $mediopago;
		$this->cuentacajaRepository = $cuentacajarepository;
    }

    public function all()
    {
        $hay_mediopagos = Mediopago::first();

		if (!$hay_mediopagos)
			self::sincronizarConAnita();

        return $this->model->with('empresas')->with('cuentacajas')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $mediopago = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $mediopago = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $mediopago;
    }

    public function delete($id)
    {
    	$mediopago = Mediopago::find($id);
		//
		// Elimina anita
		self::eliminarAnita($mediopago->codigo);

        $mediopago = $this->model->destroy($id);

		return $mediopago;
    }

    public function find($id)
    {
        if (null == $mediopago = $this->model->with('empresas')
											->with('cuentacajas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $mediopago;
    }

    public function findOrFail($id)
    {
        if (null == $mediopago = $this->model->with('empresas')
										->with('cuentacajas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $mediopago;
    }

	public function findPorCodigo($codigo)
    {
        if (null == $mediopago = $this->model->with('empresas')
										->with('cuentacajas')->where('codigo', $codigo)->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $mediopago;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Mediopago::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => '
			tctes_clave,
    		tctes_desc,
    		tctes_imputacion,
    		tctes_numero,
    		tctes_predefinido,
    		tctes_tipo_imp,
    		tctes_signo
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			if ($data->tctes_imputacion !== "00000000")
			{
				// Busca la cuenta de caja
				$cuentacaja = $this->cuentacajaRepository->findPorCodigo(ltrim($data->tctes_imputacion,'0'));

				if ($cuentacaja)
				{
					$arr_campos = [
						"nombre" => $data->tctes_desc,
						"codigo" => $data->tctes_clave,
						"cuentacaja_id" => $cuentacaja->id,
						"empresa_id" => $cuentacaja->empresa_id,
						];

					$mediopago = $this->model->create($arr_campos);
				}
			}
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		// Busca la cuenta de caja
		$cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);
		if ($cuentacaja)
		{
			$imputacion = $cuentacaja->codigo;
			if ($cuentacaja->tipocuenta == 'R')
				$tipoImputacion = 'R';
			else	
			{
				if ($cuentacaja->banco_id > 0)
					$tipoImputacion = 'B';
				else	
					$tipoImputacion = 'V';
			}
		}
		else
		{	
			$imputacion = ' ';
			$tipoImputacion = 'V';
		}

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
				tctes_clave,
				tctes_desc,
				tctes_imputacion,
				tctes_numero,
				tctes_predefinido,
				tctes_tipo_imp,
				tctes_signo
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$imputacion."',
				'".'000'."',
				'".'N'."',
				'".$tipoImputacion."',
				'".'S'."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		// Busca la cuenta de caja
		$cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);
		if ($cuentacaja)
			$imputacion = $cuentacaja->codigo;
		else
			$imputacion = ' ';

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => " 
                tctes_clave 	            = '".$request['codigo']."',
                tctes_desc 	                = '".$request['nombre']."',
                tctes_imputacion            = '".$imputacion."' "
					,
				'whereArmado' => " WHERE tctes_clave = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE banm_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
