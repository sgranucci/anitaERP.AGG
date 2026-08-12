<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\SistemaNumerador;
use App\Support\Configuracion\SistemaNumeradorListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SistemaNumeradorRepository implements SistemaNumeradorRepositoryInterface
{
    public function __construct(
        private readonly SistemaNumerador $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, SistemaNumerador>
     */
    public function leeSistemaNumerador($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => SistemaNumeradorListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = SistemaNumeradorListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('sistema_numerador.*')
            ->with(['empresa:id,nombre']);

        if (SistemaNumeradorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            SistemaNumeradorListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('sistema_numerador.modulo')
            ->orderBy('sistema_numerador.codigo')
            ->orderBy('sistema_numerador.empresa_id');

        return $paginar
            ? $query->paginate(10)->appends(SistemaNumeradorListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizar($data));
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($this->normalizar($data));
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $row = $this->model->with('empresa')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresa')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data): array
    {
        $data['codigo'] = trim((string) ($data['codigo'] ?? ''));
        $data['nombre'] = trim((string) ($data['nombre'] ?? ''));
        $data['modulo'] = strtolower(trim((string) ($data['modulo'] ?? 'caja')));
        $data['ultimo_numero'] = max(0, (int) ($data['ultimo_numero'] ?? 0));
        $data['activo'] = filter_var($data['activo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        foreach (['anita_sistema', 'anita_fuente', 'anita_clave', 'observacion'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $v = trim((string) ($data[$campo] ?? ''));
                $data[$campo] = $v === '' ? null : $v;
            }
        }

        return $data;
    }
}
