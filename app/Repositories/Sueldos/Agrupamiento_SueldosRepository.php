<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Support\Sueldos\AgrupamientoSueldosListadoFiltros;
use App\Support\Sueldos\FalloCajaTipo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Agrupamientos de sueldos (Anita sueldos / tabla agrupamiento).
 * Sync pull desde el bridge solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita.
 */
class Agrupamiento_SueldosRepository implements Agrupamiento_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'agrupamiento';

    public function __construct(Agrupamiento_Sueldos $model)
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
    public function leeAgrupamiento($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => AgrupamientoSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = AgrupamientoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('agrupamiento_sueldos.*');

        if (AgrupamientoSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            AgrupamientoSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('agrupamiento_sueldos.codigo');

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
     * Trae desde Anita (bridge) los agrupamientos e inserta los faltantes (llenado inicial).
     * No actualiza ni borra: el maestro vive en el ERP. Dedup por código.
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
            'campos' => 'agr_agrupamiento, agr_desc, agr_id_fallo, agr_variable1, agr_variable2, agr_variable3, agr_variable4',
            'orderBy' => 'agr_agrupamiento',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));

        $resultado = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Agrupamiento_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = (int) ($row->agr_agrupamiento ?? 0);
                if ($codigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }

                $descripcion = $this->recortar(trim((string) ($row->agr_desc ?? '')), 30);
                if ($descripcion === '') {
                    $descripcion = (string) $codigo;
                }

                $payload = [
                    'descripcion' => $descripcion,
                    'fallo_tipo' => FalloCajaTipo::desdeCodigoAnita((int) ($row->agr_id_fallo ?? 0)),
                    'variable1' => (float) ($row->agr_variable1 ?? 0),
                    'variable2' => (float) ($row->agr_variable2 ?? 0),
                    'variable3' => (float) ($row->agr_variable3 ?? 0),
                    'variable4' => (float) ($row->agr_variable4 ?? 0),
                ];

                $existente = $this->findPorCodigo($codigo);
                if ($existente) {
                    $existente->update($payload);
                    $resultado['actualizados']++;
                    continue;
                }

                $this->model->create(array_merge(['codigo' => $codigo], $payload));
                $resultado['importados']++;
            }
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Agrupamiento_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $falloTipo = $data['fallo_tipo'] ?? null;
        if (! FalloCajaTipo::esValido($falloTipo)) {
            $falloTipo = null;
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 30),
            'fallo_tipo' => $falloTipo,
            'variable1' => (float) ($data['variable1'] ?? $existente?->variable1 ?? 0),
            'variable2' => (float) ($data['variable2'] ?? $existente?->variable2 ?? 0),
            'variable3' => (float) ($data['variable3'] ?? $existente?->variable3 ?? 0),
            'variable4' => (float) ($data['variable4'] ?? $existente?->variable4 ?? 0),
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
