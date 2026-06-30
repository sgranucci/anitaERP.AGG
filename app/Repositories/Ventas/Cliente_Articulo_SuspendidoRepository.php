<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Articulo_Suspendido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_Articulo_SuspendidoRepository implements Cliente_Articulo_SuspendidoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Articulo_Suspendido $cliente_articulo_suspendido)
    {
        $this->model = $cliente_articulo_suspendido;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Articulo_Suspendido($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_articulo_suspendido = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Articulo_Suspendido($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_articulo_suspendido = $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_articulo_suspendido = $this->model->with('clientes')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_articulo_suspendido;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_articulo_suspendido = $this->model->with('clientes')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_articulo_suspendido;
    }

	private function guardarCliente_Articulo_Suspendido($data, $funcion, $id = null)
	{
		$articuloIds = $this->normalizarArticuloSuspendidoIds($data);

		if ($articuloIds === []) {
			return $this->model->where('cliente_id', $id)->delete();
		}

		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_articulo_suspendido = $this->model->where('cliente_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_articulo_suspendido = count($cliente_articulo_suspendido);
		}

		$articulo_ids = array_column($articuloIds, 'articulo_id');
		$creousuario_ids = array_column($articuloIds, 'creousuario_id');

		$_id = [];
		$i = 0;
		if ($funcion == 'update')
		{
			$_id = $cliente_articulo_suspendido;

			// Borra los que sobran
			if ($q_cliente_articulo_suspendido > count($articulo_ids))
			{
				for ($d = count($articulo_ids); $d < $q_cliente_articulo_suspendido; $d++) {
					$this->model->find($_id[$d])->delete();
				}
			}

			// Actualiza los que ya existian
			for ($i = 0; $i < $q_cliente_articulo_suspendido && $i < count($articulo_ids); $i++)
			{
				$this->model->findOrFail($_id[$i])->update([
					'cliente_id' => $id,
					'articulo_id' => $articulo_ids[$i],
					'creousuario_id' => $creousuario_ids[$i],
				]);
			}
		}

		for ($i_movimiento = $i; $i_movimiento < count($articulo_ids); $i_movimiento++)
		{
			$this->model->create([
				'cliente_id' => $id,
				'articulo_id' => $articulo_ids[$i_movimiento],
				'creousuario_id' => $creousuario_ids[$i_movimiento],
			]);
		}

		return true;
	}

	/**
	 * @return list<array{articulo_id: int, creousuario_id: int}>
	 */
	private function normalizarArticuloSuspendidoIds(array $data): array
	{
		if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
			return [];
		}

		$rawArticuloIds = $data['articulo_ids'];
		$rawCreousuarioIds = $data['creousuario_articulo_suspendido_ids'] ?? [];
		$usuarioActual = (int) (Auth::id() ?? 0);
		$normalizados = [];
		$articulosVistos = [];

		foreach ($rawArticuloIds as $idx => $articuloId) {
			$articuloId = (int) trim((string) $articuloId);
			if ($articuloId <= 0 || isset($articulosVistos[$articuloId])) {
				continue;
			}

			$creousuarioId = (int) ($rawCreousuarioIds[$idx] ?? 0);
			if ($creousuarioId <= 0) {
				$creousuarioId = $usuarioActual;
			}
			if ($creousuarioId <= 0) {
				continue;
			}

			$articulosVistos[$articuloId] = true;
			$normalizados[] = [
				'articulo_id' => $articuloId,
				'creousuario_id' => $creousuarioId,
			];
		}

		return $normalizados;
	}

}
