<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Empleado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class EmpleadoRepository implements EmpleadoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'empleado';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'emp_legajo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Empleado $empleado)
    {
        $this->model = $empleado;
    }

    public function all()
    {
        return $this->model->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $empleado = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $empleado = $this->model->findOrFail($id)
            ->update($data);

		return $empleado;
    }

    public function delete($id)
    {
    	$empleado = Empleado::find($id);
		
        $empleado = $this->model->destroy($id);

		return $empleado;
    }

    public function find($id)
    {
        if (null == $empleado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empleado;
    }

    public function findOrFail($id)
    {
        if (null == $empleado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empleado;
    }

    public function consultaModal(?string $texto): array
    {
        $q = $this->model->newQuery()->orderBy('id');
        $texto = trim((string) $texto);
        if ($texto !== '') {
            $q->where(function ($w) use ($texto) {
                $w->where('nombre', 'like', '%'.$texto.'%');
                if (ctype_digit($texto)) {
                    $w->orWhere('id', (int) $texto);
                }
            });
        }
        $filas = $q->limit(100)->get(['id', 'nombre']);
        $puedeAbrirAbm = can('editar-empleados', false) || can('listar-empleados', false);
        $html = '';
        foreach ($filas as $row) {
            $html .= '<tr>';
            $html .= '<td class="id">'.e($row->id).'</td>';
            $html .= '<td class="nombre">'.e($row->nombre).'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<a class="btn btn-warning btn-sm eligeconsultaempleado">Elegir</a>';
            if ($puedeAbrirAbm) {
                $url = route('editar_empleado', [
                    'id' => (int) $row->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="3" class="text-muted">Sin resultados.</td></tr>';
        }

        return ['data' => $html];
    }

    public function findParaConsulta($id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $row = $this->model->newQuery()->find($id, ['id', 'nombre']);
        if (! $row) {
            return null;
        }

        return ['id' => (int) $row->id, 'nombre' => (string) $row->nombre];
    }

}
