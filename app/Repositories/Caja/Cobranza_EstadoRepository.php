<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cobranza_Estado;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cobranza_EstadoRepository implements Cobranza_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cobranza_Estado $cobranza_estado)
    {
        $this->model = $cobranza_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarCobranza_Estado($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cobranza_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCobranza_Estado($data, 'update', $id);
    }

    public function delete($cobranza_id, $codigo)
    {
        return EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('cobranza_id', $cobranza_id)
        );
    }

    public function find($id)
    {
        if (null == $cobranza_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_estado;
    }

    public function findOrFail($id)
    {
        if (null == $cobranza_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_estado;
    }

	private function guardarCobranza_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cobranza_estado = $this->model->where('cobranza_id', $id)->get()->pluck('id')->toArray();
			$q_cobranza_estado = count($cobranza_estado);
		}

		// Graba estados siempre agregando los nuevos
		if (isset($data))
		{
			$fechas = $data['fechas'];
			$estados = $data['estados'];
			$observaciones = $data['observacionestados'];
			$usuario_ids = $data['usuario_ids'];

			for ($i_movimiento = 0; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$cobranza_estado = $this->model->create([
						"cobranza_id" => $id,
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
			$cobranza_estado = EloquentAuditDeleteSupport::each(
				$this->model->newQuery()->where('cobranza_id', $id)
			);
		}

		return $cobranza_estado;
	}

	public function leeHistoriaCobranza($cobranza_id)
	{
		return $this->model->select('id',
							'cobranza_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion',
							'created_at')
					->where('cobranza_id', $cobranza_id)
					->with('usuarios')
					->get();
	}	
}
