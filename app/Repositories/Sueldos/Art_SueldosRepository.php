<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Art_Sueldos;
use App\Support\Sueldos\ArtSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ART de sueldos (Anita sueldos / tabla artmae). Código alfanumérico (artm_art).
 * Sync pull desde el bridge solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita.
 */
class Art_SueldosRepository implements Art_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'artmae';

    public function __construct(Art_Sueldos $model)
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
    public function leeArt($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ArtSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ArtSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('art_sueldos.*');

        if (ArtSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ArtSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('art_sueldos.codigo');

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

    public function findPorCodigo(string $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * Trae desde Anita (bridge) las ART e inserta las faltantes (llenado inicial).
     * No actualiza ni borra: el maestro vive en el ERP. Dedup por código alfanumérico.
     *
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
            'campos' => 'artm_art, artm_nombre',
            'orderBy' => 'artm_art',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Art_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = $this->recortar(trim((string) ($row->artm_art ?? '')), 15);
                if ($codigo === '') {
                    $resultado['omitidos']++;
                    continue;
                }

                if ($this->findPorCodigo($codigo)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $nombre = $this->recortar(trim((string) ($row->artm_nombre ?? '')), 30);
                if ($nombre === '') {
                    $nombre = $codigo;
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
    private function normalizarPayload(array $data, ?Art_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (string) $existente->codigo
            : $this->recortar(trim((string) ($data['codigo'] ?? '')), 15);

        return [
            'codigo' => $codigo,
            'nombre' => $this->recortar(trim((string) ($data['nombre'] ?? '')), 30),
        ];
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
