<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Asignacion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Caja_AsignacionRepository implements Caja_AsignacionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(
        Caja_Asignacion $caja,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $caja;
    }

    public function all($estado = null, ?int $empresaId = null)
    {
        $query = $this->model->with(['empresas', 'cajas', 'usuarios']);

        if ($estado) {
            $query->whereIn('estado', $estado);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function create(array $data)
    {
        $caja = $this->model->create($data);

        return($caja);
    }

    public function update(array $data, $id)
    {
        $caja = $this->model->findOrFail($id)->update($data);

		return $caja;
    }

    public function delete($id)
    {
    	$caja = $this->model->find($id);

        $caja = $this->model->destroy($id);

		return $caja;
    }

    public function find($id)
    {
        if (null == $caja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja;
    }

    public function findOrFail($id)
    {
        if (null == $caja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja;
    }

}
