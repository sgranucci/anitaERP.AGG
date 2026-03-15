<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex;
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

}
