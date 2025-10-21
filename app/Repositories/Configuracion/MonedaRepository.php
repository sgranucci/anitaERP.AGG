<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Moneda;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class MonedaRepository implements MonedaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'moneda';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'mon_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Moneda $moneda)
    {
        $this->model = $moneda;
    }

    public function all()
    {
        $hay_moneda = Moneda::first();

        if (!$hay_moneda)
			self::sincronizarConAnita();

        return $this->model->orderBy('id','ASC')->get();
    }

    public function allOrdenadoPorId()
    {
        return $this->model->orderBy('id','ASC')->get();
    }

    public function create(array $data)
    {
        $moneda = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $data['codigo']);
    }

    public function update(array $data, $id)
    {
        $moneda = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $moneda;
    }

    public function delete($id)
    {
    	$moneda = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($moneda->codigo);

        $moneda = $this->model->destroy($id);

		return $moneda;
    }

    public function find($id)
    {
        if (null == $moneda = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $moneda;
    }

    public function findPorId($id)
    {
        $moneda = $this->model->where('id', $id)->first();

        return $moneda;
    }

    public function findPorCodigo($codigo)
    {
        $moneda = $this->model->where('codigo', $codigo)->first();

        return $moneda;
    }

    public function findOrFail($id)
    {
        if (null == $moneda = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $moneda;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'shared',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Moneda::all();
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
                mon_codigo,
				mon_desc,
				mon_abreviatura
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Moneda::create([
                "id" => $key,
                "nombre" => $data->mon_desc,
                "abreviatura" => $data->mon_abreviatura,
                "codigo" => $data->mon_codigo
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
						'acc' => 'insert',
                        'sistema' => 'shared',
            			'campos' => ' mon_codigo, mon_desc, mon_abreviatura',
            			'valores' => " '".$id."', 
										'".$request['nombre']."',  
										'".$request['abreviatura']."',
										'".$request['codigo']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
                        'sistema' => 'shared',
						'tabla' => $this->tableAnita, 
						'valores' => 
							" mon_desc = '".$request['nombre']."',
							mon_abreviatura = '".$request['abreviatura']."',
                			mon_codigo =	'".$request['codigo']."'",
						'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                    'sistema' => 'shared',
					'tabla' => $this->tableAnita,
					'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
