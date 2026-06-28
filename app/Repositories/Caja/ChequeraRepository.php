<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Chequera;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;
use DB;
use Carbon\Carbon;
use Exception;

class ChequeraRepository implements ChequeraRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'cprocheq';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'cproc_nro_chequera';
    private $cuentacajaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Chequera $chequera,
                                CuentacajaRepositoryInterface $cuentacajarepository)
    {
        $this->model = $chequera;
        $this->cuentacajaRepository = $cuentacajarepository;
    }

    public function all()
    {
        $hay_chequera = Chequera::first();

        if (!$hay_chequera) {
            try {
                self::sincronizarConAnita();
            } catch (\Throwable $e) {
                // No bloquear pantalla si la sync inicial falla parcialmente.
            }
        }

        return $this->model->with('cuentacajas')->get();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try 
        {
            $chequera = $this->model->create($data);

            // Graba anita
		    $anita = self::guardarAnita($data);


            DB::commit();

        } catch (\Exception $e) {

            DB::rollback();

            dd($e->getMessage());

            return ['error' => $e->getMessage()];
        }
        return($chequera);
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try 
        {
            $chequera = $this->model->findOrFail($id)->update($data);

            // Actualiza anita
		    $anita = self::actualizarAnita($data, $data['codigo']);


            DB::commit();

        } catch (\Exception $e) {
            
            DB::rollback();

            dd($e->getMessage());
            return ['error' => $e->getMessage()];
        }
        return($chequera);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try 
        {
    	    $chequera = $this->model->find($id);
        		
		    // Elimina anita
		    $anita = self::eliminarAnita($chequera->codigo);

            $chequera = $this->model->destroy($id);


            DB::commit();   

        } catch (\Exception $e) {
            
            DB::rollback();

            return ['error' => $e->getMessage()];
        }
		return $chequera;
    }

    public function find($id)
    {
        if (null == $chequera = $this->model->with('cuentacajas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $chequera;
    }

    public function findOrFail($id)
    {
        if (null == $chequera = $this->model->with('cuentacajas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $chequera;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->where('codigo', $codigo)->with('cuentacajas')->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Chequera::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = ltrim((string) $value->{$this->keyField}, '0');
        }

        foreach ($dataAnita as $value) {
            $codigoAnita = ltrim((string) $value->{$this->keyField}, '0');
            if ($codigoAnita === '' || in_array($codigoAnita, $datosLocalArray, true)) {
                continue;
            }

            try {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
                $datosLocalArray[] = $codigoAnita;
            } catch (\Throwable $e) {
                // Continuar con la siguiente chequera.
            }
        }
    }

    private function resolverCuentacajaIdDesdeAnita($codigoAnita): ?int
    {
        $codigoNorm = ltrim(trim((string) $codigoAnita), '0');
        if ($codigoNorm === '') {
            return null;
        }

        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);
        if ($cuentacaja) {
            return (int) $cuentacaja->id;
        }

        $estado = $this->cuentacajaRepository->traerRegistroDeAnita(
            str_pad($codigoNorm, 8, '0', STR_PAD_LEFT)
        );
        if ($estado !== 'importado') {
            return null;
        }

        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);

        return $cuentacaja ? (int) $cuentacaja->id : null;
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => '
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            $cuentacaja_id = $this->resolverCuentacajaIdDesdeAnita($data->cproc_cuenta ?? '');
            if ($cuentacaja_id === null) {
                return;
            }
            
            $fechaUso = date('d-m-Y', strtotime($data->cproc_fecha_uso));

            $arr_campos = [
                "tipochequera" => 'F',
                "tipocheque" => ($data->cproc_tipo_cheque == 'C' ? 'N' : $data->cproc_tipo_cheque),
                "codigo" => $data->cproc_nro_chequera,
                "cuentacaja_id" => $cuentacaja_id,
                "estado" => $data->cproc_estado,
                "fechauso" => $fechaUso,
                "desdenumerocheque" => $data->cproc_desde_cheque,
                "hastanumerocheque" => $data->cproc_hasta_cheque
                ];

            $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        $cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);

        $codigo = 0;
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;

        if ($request['fechauso'])
            $fechaUso = date('Ymd',strtotime($request['fechauso']));
        else    
            $fechaUso = 0;

        $fechaAlta = Carbon::now()->format('Ymd');

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
				',
            'valores' => " 
				'".str_pad($codigo, 8, "0", STR_PAD_LEFT)."', 
				'".$request['codigo']."',
                '".$fechaAlta."',
                '".$fechaUso."',
				'".$request['desdenumerocheque']."',
                '".$request['hastanumerocheque']."',
                'A',
				'".($request['tipocheque'] == 'C' ? 'N' : $request['tipocheque'])."' "
        );
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);

        $codigo = 0;
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;

        if ($request['fechauso'])
            $fechaUso = date('Ymd',strtotime($request['fechauso']));
        else    
            $fechaUso = 0;

        $fechaAlta = Carbon::now()->format('Ymd');

        $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => " 
                        cproc_cuenta 	                = '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."' ,
                        cproc_nro_chequera              = '".$request['codigo']."' ,
                        cproc_fecha_alta    	        = '".$fechaAlta."' ,
                        cproc_fecha_uso                 = '".$fechaUso."' ,
                        cproc_desde_cheque              = '".$request['desdenumerocheque']."' ,
                        cproc_hasta_cheque              = '".$request['hastanumerocheque']."' ,
                        cproc_estado 	                = '".$request['estado']."' ,
                        cproc_tipo_cheque 	            = '".($request['tipocheque'] == 'C' ? 'N' : $request['tipocheque'])."' "
				,
				'whereArmado' => " WHERE cproc_nro_chequera = '".$request['codigo']."' " );
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE cproc_nro_chequera = '".$id."' " );
        $anita = $apiAnita->apiCallEscritura($data);
        
        return $anita;
	}

}
