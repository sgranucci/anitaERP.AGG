<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Categoria_Base_Sueldos;
use App\Models\Sueldos\Categoria_Sueldos;
use App\Models\Sueldos\Nombrebase_Sueldos;
use App\Support\Sueldos\CategoriaOrigenBases;
use App\Support\Sueldos\CategoriaSueldosListadoFiltros;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Categorías de sueldos (Anita sueldos / tabla categoria).
 * Sync pull solo para llenado inicial; el CRUD vive completo en el ERP.
 * Las bases de cálculo se guardan aparte en categoria_base_sueldos con fecha de vigencia.
 */
class Categoria_SueldosRepository implements Categoria_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'categoria';

    /**
     * Mapa columna Anita => código de nombrebase (número de base 1..6).
     *
     * @var array<string, int>
     */
    protected array $mapaBases = [
        'cat_sueldo' => 1,
        'cat_diario' => 2,
        'cat_hora' => 3,
        'cat_base4' => 4,
        'cat_base5' => 5,
        'cat_base6' => 6,
    ];

    public function __construct(Categoria_Sueldos $model)
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
    public function leeCategoria($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => CategoriaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = CategoriaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('categoria_sueldos.*');

        if (CategoriaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CategoriaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('categoria_sueldos.codigo');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(10);
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
     * Trae categorías desde Anita e inserta las faltantes (llenado inicial).
     * Para categorías con origen 'T' importa las bases != 0 hacia categoria_base_sueldos.
     *
     * @return array{en_anita: int, importados: int, omitidos: int, bases_importadas: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '600');

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'bases_importadas' => 0, 'errores' => []];

        // Necesitamos el catálogo de nombres de base para mapear las bases.
        $nombrebasePorCodigo = Nombrebase_Sueldos::query()->pluck('id', 'codigo')->all();
        if ($nombrebasePorCodigo === []) {
            $resultado['errores'][] = 'No hay nombres de bases cargados (sincronice primero "Nombres de bases").';
        }

        $api = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'cat_numero, cat_desc, cat_tabla, cat_sueldo, cat_diario, cat_hora, cat_base4, cat_base5, cat_base6',
            'orderBy' => 'cat_numero',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($data));
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        $hoy = Carbon::today()->toDateString();

        // El llenado inicial no debe generar registros de auditoría (categorías y bases).
        // Se desactiva globalmente porque el sync toca dos modelos auditables.
        Categoria_Sueldos::withoutAuditing(function () use ($parsed, $nombrebasePorCodigo, $hoy, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;
                $codigo = (int) ($row->cat_numero ?? 0);
                if ($codigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }

                $categoria = $this->findPorCodigo($codigo);
                if ($categoria === null) {
                    $descripcion = $this->recortar(trim((string) ($row->cat_desc ?? '')), 30);
                    if ($descripcion === '') {
                        $descripcion = (string) $codigo;
                    }
                    $categoria = $this->model->create([
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'origen_bases' => CategoriaOrigenBases::normalizar((string) ($row->cat_tabla ?? 'T')),
                    ]);
                    $resultado['importados']++;
                } else {
                    $resultado['omitidos']++;
                }

                // Importar bases solo cuando la categoría toma bases de la tabla.
                if (! CategoriaOrigenBases::usaTablaCategoria($categoria->origen_bases)) {
                    continue;
                }

                foreach ($this->mapaBases as $columna => $codigoBase) {
                    $valor = (float) ($row->{$columna} ?? 0);
                    if ($valor == 0.0) {
                        continue;
                    }
                    $nombrebaseId = $nombrebasePorCodigo[$codigoBase] ?? null;
                    if ($nombrebaseId === null) {
                        continue;
                    }

                    $yaExiste = Categoria_Base_Sueldos::query()
                        ->where('categoria_id', $categoria->id)
                        ->where('nombrebase_id', $nombrebaseId)
                        ->exists();
                    if ($yaExiste) {
                        continue;
                    }

                    Categoria_Base_Sueldos::create([
                        'categoria_id' => $categoria->id,
                        'nombrebase_id' => $nombrebaseId,
                        'valor' => $valor,
                        'fecha_vigencia' => $hoy,
                        'valor_anterior' => null,
                        'usuario_id' => null,
                    ]);
                    $resultado['bases_importadas']++;
                }
            }
        }, true);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Categoria_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 30),
            'origen_bases' => CategoriaOrigenBases::normalizar($data['origen_bases'] ?? null),
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
