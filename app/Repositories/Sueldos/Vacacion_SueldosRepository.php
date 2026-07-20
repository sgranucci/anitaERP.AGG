<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Vacacion_Periodo_Sueldos;
use App\Models\Sueldos\Vacacion_Sueldos;
use App\Support\Sueldos\VacacionFechaAnita;
use App\Support\Sueldos\VacacionSueldosListadoFiltros;
use App\Support\Sueldos\VacacionTipoDia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Vacaciones de sueldos (Anita sueldos / vacmae + vacmov).
 * Sync pull solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita.
 */
class Vacacion_SueldosRepository implements Vacacion_SueldosRepositoryInterface
{
    protected $model;

    protected Vacacion_Periodo_Sueldos $periodoModel;

    protected string $tableAnitaMae = 'vacmae';

    protected string $tableAnitaMov = 'vacmov';

    public function __construct(Vacacion_Sueldos $model, Vacacion_Periodo_Sueldos $periodoModel)
    {
        $this->model = $model;
        $this->periodoModel = $periodoModel;
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
    public function leeVacacion($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => VacacionSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = VacacionSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('vacacion_sueldos.*')
            ->withCount('periodos');

        if (VacacionSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            VacacionSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('vacacion_sueldos.codigo');

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
            $periodos = $this->extraerPeriodos($data);
            $cabecera = $this->normalizarCabecera($data, null);
            $registro = $this->model->create($cabecera);
            $this->sincronizarPeriodos((int) $registro->id, $periodos);

            return $registro->load('periodos');
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $periodos = $this->extraerPeriodos($data);
            $cabecera = $this->normalizarCabecera($data, $registro);
            $registro->update($cabecera);
            $this->sincronizarPeriodos((int) $registro->id, $periodos);

            return $registro->fresh('periodos');
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
        if (null == $registro = $this->model->with('periodos')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->with('periodos')->findOrFail($id);
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * Trae vacmae + vacmov desde Anita e inserta las cabeceras faltantes con sus períodos.
     * No actualiza ni borra: el maestro vive en el ERP. Dedup por código.
     *
     * @return array{en_anita: int, importados: int, omitidos: int, periodos_importados: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '600');

        $resultado = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'periodos_importados' => 0,
            'errores' => [],
        ];

        $api = new ApiAnita();

        $parsedMae = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnitaMae,
            'campos' => 'vacm_codigo, vacm_desc',
            'orderBy' => 'vacm_codigo',
        ]));
        if (! empty($parsedMae['error_lectura'])) {
            $resultado['errores'][] = (string) $parsedMae['error_lectura'];

            return $resultado;
        }

        $parsedMov = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnitaMov,
            'campos' => 'vacv_codigo, vacv_nro_linea, vacv_desde, vacv_hasta, vacv_tipo_dia, vacv_cant_dia',
            'orderBy' => 'vacv_codigo, vacv_nro_linea',
        ]));
        if (! empty($parsedMov['error_lectura'])) {
            $resultado['errores'][] = (string) $parsedMov['error_lectura'];

            return $resultado;
        }

        $periodosPorCodigo = [];
        foreach ($parsedMov['filas'] as $row) {
            $codigo = (int) ($row->vacv_codigo ?? $row->VACV_CODIGO ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $periodosPorCodigo[$codigo][] = $row;
        }

        Vacacion_Sueldos::withoutAuditing(function () use ($parsedMae, $periodosPorCodigo, &$resultado) {
            Vacacion_Periodo_Sueldos::withoutAuditing(function () use ($parsedMae, $periodosPorCodigo, &$resultado) {
                foreach ($parsedMae['filas'] as $row) {
                    $resultado['en_anita']++;
                    $codigo = (int) ($row->vacm_codigo ?? $row->VACM_CODIGO ?? 0);
                    if ($codigo <= 0) {
                        $resultado['omitidos']++;
                        continue;
                    }

                    if ($this->findPorCodigo($codigo)) {
                        $resultado['omitidos']++;
                        continue;
                    }

                    $descripcion = $this->recortar(trim((string) ($row->vacm_desc ?? $row->VACM_DESC ?? '')), 30);
                    if ($descripcion === '') {
                        $descripcion = (string) $codigo;
                    }

                    $vacacion = $this->model->create([
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                    ]);
                    $resultado['importados']++;

                    $periodos = [];
                    foreach ($periodosPorCodigo[$codigo] ?? [] as $mov) {
                        $nroLinea = (int) ($mov->vacv_nro_linea ?? $mov->VACV_NRO_LINEA ?? 0);
                        if ($nroLinea <= 0) {
                            continue;
                        }
                        $periodos[] = [
                            'nro_linea' => $nroLinea,
                            'fecha_desde' => VacacionFechaAnita::erpDesdeAnita(
                                $mov->vacv_desde ?? $mov->VACV_DESDE ?? 0
                            ),
                            'fecha_hasta' => VacacionFechaAnita::erpDesdeAnita(
                                $mov->vacv_hasta ?? $mov->VACV_HASTA ?? 0
                            ),
                            'tipo_dia' => VacacionTipoDia::normalizar(
                                (string) ($mov->vacv_tipo_dia ?? $mov->VACV_TIPO_DIA ?? '')
                            ),
                            'cantidad_dias' => max(0, (int) ($mov->vacv_cant_dia ?? $mov->VACV_CANT_DIA ?? 0)),
                        ];
                    }

                    $this->sincronizarPeriodos((int) $vacacion->id, $periodos);
                    $resultado['periodos_importados'] += count($periodos);
                }
            });
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{codigo: int, descripcion: string}
     */
    private function normalizarCabecera(array $data, ?Vacacion_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 30),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{nro_linea: int, fecha_desde: ?string, fecha_hasta: ?string, tipo_dia: ?string, cantidad_dias: int}>
     */
    private function extraerPeriodos(array $data): array
    {
        $nros = $data['nro_linea'] ?? [];
        $desdes = $data['fecha_desde'] ?? [];
        $hastas = $data['fecha_hasta'] ?? [];
        $tipos = $data['tipo_dia'] ?? [];
        $cantidades = $data['cantidad_dias'] ?? [];

        if (! is_array($nros)) {
            return [];
        }

        $periodos = [];
        $total = count($nros);
        $lineaAuto = 1;

        for ($i = 0; $i < $total; $i++) {
            $desde = trim((string) ($desdes[$i] ?? ''));
            $hasta = trim((string) ($hastas[$i] ?? ''));
            $tipo = VacacionTipoDia::normalizar((string) ($tipos[$i] ?? ''));
            $cantidad = (int) ($cantidades[$i] ?? 0);
            $nro = (int) ($nros[$i] ?? 0);

            if ($desde === '' && $hasta === '' && $tipo === null && $cantidad <= 0 && $nro <= 0) {
                continue;
            }

            if ($nro <= 0) {
                $nro = $lineaAuto;
            }
            $lineaAuto = max($lineaAuto, $nro) + 1;

            $periodos[] = [
                'nro_linea' => $nro,
                'fecha_desde' => $desde !== '' ? $desde : null,
                'fecha_hasta' => $hasta !== '' ? $hasta : null,
                'tipo_dia' => $tipo,
                'cantidad_dias' => max(0, $cantidad),
            ];
        }

        usort($periodos, fn ($a, $b) => $a['nro_linea'] <=> $b['nro_linea']);

        // Renumerar secuencial si hay duplicados de nro_linea.
        $vistos = [];
        foreach ($periodos as $idx => $periodo) {
            $nro = (int) $periodo['nro_linea'];
            if (isset($vistos[$nro])) {
                $periodos[$idx]['nro_linea'] = $idx + 1;
            }
            $vistos[$periodos[$idx]['nro_linea']] = true;
        }

        return array_values($periodos);
    }

    /**
     * @param  list<array{nro_linea: int, fecha_desde: ?string, fecha_hasta: ?string, tipo_dia: ?string, cantidad_dias: int}>  $periodos
     */
    private function sincronizarPeriodos(int $vacacionId, array $periodos): void
    {
        $this->periodoModel->newQuery()->where('vacacion_id', $vacacionId)->delete();

        foreach ($periodos as $periodo) {
            $this->periodoModel->create([
                'vacacion_id' => $vacacionId,
                'nro_linea' => $periodo['nro_linea'],
                'fecha_desde' => $periodo['fecha_desde'],
                'fecha_hasta' => $periodo['fecha_hasta'],
                'tipo_dia' => $periodo['tipo_dia'],
                'cantidad_dias' => $periodo['cantidad_dias'],
            ]);
        }
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
