<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Support\Sueldos\TipoAusenciaSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Tipo_Ausencia_SueldosRepository implements Tipo_Ausencia_SueldosRepositoryInterface
{
    protected $model;

    public function __construct(Tipo_Ausencia_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->newQuery()->orderBy('orden')->orderBy('codigo')->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeTipoAusencia($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => TipoAusenciaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = TipoAusenciaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('tipo_ausencia_sueldos.*');

        if (TipoAusenciaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            TipoAusenciaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('tipo_ausencia_sueldos.orden')->orderBy('tipo_ausencia_sueldos.codigo');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(15);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($this->normalizar($data, null));
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $registro->update($this->normalizar($data, $registro));

            return $registro->fresh();
        });
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->find($id)) {
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data, ?Tipo_Ausencia_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $categoria = $data['categoria'] ?? 'licencia';
        $esVacaciones = $categoria === 'vacaciones';

        $tope = $data['tope_dias_anio'] ?? null;
        if ($tope === '' || $tope === null) {
            $tope = null;
        }

        return [
            'codigo' => $codigo,
            'nombre' => mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 60),
            'categoria' => isset(Tipo_Ausencia_Sueldos::CATEGORIAS[$categoria]) ? $categoria : 'licencia',
            'afecta_saldo_vacaciones' => $esVacaciones ? true : (bool) ($data['afecta_saldo_vacaciones'] ?? false),
            'goza_sueldo' => (bool) ($data['goza_sueldo'] ?? false),
            'computa_antiguedad' => (bool) ($data['computa_antiguedad'] ?? false),
            'requiere_certificado' => (bool) ($data['requiere_certificado'] ?? false),
            'tipo_dias' => in_array($data['tipo_dias'] ?? null, ['corridos', 'habiles'], true) ? $data['tipo_dias'] : 'corridos',
            'tope_dias_anio' => $tope !== null ? (int) $tope : null,
            'concepto_id' => ! empty($data['concepto_id']) ? (int) $data['concepto_id'] : null,
            'color' => ! empty($data['color']) ? mb_substr(trim((string) $data['color']), 0, 9) : null,
            'activo' => (bool) ($data['activo'] ?? true),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }
}
