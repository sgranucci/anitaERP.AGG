<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Impuesto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ImpuestoRepository implements ImpuestoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'impvar';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'impv_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Impuesto $impuesto)
    {
        $this->model = $impuesto;
    }

    public function all()
    {
        $hay_impuesto = Impuesto::first();

        if (!$hay_impuesto)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $impuesto = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);

        return $impuesto;
    }

    public function update(array $data, $id)
    {
        $impuesto = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $impuesto;
    }

    public function delete($id)
    {
    	$impuesto = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($impuesto->codigo);

        $impuesto = $this->model->destroy($id);

		return $impuesto;
    }

    public function find($id)
    {
        return $this->model->with('impuesto_cuentacontables')->find($id);
    }

    public function findPorId($id)
    {
        $impuesto = $this->model->with('impuesto_cuentacontables')->where('id', $id)->first();

        return $impuesto;
    }

    public function findPorCodigo($codigo)
    {
        $impuesto = $this->model->with('impuesto_cuentacontables')->where('codigo', $codigo)->first();

        return $impuesto;
    }

    public function findPorValor($valor)
    {
        $impuesto = $this->model->with('impuesto_cuentacontables')->where('valor', $valor)->first();

        return $impuesto;
    }

    public function findOrFail($id)
    {
        if (null == $impuesto = $this->model->with('impuesto_cuentacontables')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $impuesto;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'shared',
                        'campos' => $this->keyFieldAnita, 
                        'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Impuesto::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
		if ($dataAnita)
		{
        	foreach ($dataAnita as $value) {
            	if (!in_array($value->{$this->keyFieldAnita}, $datosLocalArray)) {
                	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            	}
        	}
		}
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'shared',
            'campos' => '
                impv_codigo,
				impv_desc,
				impv_tasa,
				impv_fecha
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$fechavigencia = date('Y-m-d', strtotime($dataAnita[0]->impv_fecha));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Impuesto::create([
                "id" => $key,
                "nombre" => $data->impv_desc,
                "valor" => $data->impv_tasa,
				"fechavigencia" => $fechavigencia
            ]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$fechavigencia = $request['fechavigencia'];
		$fechavigencia = date('Ymd', strtotime($fechavigencia));

        $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' impv_codigo, impv_desc, impv_tasa, impv_fecha',
            			'valores' => " '".$request['codigo']."', '".$request['nombre']."', '".$request['valor']."', '".$fechavigencia."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $fechavigencia = $request['fechavigencia'];
		$fechavigencia = date('Ymd', strtotime($fechavigencia));

		$data = array( 'acc' => 'update', 
						'tabla' => $this->tableAnita, 
                        'sistema' => 'shared',
						'valores' => " impv_desc = '".$request['nombre']."', impv_tasa = '".$request['valor']."', impv_fecha = '".$fechavigencia."' ", 
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

}
