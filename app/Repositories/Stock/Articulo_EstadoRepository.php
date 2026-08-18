<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Estado;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Articulo_EstadoRepository implements Articulo_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Estado $articulo_estado)
    {
        $this->model = $articulo_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarArticulo_Estado($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$articulo_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarArticulo_Estado($data, 'update', $id);
    }

    public function delete($articulo_id, $codigo)
    {
        return EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('articulo_id', $articulo_id)
        );
    }

    public function find($id)
    {
        if (null == $articulo_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_estado;
    }

    public function findOrFail($id)
    {
        if (null == $articulo_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo_estado;
    }

	private function guardarArticulo_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$articulo_estado = $this->model->where('articulo_id', $id)->get()->pluck('id')->toArray();
			$q_articulo_estado = count($articulo_estado);
		}

		// Graba estados siempre agregando los nuevos
		if (isset($data['estadofechas']))
		{
			$fechas = $data['estadofechas'];
			$estados = $data['estados'];
			$observaciones = $data['estadoobservaciones'];
			$usuario_ids = $data['estadousuarios'];

			if ($funcion == 'update')
			{
				$_id = $articulo_estado;

				// Borra los que sobran
				if ($q_articulo_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_articulo_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_articulo_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						if ($fechas[$i] != '') 
						{
							$articulo_estado = $this->model->findOrFail($_id[$i])->update([
										"articulo_id" => $id,
										"fecha" => $fechas[$i],
										"estado" => $estados[$i],
										"usuario_id" => $usuario_ids[$i],
										"observacion" => $observaciones[$i]
										]);
						}
					}
				}
				if ($q_articulo_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$articulo_estado = $this->model->create([
						"articulo_id" => $id,
						"fecha" => $fechas[$i_movimiento],
						"estado" => $estados[$i_movimiento],
						"usuario_id" => $usuario_ids[$i_movimiento],
						"observacion" => $observaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$articulo_estado = EloquentAuditDeleteSupport::each(
				$this->model->newQuery()->where('articulo_id', $id)
			);
		}

		return $articulo_estado;
	}

	public function leeHistoriaArticulo($articulo_id)
	{
		return $this->model->select('id',
							'articulo_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion',
							'created_at')
					->where('articulo_id', $articulo_id)
					->with('usuarios')
					->get();
	}	
}
