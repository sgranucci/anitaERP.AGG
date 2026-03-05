<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Idioma;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use App\ApiAnita;

class IdiomaRepository implements IdiomaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'idioma';
    protected $keyField = 'codigo';
	protected $keyFieldAnita = 'idio_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Idioma $tipoempresa)
    {
        $this->model = $tipoempresa;
    }

    public function all()
    {
        $idioma = $this->model->get();

		if ($idioma->isEmpty())
		{
        	self::sincronizarConAnita();

			$idioma = $this->model->orderBy('nombre','ASC')->get();
		}

        return $idioma;
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $idioma = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data);

        return $idioma;
    }

    public function update(array $data, $id)
    {
        $idioma = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $id);

        return $idioma;
    }

    public function delete($id)
    {
    	$idioma = $this->model->find($id);
		$codigo = $idioma->codigo;

        $idioma = $this->model->destroy($id);
        
        self::eliminarAnita($codigo);

		return $idioma;
    }

    public function find($id)
    {
        if (null == $idioma = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $idioma;
    }

    public function findOrFail($id)
    {
        if (null == $idioma = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $idioma;
    }

    public function findPorId($id)
    {
		return $this->model->where('id', $id)->first();
    }

    public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(){

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'receptivo',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'orderBy' => $this->keyField,
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));
        $datosLocal = $this->model->get();
        $datosLocalArray = [];

        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'receptivo',
            'campos' => '
                idio_codigo,
				idio_desc,
				idio_abrev
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		if (count($dataAnita) > 0) 
		{
            $data = $dataAnita[0];

			// Crea registro 
            $this->model->create([
                'id' => $key,
                'nombre' => $data->idio_desc,
				'abreviatura' => $data->idio_abrev,
                'codigo' => $data->idio_codigo
            ]);
        }
    }

	public function guardarAnita($request) {

        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
			'sistema' => 'receptivo',
            'campos' => '
                idio_codigo,
				idio_desc,
                idio_abrev
					',
            'valores' => " 
						'".$request['codigo']."', 
						'".$request['nombre']."',
						'".$request['abreviatura']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {

        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'sistema' => 'receptivo',
            	'valores' => " 
				            idio_codigo = '".$request['codigo']."',
							idio_desc = '".$request['nombre']."',
							idio_abrev = '".$request['abreviatura']."'
							", 
            	'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$request['codigo']."' " 
				);

        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
			'sistema' => 'receptivo',
			'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$id."' " );
        $apiAnita->apiCall($data);
	}    

	// Devuelve ultimo codigo de retenciones de iva + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'sistema' => 'receptivo',
				'tabla' => $this->tableAnita, 
				'campos' => " max(idio_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (isset($dataAnita)) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
		else	
			$codigo = 1;
	}
		
}
