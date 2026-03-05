<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Exclusion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Proveedor_ExclusionRepository implements Proveedor_ExclusionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Exclusion $proveedor_exclusion)
    {
        $this->model = $proveedor_exclusion;
    }

    public function create(array $data, $id)
    {
		return self::guardarProveedor_Exclusion($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
		return self::guardarProveedor_Exclusion($data, 'update', $id);
    }

    public function delete($proveedor_id, $codigo)
    {
        $proveedor_exclusion = $this->model->where('proveedor_id', $proveedor_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        if (null == $proveedor_exclusion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeProveedorExclusion($proveedor_id)
	{
		$proveedor_exclusion = $this->model->where('proveedor_id', $proveedor_id)->get();

		return $proveedor_exclusion;
	}
	
    public function findOrFail($id)
    {
        if (null == $proveedor_exclusion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarProveedor_Exclusion($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$proveedor_exclusion = $this->model->where('proveedor_id', $id)->get()->pluck('id')->toArray();
			$q_proveedor_exclusion = count($proveedor_exclusion);
		}

		// Graba exclusiones
		if (isset($data['desdefechas']))
		{
			$desdefechas = $data['desdefechas'];
			$hastafechas = $data['hastafechas'];
			$porcentajeexclusiones = $data['porcentajeexclusiones'];
			$tiporetenciones = $data['tiporetenciones'];
			$comentarios = $data['comentarios'];

			if ($funcion == 'update')
			{
				$_id = $proveedor_exclusion;

				// Borra los que sobran
				if ($q_proveedor_exclusion > count($desdefechas))
				{
					for ($d = count($desdefechas); $d < $q_proveedor_exclusion; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_proveedor_exclusion && $i < count($desdefechas); $i++)
				{
					if ($i < count($desdefechas))
					{
						$proveedor_exclusion = $this->model->findOrFail($_id[$i])->update([
									"proveedor_id" => $id,
									"comentario" => $comentarios[$i],
									"tiporetencion" => $tiporetenciones[$i],
									"desdefecha" => $desdefechas[$i],
									"hastafecha" => $hastafechas[$i],
									"porcentajeexclusion" => $porcentajeexclusiones[$i]
									]);
					}
				}
				if ($q_proveedor_exclusion > count($desdefechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_exclusion = $i; $i_exclusion < count($desdefechas); $i_exclusion++)
			{
				//* Valida si se cargo una exclusion
				if ($desdefechas[$i_exclusion] != '') 
				{
					$proveedor_exclusion = $this->model->create([
									"proveedor_id" => $id,
									"comentario" => $comentarios[$i_exclusion],
									"tiporetencion" => $tiporetenciones[$i_exclusion],
									"desdefecha" => $desdefechas[$i_exclusion],
									"hastafecha" => $hastafechas[$i_exclusion],
									"porcentajeexclusion" => $porcentajeexclusiones[$i_exclusion]
									]);
				}
			}
		}
		else
		{
			$proveedor_exclusion = $this->model->where('proveedor_id', $id)->delete();
		}
	}
}
