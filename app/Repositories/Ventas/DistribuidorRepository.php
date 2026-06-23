<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Distribuidor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class DistribuidorRepository implements DistribuidorRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'distribuidor';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'distr_distribuidor';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Distribuidor $distribuidor)
    {
        $this->model = $distribuidor;
    }

    public function all()
    {
        $hay_distribuidores = Distribuidor::first();

		if (!$hay_distribuidores)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $distribuidor = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $distribuidor = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $distribuidor;
    }

    public function delete($id)
    {
    	$distribuidor = Distribuidor::find($id);
		//
		// Elimina anita
		self::eliminarAnita($distribuidor->codigo);

        $distribuidor = $this->model->destroy($id);

		return $distribuidor;
    }

    public function find($id)
    {
        if (null == $distribuidor = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $distribuidor;
    }

    public function findOrFail($id)
    {
        if (null == $distribuidor = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $distribuidor;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Distribuidor::all();
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
		// Formato El Bierzo
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'ventas',
            'campos' => '
			distr_distribuidor,
    		distr_nombre,
    		distr_porc_com
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->distr_nombre,
				"codigo" => $data->distr_distribuidor,
				"porcentajecomision" => $data->distr_porc_com,
            	];
	
        	$distribuidor = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				distr_distribuidor,
    			distr_nombre,
    			distr_porc_com
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['porcentajecomision']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'valores' => " 
                distr_distribuidor 	                = '".$request['codigo']."',
                distr_nombre 	                = '".$request['nombre']."',
                distr_porc_com 	                = '".$request['porcentajecomision']."' "
					,
				'whereArmado' => " WHERE distr_distribuidor = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE distr_distribuidor = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	// Devuelve ultimo codigo de distribuidors + 1 para agregar nuevos en Anita

	public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $distribuidor = $this->model->newQuery()->where('codigo', $codigo)->first();
        if ($distribuidor) {
            return $distribuidor;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()->where('codigo', $alt)->first();
        }

        return null;
    }

	public function consultaDistribuidor(string $consulta): string
    {
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $consulta = strtoupper(trim($consulta));
        $query = $this->model->newQuery()->select('id', 'nombre', 'codigo');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('id', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('codigo', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->orderBy('codigo')->limit(200)->get();
        $puedeAbrirAbm = can('editar-distribuidor', false) || can('listar-distribuidor', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultadistribuidor">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_distribuidor', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(distr_distribuidor) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
