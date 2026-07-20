<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Lugartrabajo_Sueldos;
use App\Support\Sueldos\LugartrabajoSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Lugares de trabajo de sueldos (Anita sueldos / tabla lugartrabajo).
 * Sync pull desde el bridge solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita. Solo se importan código y nombre
 * (se ignoran las leyendas de la tabla de Anita).
 */
class Lugartrabajo_SueldosRepository implements Lugartrabajo_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'lugartrabajo';

    public function __construct(Lugartrabajo_Sueldos $model)
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
     * Listado paginado/completo del index con filtros inteligentes.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeLugartrabajo($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => LugartrabajoSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = LugartrabajoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('lugartrabajo_sueldos.*');

        if (LugartrabajoSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            LugartrabajoSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('lugartrabajo_sueldos.codigo');

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
     * Trae desde Anita (bridge) los lugares de trabajo e inserta los faltantes (llenado inicial).
     * No actualiza ni borra: el maestro vive en el ERP. Dedup por código. Solo código y nombre.
     *
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '600');

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'lugt_lugartrabajo, lugt_desc',
            'orderBy' => 'lugt_lugartrabajo',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Lugartrabajo_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = (int) ($row->lugt_lugartrabajo ?? 0);
                if ($codigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }

                if ($this->findPorCodigo($codigo)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $nombre = $this->recortar(trim((string) ($row->lugt_desc ?? '')), 255);
                if ($nombre === '') {
                    $nombre = (string) $codigo;
                }

                $this->model->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
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
    private function normalizarPayload(array $data, ?Lugartrabajo_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        return [
            'codigo' => $codigo,
            'nombre' => $this->recortar(trim((string) ($data['nombre'] ?? '')), 255),
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
