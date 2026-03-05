<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Cuentacontable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Articulo_CuentacontableRepository implements Articulo_CuentacontableRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Cuentacontable $articulo_cuentacontable)
    {
        $this->model = $articulo_cuentacontable;
    }

    public function all()
    {
        $articulo_cuentacontable = $this->model->get();

		return $articulo_cuentacontable;
    }

    public function leePorArticulo($articulo_id, $empresa_id = null)
    {
        if ($empresa_id)
    	    $articulo_cuentacontable = $this->model
                                        ->where('empresa_id', $empresa_id)
                                        ->where('articulo_id', $articulo_id)->get();
        else
            $articulo_cuentacontable = $this->model->where('articulo_id', $articulo_id)->get();

		return $articulo_cuentacontable;
    }

    public function create(array $data, $id)
    {
		return self::guardarArticulo_Cuentacontable($data, 'create', $id);
    }

    public function createUnique(array $data)
    {
        $articulo_cuentacontable = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarArticulo_Cuentacontable($data, 'update', $id);
    }

    public function delete($id)
    {
    	$articulo_cuentacontable = $this->model->find($id);

        $articulo_cuentacontable = $this->model->destroy($id);

		return $articulo_cuentacontable;
    }
 
    public function deletePorArticulo($articulo_id)
    {
    	$articulo_cuentacontable = $this->model->where('articulo_id', $articulo_id)->delete();

		return $articulo_cuentacontable;
    }

    public function find($id)
    {
        if (null == $articulo_cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $articulo_cuentacontable = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_cuentacontable;
    }

	private function guardarArticulo_Cuentacontable($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$articulo_cuentacontable = $this->model->where('articulo_id', $id)->get()->pluck('id')->toArray();
			$q_articulo_cuentacontable = count($articulo_cuentacontable);
		}
		// Graba retenciones
		if (isset($data['empresa_ids']))
		{
			$empresa_ids = $data['empresa_ids'];
			$cuentacontable_ids = $data['cuentacontable_ids'];
			$tipoimputaciones = $data['tipoimputaciones'];
			$creousuario_cuentacontable_ids = $data['creousuario_cuentacontable_ids'];
			if ($funcion == 'update')
			{
				$_id = $articulo_cuentacontable;

				// Borra los que sobran
				if ($q_articulo_cuentacontable > count($empresa_ids))
				{
					for ($d = count($empresa_ids); $d < $q_articulo_cuentacontable; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_articulo_cuentacontable && $i < count($empresa_ids); $i++)
				{
					if ($i < count($empresa_ids))
					{
						$articulo_cuentacontable = $this->model->findOrFail($_id[$i])->update([
									"articulo_id" => $id,
									"empresa_id" => $empresa_ids[$i],
									"cuentacontable_id" => $cuentacontable_ids[$i],
									"tipoimputacion" => $tipoimputaciones[$i],
                                    'creousuario_id' => auth()->id()
									]);
					}
				}
				if ($q_articulo_cuentacontable > count($empresa_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($empresa_ids); $i_movimiento++)
			{
				if ($empresa_ids[$i_movimiento] != '') 
				{
					$articulo_cuentacontable = $this->model->create([
						"articulo_id" => $id,
						"empresa_id" => $empresa_ids[$i_movimiento],
						"cuentacontable_id" => $cuentacontable_ids[$i_movimiento],
						"tipoimputacion" => $tipoimputaciones[$i_movimiento],
                        'creousuario_id' => $creousuario_cuentacontable_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$articulo_cuentacontable = $this->model->where('articulo_id', $id)->delete();
		}
		return $articulo_cuentacontable;
	}    

}
