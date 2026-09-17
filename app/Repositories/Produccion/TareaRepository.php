<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Tarea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TareaRepository implements TareaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'tarea';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'tar_tarea';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tarea $tarea)
    {
        $this->model = $tarea;
    }

    public function all()
    {
        $hay_tareas = Tarea::first();

		if (!$hay_tareas)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $tarea = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $tarea->id);
    }

    public function update(array $data, $id)
    {
        $tarea = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $tarea;
    }

    public function delete($id)
    {
    	$tarea = Tarea::find($id);

        try{
        
            $tarea = $this->model->destroy($id);

        } catch (\Exception $e) 
		{
		    dd($e->getMessage());
			return $e->getMessage();
		}

        return $tarea;
    }

    public function find($id)
    {
        if (null == $tarea = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea;
    }

    public function findOrFail($id)
    {
        if (null == $tarea = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea;
    }

    private function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Tarea::all();
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

    private function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			tar_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->tar_desc,
            	];
	
        	$tarea = $this->model->create($arr_campos);
        }
    }

	private function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				tar_tarea,
    			tar_desc
				',
            'valores' => " 
				'".$id."', 
				'".$request['nombre']."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
                tar_desc 	                = '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE tar_tarea = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE tar_tarea = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

    public function consultaModal(?string $texto): array
    {
        $q = $this->model->newQuery()->orderBy('id');
        $texto = trim((string) $texto);
        if ($texto !== '') {
            $q->where(function ($w) use ($texto) {
                $w->where('nombre', 'like', '%'.$texto.'%');
                if (ctype_digit($texto)) {
                    $w->orWhere('id', (int) $texto);
                }
            });
        }
        $filas = $q->limit(100)->get(['id', 'nombre']);
        $puedeAbrirAbm = can('editar-tareas', false) || can('listar-tareas', false);
        $html = '';
        foreach ($filas as $row) {
            $html .= '<tr>';
            $html .= '<td class="id">'.e($row->id).'</td>';
            $html .= '<td class="nombre">'.e($row->nombre).'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<a class="btn btn-warning btn-sm eligeconsultatarea">Elegir</a>';
            if ($puedeAbrirAbm) {
                $url = route('editar_tarea', [
                    'id' => (int) $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="3" class="text-muted">Sin resultados.</td></tr>';
        }

        return ['data' => $html];
    }

    public function findParaConsulta($id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $row = $this->model->newQuery()->find($id, ['id', 'nombre']);
        if (! $row) {
            return null;
        }

        return ['id' => (int) $row->id, 'nombre' => (string) $row->nombre];
    }

}
