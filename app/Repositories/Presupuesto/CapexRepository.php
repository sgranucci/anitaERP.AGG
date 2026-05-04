<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Presupuesto;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class CapexRepository implements CapexRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Capex $capex)
    {
        $this->model = $capex;
    }

	public function all()
	{

	}
	
    public function create(array $data)
    {
		$data['codigo'] = Self::ultimoCapex();

		return $this->model->create($data);
    }

    public function createDesdeAnita(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$capex = $this->model->findOrFail($id)->update($data);

		return $capex;
    }

    public function delete($id)
    {
		$capex = $this->model->findOrFail($id);

		if ($capex)
        	$capex = $this->model->destroy($id);

		return $capex;
    }

    public function find($id)
    {
        return $this->model->with("capex_estados")
									->with("capex_partidas")
									->with("capex_archivos")
									->with("empresas")
									->with("centrocostos")
									->find($id);
	}

    public function findOrFail($id)
    {
        if (null == $capex = $this->model->with("capex_estados")
									->with("capex_partidas")
									->with("capex_archivos")
									->with("empresas")
									->with("centrocostos")
									->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $capex;
    }

	public function findPorCodigo($codigo)
	{
		return $this->model->with("empresas")
							->with("presupuestos")
							->with("capex_partidas")
							->with("centrocostos")
							->with("usuarios")
							->where('codigo', $codigo)->first();	
	}

	// Devuelve ultimo numero de capex + 1 (campo codigo)
	private function ultimoCapex()
	{
		$capex = $this->model->select('codigo')->orderBy('id', 'desc')->first();
		
		$numerocapex = 0;
        if ($capex) 
		{
			$numerocapex = $capex->codigo;
			$numerocapex = $numerocapex + 1;
		}
		else	
			$numerocapex = 1;

		return $numerocapex;
	}

	public function consultaCapex($consulta, $empresa_id, $centrocostodestino_id = null)
	{
		ini_set('max_execution_time', '120');
		ini_set('memory_limit', '256M');

		$output = ['data' => ''];
		$empresa_id = (int) $empresa_id;
		if ($empresa_id <= 0) {
			$output['data'] .= '<tr><td colspan="5">Seleccione una empresa en el encabezado.</td></tr>';

			return $output;
		}

		$ultimoPresupuestoId = Presupuesto::query()->max('id');
		if (!$ultimoPresupuestoId) {
			$output['data'] .= '<tr><td colspan="5">No hay presupuestos cargados.</td></tr>';

			return $output;
		}

		$q = $this->model->query()
			->where('empresa_id', $empresa_id)
			->where('presupuesto_id', $ultimoPresupuestoId)
			->where('estado', 'ACTIVO');

		$centrocostodestino_id = (int) $centrocostodestino_id;
		if ($centrocostodestino_id > 0) {
			$q->where('centrocosto_id', $centrocostodestino_id);
		}

		$consulta = is_string($consulta) ? trim($consulta) : '';

		if ($consulta !== '') {
			$c = $consulta;
			$q->where(function ($query) use ($c) {
				$query->where('codigo', 'like', '%'.$c.'%')
					->orWhere('detalle', 'like', '%'.$c.'%')
					->orWhere('nombre', 'like', '%'.$c.'%')
					->orWhere('codigoproyecto', 'like', '%'.$c.'%');
				if (ctype_digit($c)) {
					$query->orWhere('id', (int) $c);
				}
			});
		}

		$data = $q->orderBy('codigo')->limit(200)->get();
		if ($data->isEmpty()) {
			$output['data'] .= '<tr><td colspan="5">Sin resultados para el último presupuesto y esta empresa.</td></tr>';

			return $output;
		}

		foreach ($data as $row) {
			$nombre = $row->nombre ?? '';
			$output['data'] .= '<tr>';
			$output['data'] .= '<td class="id">'.e($row->id).'</td>';
			$output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
			$output['data'] .= '<td class="detalle">'.e($row->detalle).'</td>';
			$output['data'] .= '<td class="concepto">'.e($nombre).'</td>';
			$output['data'] .= '<td>'
				.'<a class="btn btn-warning btn-sm eligeconsultacapex">Elegir</a> '
				.'<a class="btn btn-info btn-sm" href="'.e(url('presupuesto/capex/'.$row->id.'/editar')).'" target="_blank" rel="noopener">Consultar</a>'
				.'</td>';
			$output['data'] .= '</tr>';
		}

		return $output;
	}
}
