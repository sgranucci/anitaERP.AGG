<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class PartidagastoRepository implements PartidagastoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Partidagasto $partidagasto)
    {
        $this->model = $partidagasto;
    }

	public function all()
	{

	}
	
    public function create(array $data)
    {
		$data['codigo'] = Self::ultimoPartidagasto();

		return $this->model->create($data);
    }

    public function createDesdeAnita(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$partidagasto = $this->model->findOrFail($id)->update($data);

		return $partidagasto;
    }

    public function delete($id)
    {
		$partidagasto = $this->model->findOrFail($id);

		if ($partidagasto)
        	$partidagasto = $this->model->destroy($id);

		return $partidagasto;
    }

    public function find($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")
									->with("centrocostos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return($partidagasto);
	}

    public function findOrFail($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")									
									->with("centrocostos")
									->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $partidagasto;
    }

	// Devuelve ultimo numero de partidagasto + 1 (campo codigo)
	private function ultimoPartidagasto()
	{
		$partidagasto = $this->model->select('codigo')->orderBy('id', 'desc')->first();
		
		$numeropartidagasto = 0;
        if ($partidagasto) 
		{
			$numeropartidagasto = $partidagasto->codigo;
			$numeropartidagasto = $numeropartidagasto + 1;
		}
		else	
			$numeropartidagasto = 1;

		return $numeropartidagasto;
	}	

}
