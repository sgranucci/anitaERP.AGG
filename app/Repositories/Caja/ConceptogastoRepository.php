<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Conceptogasto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ConceptogastoRepository implements ConceptogastoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Conceptogasto $conceptogasto)
    {
        $this->model = $conceptogasto;
    }

    public function all()
    {
        $hay_conceptogasto = Conceptogasto::first();

		if (!$hay_conceptogasto)
			self::sincronizarConAnita();

        return $this->model->with('conceptogasto_cuentacontables')->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $conceptogasto = $this->model->create($data);

        // Graba anita
		self::guardarAnita($data, $conceptogasto->id);

        return $conceptogasto;
    }

    public function update(array $data, $id)
    {
        $conceptogasto = $this->model->findOrFail($id)->update($data);

		// Actualiza anita
		self::actualizarAnita($data, $data['id']);        

        return $conceptogasto;
    }

    public function delete($id)
    {
    	$conceptogasto = $this->model->find($id);

        $conceptogasto = $this->model->destroy($id);

		// Elimina anita
		self::eliminarAnita($id);

		return $conceptogasto;
    }

    public function find($id)
    {
        if (null == $conceptogasto = $this->model->with('conceptogasto_cuentacontables')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $conceptogasto;
    }

    public function findPorId($id)
    {
		$retencionganancia = $this->model->with('conceptogasto_cuentacontables')->where('id', $id)->first();

		return $retencionganancia;
    }

    public function findOrFail($id)
    {
        if (null == $conceptogasto = $this->model->with('conceptogasto_cuentacontables')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $conceptogasto;
    }

    public function leeConceptogasto($consulta)
    {
		$columns = ['conceptogasto.id', 'conceptogasto.nombre'];
        $columnsOut = ['id', 'nombre'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('conceptogasto.id as id',
									'conceptogasto.nombre as nombre')
							->orWhere(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                })	
				->get();								

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaconceptogasto">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'che_ban',
						'campos' => "
                        			coper_concepto as numeroconcepto,
    		                        coper_concepto",
						'tabla' => 'concoper',
                        'orderby' => 'coper_concepto');
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            if ($value->coper_concepto != '0')
                $this->traerRegistroDeAnita($value->coper_concepto);
        }

        $this->traerRegistroDeAnita('0');
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => 'concoper', 
            'sistema' => 'che_ban',
            'campos' => '
			coper_concepto,
    		coper_desc
			',
            'whereArmado' => " WHERE coper_concepto = '".$key."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->coper_desc,
            	];
	
        	$conceptogasto = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'concoper', 
            'acc' => 'insert',
            'sistema' => 'che_ban',
            'campos' => ' 
                coper_concepto,
                coper_desc
				',
            'valores' => " 
				'".$id."', 
                '".$request['nombre']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 
                'tabla' => 'concoper', 
                'sistema' => 'che_ban',
				'valores' => " 
                coper_desc = '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE coper_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'concoper', 
                'sistema' => 'che_ban',
				'whereArmado' => " WHERE coper_concepto = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
	
}
