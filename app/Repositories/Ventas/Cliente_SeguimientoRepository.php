<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Seguimiento;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_SeguimientoRepository implements Cliente_SeguimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Seguimiento $cliente_seguimiento)
    {
        $this->model = $cliente_seguimiento;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Seguimiento($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_seguimiento = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Seguimiento($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_seguimiento = $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return EloquentAuditDeleteSupport::each($this->model->newQuery()->where('id', $id));
    }

    public function find($id)
    {
        if (null == $cliente_seguimiento = $this->model->with('clientes')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_seguimiento;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_seguimiento = $this->model->with('clientes')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_seguimiento;
    }

	private function guardarCliente_Seguimiento($data, $funcion, $id = null)
	{
		// Un submit sin la solapa Seguimiento no puede borrar el historial importado de Anita.
		if (! isset($data['seguimiento_en_formulario'])) {
			return null;
		}

		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_seguimiento = $this->model->where('cliente_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_seguimiento = count($cliente_seguimiento);
		}

		$renglones = $this->renglonesDesdeRequest($data, $id);

		if ($funcion != 'update')
		{
			foreach ($renglones as $renglon)
				$this->model->create($renglon);

			return count($renglones);
		}

		$_id = $cliente_seguimiento;

		// Reusa las filas existentes por posición para conservar id y trazabilidad
		for ($i = 0; $i < $q_cliente_seguimiento && $i < count($renglones); $i++)
			$this->model->findOrFail($_id[$i])->update($renglones[$i]);

		// Las que sobran se borran instancia a instancia para que queden en audits
		if ($q_cliente_seguimiento > count($renglones))
			EloquentAuditDeleteSupport::each(
				$this->model->newQuery()
					->where('cliente_id', $id)
					->whereIn('id', array_slice($_id, count($renglones)))
			);

		for ($i_movimiento = $q_cliente_seguimiento; $i_movimiento < count($renglones); $i_movimiento++)
			$this->model->create($renglones[$i_movimiento]);

		return count($renglones);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function renglonesDesdeRequest(array $data, $id): array
	{
		$fechas = $data['fechas'] ?? [];
		if (! is_array($fechas))
			return [];

		$observaciones = $data['observaciones'] ?? [];
		$leyendas = $data['leyendas'] ?? [];
		$creousuario_ids = $data['creousuario_ids'] ?? $data['creousuario_id'] ?? [];
		$usuarioActual = Auth::id();

		$renglones = [];
		foreach ($fechas as $indice => $fecha)
		{
			if (trim((string) $fecha) === '')
				continue;

			$creousuario_id = $creousuario_ids[$indice] ?? null;

			$renglones[] = [
				'cliente_id' => $id,
				'fecha' => $fecha,
				'observacion' => $observaciones[$indice] ?? '',
				'leyenda' => $leyendas[$indice] ?? '',
				'creousuario_id' => $creousuario_id !== null && $creousuario_id !== '' ? $creousuario_id : $usuarioActual,
			];
		}

		return $renglones;
	}

}
