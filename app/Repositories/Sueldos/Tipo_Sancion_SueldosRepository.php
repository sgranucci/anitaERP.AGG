<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use App\Support\Sueldos\TipoSancionSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Tipo_Sancion_SueldosRepository implements Tipo_Sancion_SueldosRepositoryInterface
{
    public function __construct(protected Tipo_Sancion_Sueldos $model)
    {
    }

    public function all()
    {
        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    public function leeTipoSancion($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => TipoSancionSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = TipoSancionSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->with('concepto:id,codigo,descripcion')
            ->select('tipo_sancion_sueldos.*');

        if (TipoSancionSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            TipoSancionSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('tipo_sancion_sueldos.codigo');

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
        if ($registro === null) {
            return false;
        }

        return (bool) $registro->delete();
    }

    public function find($id)
    {
        $registro = $this->model->with('concepto')->find($id);
        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->with('concepto')->findOrFail($id);
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
            $query->where(function ($q) use ($texto, $like, $id) {
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
    private function normalizarPayload(array $data, ?Tipo_Sancion_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $clase = (string) ($data['clase'] ?? Tipo_Sancion_Sueldos::CLASE_OTRO);
        if (! isset(Tipo_Sancion_Sueldos::CLASES[$clase])) {
            $clase = Tipo_Sancion_Sueldos::CLASE_OTRO;
        }

        return [
            'codigo' => $codigo,
            'nombre' => mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 60),
            'clase' => $clase,
            'requiere_dias' => ! empty($data['requiere_dias']),
            'tope_dias' => isset($data['tope_dias']) && $data['tope_dias'] !== '' ? (int) $data['tope_dias'] : null,
            'tipo_dias' => in_array($data['tipo_dias'] ?? 'corridos', ['corridos', 'habiles'], true)
                ? $data['tipo_dias']
                : 'corridos',
            'goza_sueldo' => ! empty($data['goza_sueldo']),
            'genera_novedad' => ! empty($data['genera_novedad']),
            'concepto_id' => isset($data['concepto_id']) && (int) $data['concepto_id'] > 0
                ? (int) $data['concepto_id']
                : null,
            'orden_progresivo' => max(1, (int) ($data['orden_progresivo'] ?? 1)),
            'plazo_descargo_dias' => max(0, (int) ($data['plazo_descargo_dias'] ?? 2)),
            'plantilla_notificacion' => trim((string) ($data['plantilla_notificacion'] ?? '')) ?: null,
            'activo' => array_key_exists('activo', $data) ? ! empty($data['activo']) : true,
        ];
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }
}
