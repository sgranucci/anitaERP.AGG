<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Sindicato_Sueldos;
use App\Support\Sueldos\SindicatoSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Sindicatos de sueldos (Anita sueldos / tabla sindicato).
 * Sync pull solo para llenado inicial; el CRUD vive completo en el ERP.
 * No se toma la imputación contable de Anita (sin_imput): no se usa.
 */
class Sindicato_SueldosRepository implements Sindicato_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'sindicato';

    public function __construct(Sindicato_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeSindicato($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => SindicatoSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = SindicatoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('sindicato_sueldos.*');

        if (SindicatoSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            SindicatoSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('sindicato_sueldos.codigo');

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
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'sin_codigo, sin_desc, sin_numero',
            'orderBy' => 'sin_codigo',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Sindicato_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = (int) ($row->sin_codigo ?? 0);
                if ($codigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }
                if ($this->findPorCodigo($codigo)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $descripcion = $this->recortar(trim((string) ($row->sin_desc ?? '')), 30);
                if ($descripcion === '') {
                    $descripcion = (string) $codigo;
                }

                $this->model->create([
                    'codigo' => $codigo,
                    'descripcion' => $descripcion,
                    'numero' => $this->recortar(trim((string) ($row->sin_numero ?? '')), 15) ?: null,
                ]);
                $resultado['importados']++;
            }
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Sindicato_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $numero = $this->recortar(trim((string) ($data['numero'] ?? '')), 15);

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 30),
            'numero' => $numero !== '' ? $numero : null,
        ];
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
