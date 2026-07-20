<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Parametro_Sueldos;
use App\Models\Sueldos\Parametro_Valor_Sueldos;
use App\Support\Sueldos\ParametroSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Parametro_SueldosRepository implements Parametro_SueldosRepositoryInterface
{
    protected Parametro_Sueldos $model;

    public function __construct(Parametro_Sueldos $model)
    {
        $this->model = $model;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeParametro($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ParametroSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ParametroSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('parametro_sueldos.*')
            ->withCount('valores')
            ->with(['valores' => function ($q) {
                $q->orderByDesc('fecha_vigencia')->limit(1);
            }]);

        if (ParametroSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ParametroSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('parametro_sueldos.codigo');

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
            $valores = $data['valores'] ?? [];
            unset($data['valores']);

            $parametro = $this->model->create($this->normalizarPayload($data));

            $this->syncValores($parametro, is_array($valores) ? $valores : []);

            return $parametro->load('valores');
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $valores = $data['valores'] ?? [];
            unset($data['valores']);

            $registro->update($this->normalizarPayload($data, $registro));

            $this->syncValores($registro, is_array($valores) ? $valores : []);

            return $registro->load('valores');
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
        return $this->model->newQuery()
            ->with(['valores' => function ($q) {
                $q->orderByDesc('fecha_vigencia');
            }])
            ->findOrFail($id);
    }

    public function findPorCodigo(string $codigo)
    {
        $codigoNorm = $this->normalizarCodigo($codigo);
        if ($codigoNorm === '') {
            return null;
        }

        return $this->model->newQuery()->where('codigo', $codigoNorm)->first();
    }

    /**
     * @param  list<array<string, mixed>>  $valores
     */
    private function syncValores(Parametro_Sueldos $parametro, array $valores): void
    {
        Parametro_Valor_Sueldos::query()
            ->where('parametro_id', $parametro->id)
            ->delete();

        $fechasUsadas = [];
        foreach ($valores as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $fecha = trim((string) ($fila['fecha_vigencia'] ?? ''));
            if ($fecha === '') {
                continue;
            }

            if (isset($fechasUsadas[$fecha])) {
                continue;
            }
            $fechasUsadas[$fecha] = true;

            $valorNum = $fila['valor'] ?? null;
            $valorTexto = isset($fila['valor_texto']) ? trim((string) $fila['valor_texto']) : null;
            if ($valorTexto === '') {
                $valorTexto = null;
            }

            Parametro_Valor_Sueldos::query()->create([
                'parametro_id' => $parametro->id,
                'fecha_vigencia' => $fecha,
                'valor' => $valorNum !== null && $valorNum !== '' ? $valorNum : 0,
                'valor_texto' => $valorTexto,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Parametro_Sueldos $existente = null): array
    {
        $codigo = $existente !== null
            ? (string) $existente->codigo
            : $this->normalizarCodigo((string) ($data['codigo'] ?? ''));

        return [
            'empresa_id' => $data['empresa_id'] ?? null,
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 120),
            'tipo' => in_array($data['tipo'] ?? '', ['numero', 'texto'], true) ? $data['tipo'] : 'numero',
            'unidad' => $this->recortarNullable(trim((string) ($data['unidad'] ?? '')), 20),
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
        ];
    }

    private function normalizarCodigo(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }

    private function recortarNullable(string $valor, int $len): ?string
    {
        if ($valor === '') {
            return null;
        }

        return $this->recortar($valor, $len);
    }
}
