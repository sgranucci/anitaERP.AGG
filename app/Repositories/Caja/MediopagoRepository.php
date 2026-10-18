<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Mediopago;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Illuminate\Support\Facades\Log;

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

        return $this->model->with('empresas')->with('cuentacajas')->orderBy('nombre', 'ASC')->get();
    }

    public function create(array $data)
    {
        throw new \BadMethodCallException('Los medios de pago se gestionan por sincronización con Anita.');
    }

    public function update(array $data, $id)
    {
        throw new \BadMethodCallException('Los medios de pago se gestionan por sincronización con Anita.');
    }

    public function delete($id)
    {
        throw new \BadMethodCallException('Los medios de pago se gestionan por sincronización con Anita.');
    }

    public function find($id)
    {
        return $this->model->with('empresas')->with('cuentacajas')->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $mediopago = $this->model->with('empresas')->with('cuentacajas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $mediopago;
    }

	public function findPorCodigo($codigo)
    {
        return $this->model->with('empresas')->with('cuentacajas')->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));

        if ($parsed['error_lectura'] !== null) {
            try {
                Log::warning('mediopago.sync_anita', [
                    'mensaje' => $parsed['error_lectura'],
                    'tabla' => $this->tableAnita,
                ]);
            } catch (\Throwable $e) {
                // ignore
            }

            return;
        }

        if ($parsed['filas'] === []) {
            return;
        }

        $datosLocalArray = Mediopago::query()
            ->pluck($this->keyField)
            ->map(static fn ($c) => trim((string) $c))
            ->filter(static fn ($c) => $c !== '')
            ->values()
            ->all();

        foreach ($parsed['filas'] as $value) {
            $clave = trim((string) ($value->{$this->keyField} ?? ''));
            if ($clave === '') {
                continue;
            }
            if (! in_array($clave, $datosLocalArray, true)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $key = trim((string) $key);
        if ($key === '') {
            return;
        }
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
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));

        if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
            return;
        }

        $data = $parsed['filas'][0];
        $clave = trim((string) ($data->tctes_clave ?? ''));
        $imputacion = trim((string) ($data->tctes_imputacion ?? ''));

        if ($clave === '' || $imputacion === '' || $imputacion === '00000000') {
            return;
        }

        // No ltrim ciegos: Crown usa códigos con "/" o espacios (071080/1, 00102 eu).
        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($imputacion);
        if (! $cuentacaja) {
            return;
        }

        if ($this->model->where('codigo', $clave)->exists()) {
            return;
        }

        $this->model->create([
            'nombre' => $data->tctes_desc,
            'codigo' => $clave,
            'cuentacaja_id' => $cuentacaja->id,
            'empresa_id' => $cuentacaja->empresa_id,
        ]);
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
        $apiAnita->apiCallEscritura($data);
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
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE banm_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
