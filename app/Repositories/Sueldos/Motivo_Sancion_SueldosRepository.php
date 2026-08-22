<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Motivo_Sancion_Sueldos;
use App\Support\Sueldos\MotivoSancionSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Motivo_Sancion_SueldosRepository implements Motivo_Sancion_SueldosRepositoryInterface
{
    public function __construct(protected Motivo_Sancion_Sueldos $model)
    {
    }

    public function all()
    {
        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    public function leeMotivoSancion($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => MotivoSancionSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = MotivoSancionSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('motivo_sancion_sueldos.*');

        if (MotivoSancionSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            MotivoSancionSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('motivo_sancion_sueldos.codigo');

        if (isset($flPaginando)) {
            return $flPaginando ? $query->paginate(15) : $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarPayload($data, null));
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($this->normalizarPayload($data, $registro));

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

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    public function findActivoPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->activos()->where('codigo', $codigo)->first();
    }

    public function listadoParaConsulta(string $consulta)
    {
        $query = $this->model->newQuery()->activos()->orderBy('codigo');
        $texto = trim($consulta);
        if ($texto !== '') {
            $id = filter_var($texto, FILTER_VALIDATE_INT);
            $like = '%'.addcslashes($texto, '%_\\').'%';
            $query->where(function ($q) use ($like, $id) {
                if ($id !== false) {
                    $q->orWhere('id', (int) $id)->orWhere('codigo', (int) $id);
                }
                $q->orWhere('nombre', 'like', $like);
            });
        }

        return $query->limit(80)->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Motivo_Sancion_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        return [
            'codigo' => $codigo,
            'nombre' => mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 60),
            'activo' => array_key_exists('activo', $data) ? ! empty($data['activo']) : true,
        ];
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }
}
