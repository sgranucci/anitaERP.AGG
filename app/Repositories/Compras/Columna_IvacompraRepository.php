<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Columna_Ivacompra;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Columna_IvacompraRepository implements Columna_IvacompraRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'colivacomp';
    protected $keyField = 'numerocolumna';
    protected $keyFieldAnita = 'coli_columna';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Columna_Ivacompra $columna_ivacompra)
    {
        $this->model = $columna_ivacompra;
    }

    public function all()
    {
        $hay_columna_ivacompra = Columna_Ivacompra::first();

		if (!$hay_columna_ivacompra)
			self::sincronizarConAnita();

        return $this->model->orderBy('numerocolumna','ASC')->get();
    }

    public function create(array $data)
    {
        $columna_ivacompra = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $columna_ivacompra = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['numerocolumna']);

		return $columna_ivacompra;
    }

    public function delete($id)
    {
    	$columna_ivacompra = Columna_Ivacompra::find($id);
		//
		// Elimina anita
		self::eliminarAnita($columna_ivacompra->codigo);

        $columna_ivacompra = $this->model->destroy($id);

		return $columna_ivacompra;
    }

    public function find($id)
    {
        if (null == $columna_ivacompra = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $columna_ivacompra;
    }

    public function findOrFail($id)
    {
        if (null == $columna_ivacompra = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $columna_ivacompra;
    }

    public function findPorNumeroColumna($numerocolumna)
    {
		return $this->model->where('numerocolumna', $numerocolumna)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "
                        			coli_columna as numerocolumna,
    		                        coli_columna",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Columna_Ivacompra::all();
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
            'sistema' => 'compras',
            'campos' => '
			coli_columna,
    		coli_desc,
            coli_desc_columna,
            coli_tipo_dato,
            coli_formula
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->coli_desc,
                "nombrecolumna" => $data->coli_desc_columna,
				"numerocolumna" => $data->coli_columna,
            	];
	
        	$columna_ivacompra = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
                coli_columna,
                coli_desc,
                coli_desc_columna,
                coli_tipo_dato,
                coli_formula
				',
            'valores' => " 
				'".$request['numerocolumna']."', 
                '".$request['nombre']."', 
                '".$request['nombrecolumna']."', 
                '".' '."', 
				'".'D'."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                coli_columna 	                = '".$request['numerocolumna']."',
                coli_desc    	                = '".$request['nombre']."',
                coli_desc_columna               = '".$request['nombrecolumna']."' "
					,
				'whereArmado' => " WHERE coli_columna = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE coli_columna = '".$id."' " );
        $apiAnita->apiCall($data);
	}
	
}
