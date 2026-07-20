<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Acumulador_Sueldos;
use App\Support\Sueldos\AcumuladorSueldosListadoFiltros;
use App\Support\Sueldos\ConceptoTipo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Acumuladores dinámicos de liquidación. Agrupan importes por tipo de concepto.
 */
class Acumulador_SueldosRepository implements Acumulador_SueldosRepositoryInterface
{
    protected $model;

    public function __construct(Acumulador_Sueldos $model)
    {
        $this->model = $model;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeAcumulador($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => AcumuladorSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = AcumuladorSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('acumulador_sueldos.*');

        if (AcumuladorSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            AcumuladorSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('acumulador_sueldos.orden')->orderBy('acumulador_sueldos.codigo');

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
        if ($registro->reservado) {
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

    public function findPorCodigo(string $codigo)
    {
        $codigo = $this->normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }

        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Acumulador_Sueldos $existente): array
    {
        $codigo = $existente !== null && $existente->reservado
            ? (string) $existente->codigo
            : $this->normalizarCodigo((string) ($data['codigo'] ?? ''));

        $signo = (int) ($data['signo'] ?? 1);
        if (! in_array($signo, [1, -1], true)) {
            $signo = 1;
        }

        $payload = [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 80),
            'tipos_incluye' => $this->normalizarTiposIncluye($data['tipos_incluye'] ?? []),
            'signo' => $signo,
            'activo' => (bool) ($data['activo'] ?? true),
            'orden' => (int) ($data['orden'] ?? 0),
            'empresa_id' => isset($data['empresa_id']) && $data['empresa_id'] !== ''
                ? (int) $data['empresa_id']
                : null,
        ];

        if ($existente !== null) {
            $payload['reservado'] = (bool) $existente->reservado;
        } else {
            $payload['reservado'] = false;
        }

        return $payload;
    }

    /**
     * @param  mixed  $tipos
     * @return list<string>
     */
    private function normalizarTiposIncluye($tipos): array
    {
        if (! is_array($tipos)) {
            return [];
        }

        $permitidos = ConceptoTipo::tiposPermitidos();
        $resultado = [];
        foreach ($tipos as $tipo) {
            $tipo = trim((string) $tipo);
            if ($tipo !== '' && in_array($tipo, $permitidos, true) && ! in_array($tipo, $resultado, true)) {
                $resultado[] = $tipo;
            }
        }

        return $resultado;
    }

    private function normalizarCodigo(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        return $this->recortar($codigo, 30);
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
