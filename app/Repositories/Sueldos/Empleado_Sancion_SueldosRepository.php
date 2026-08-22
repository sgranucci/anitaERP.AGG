<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Empleado_Sancion_SueldosRepository implements Empleado_Sancion_SueldosRepositoryInterface
{
    public function __construct(
        protected Empleado_Sancion_Sueldos $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeSancionesReporte(array $filtros, bool $flPaginando = false)
    {
        $query = $this->model->newQuery()
            ->select('empleado_sancion_sueldos.*')
            ->with([
                'empleado:id,empresa_id,legajo,nombre,centrocosto_id',
                'empleado.empresa:id,nombre',
                'empleado.centrocosto:id,codigo,nombre',
                'tipo:id,codigo,nombre,clase',
                'motivo:id,codigo,nombre',
            ])
            ->join('empleado_sueldos', 'empleado_sueldos.id', '=', 'empleado_sancion_sueldos.empleado_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('empleado_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('empleado_sancion_sueldos.fecha_hecho', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('empleado_sancion_sueldos.fecha_hecho', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['tipo_sancion_id'])) {
            $query->where('empleado_sancion_sueldos.tipo_sancion_id', (int) $filtros['tipo_sancion_id']);
        }
        if (! empty($filtros['motivo_sancion_id'])) {
            $query->where('empleado_sancion_sueldos.motivo_sancion_id', (int) $filtros['motivo_sancion_id']);
        }
        if (($filtros['estado'] ?? '') !== '') {
            $query->where('empleado_sancion_sueldos.estado', $filtros['estado']);
        }
        if (($filtros['legajo_desde'] ?? '') !== '') {
            $query->where('empleado_sueldos.legajo', '>=', (int) $filtros['legajo_desde']);
        }
        if (($filtros['legajo_hasta'] ?? '') !== '') {
            $query->where('empleado_sueldos.legajo', '<=', (int) $filtros['legajo_hasta']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('empleado_sueldos.centrocosto_id', (int) $filtros['centrocosto_id']);
        }

        $query->orderBy('empleado_sueldos.legajo')
            ->orderBy('empleado_sancion_sueldos.fecha_hecho')
            ->orderBy('empleado_sancion_sueldos.id');

        return $flPaginando ? $query->paginate(15) : $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($data);

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);

        return $registro ? (bool) $registro->delete() : false;
    }

    public function find($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
