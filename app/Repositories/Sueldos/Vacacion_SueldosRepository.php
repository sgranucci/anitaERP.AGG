<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Vacacion_Periodo_Sueldos;
use App\Models\Sueldos\Vacacion_Sueldos;
use App\Support\Sueldos\VacacionSueldosListadoFiltros;
use App\Support\Sueldos\VacacionTipoDia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

                    $periodos = $this->periodosDesdeFilasAnita($periodosPorCodigo[$codigo] ?? []);

                    $this->sincronizarPeriodos((int) $vacacion->id, $periodos);
                    $resultado['periodos_importados'] += count($periodos);
                }
            });
        });

        return $resultado;
    }

    /**
     * Completa las tablas que ya existen en el ERP con lo que Anita (vacmov) tiene y el ERP no:
     * el rango de antigüedad en años de cada tramo y las líneas que faltan (la línea 0 se
     * descartaba al importar). No pisa lo que el operador cambió: solo rellena `anios_desde` /
     * `anios_hasta` vacíos de una línea con el mismo nº, y agrega líneas inexistentes.
     *
     * @return array{tablas: int, lineas_completadas: int, lineas_nuevas: int, detalle: list<string>, errores: list<string>}
     */
    public function completarAntiguedadDesdeAnita(bool $dryRun = true): array
    {
        $res = ['tablas' => 0, 'lineas_completadas' => 0, 'lineas_nuevas' => 0, 'detalle' => [], 'errores' => []];

        if (! $this->tieneColumnasAnios()) {
            $res['errores'][] = 'Falta migrar vacacion_periodo_sueldos (anios_desde / anios_hasta).';

            return $res;
        }

        $parsedMov = ApiAnita::parsearRespuestaLista((new ApiAnita())->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnitaMov,
            'campos' => 'vacv_codigo, vacv_nro_linea, vacv_desde, vacv_hasta, vacv_tipo_dia, vacv_cant_dia',
            'orderBy' => 'vacv_codigo, vacv_nro_linea',
        ]));
        if (! empty($parsedMov['error_lectura'])) {
            $res['errores'][] = (string) $parsedMov['error_lectura'];

            return $res;
        }

        $porCodigo = [];
        foreach ($parsedMov['filas'] as $row) {
            $codigo = (int) ($row->vacv_codigo ?? $row->VACV_CODIGO ?? 0);
            if ($codigo > 0) {
                $porCodigo[$codigo][] = $row;
            }
        }

        $aplicar = function () use ($porCodigo, $dryRun, &$res) {
            foreach ($porCodigo as $codigo => $filasAnita) {
                $vacacion = $this->findPorCodigo((int) $codigo);
                if ($vacacion === null) {
                    continue;
                }
                $existentes = $this->periodoModel->newQuery()
                    ->where('vacacion_id', $vacacion->id)
                    ->get()
                    ->keyBy('nro_linea');
                $tocada = false;

                foreach ($this->periodosDesdeFilasAnita($filasAnita) as $p) {
                    $actual = $existentes->get($p['nro_linea']);
                    if ($actual === null) {
                        $res['lineas_nuevas']++;
                        $res['detalle'][] = sprintf('Tabla %d: línea %d NUEVA (%s–%s años, %d días)', $codigo, $p['nro_linea'], $p['anios_desde'], $p['anios_hasta'], $p['cantidad_dias']);
                        $tocada = true;
                        if (! $dryRun) {
                            $this->periodoModel->create(['vacacion_id' => $vacacion->id] + $p);
                        }

                        continue;
                    }
                    if ($actual->anios_desde === null && $actual->anios_hasta === null && (int) $actual->cantidad_dias === (int) $p['cantidad_dias']) {
                        $res['lineas_completadas']++;
                        $res['detalle'][] = sprintf('Tabla %d: línea %d completa años %s–%s (%d días)', $codigo, $p['nro_linea'], $p['anios_desde'], $p['anios_hasta'], $p['cantidad_dias']);
                        $tocada = true;
                        if (! $dryRun) {
                            $actual->update(['anios_desde' => $p['anios_desde'], 'anios_hasta' => $p['anios_hasta']]);
                        }
                    }
                }
                if ($tocada) {
                    $res['tablas']++;
                }
            }
        };

        if ($dryRun) {
            $aplicar();
        } else {
            Vacacion_Periodo_Sueldos::withoutAuditing(fn () => DB::transaction($aplicar));
        }

        return $res;
    }

    /**
     * Filas vacmov de Anita -> líneas del ERP. La línea 0 es válida (primer tramo de antigüedad).
     * vacv_desde / vacv_hasta son AÑOS de antigüedad, no fechas.
     *
     * @param  iterable<object>  $filas
     * @return list<array{nro_linea: int, anios_desde: int, anios_hasta: int, tipo_dia: ?string, cantidad_dias: int}>
     */
    private function periodosDesdeFilasAnita(iterable $filas): array
    {
        $periodos = [];
        foreach ($filas as $mov) {
            $nroLinea = (int) ($mov->vacv_nro_linea ?? $mov->VACV_NRO_LINEA ?? -1);
            if ($nroLinea < 0) {
                continue;
            }
            $periodos[] = [
                'nro_linea' => $nroLinea,
                'anios_desde' => max(0, (int) ($mov->vacv_desde ?? $mov->VACV_DESDE ?? 0)),
                'anios_hasta' => max(0, (int) ($mov->vacv_hasta ?? $mov->VACV_HASTA ?? 0)),
                'tipo_dia' => VacacionTipoDia::normalizar((string) ($mov->vacv_tipo_dia ?? $mov->VACV_TIPO_DIA ?? '')),
                'cantidad_dias' => max(0, (int) ($mov->vacv_cant_dia ?? $mov->VACV_CANT_DIA ?? 0)),
            ];
        }

        return $periodos;
    }

    private function tieneColumnasAnios(): bool
    {
        static $tiene = null;

        return $tiene ??= Schema::hasColumn('vacacion_periodo_sueldos', 'anios_desde');
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
        $aniosDesde = $data['anios_desde'] ?? [];
        $aniosHasta = $data['anios_hasta'] ?? [];
        $tipos = $data['tipo_dia'] ?? [];
        $cantidades = $data['cantidad_dias'] ?? [];

        if (! is_array($nros)) {
            return [];
        }

        $periodos = [];
        $total = count($nros);
        $lineaAuto = 0;

        for ($i = 0; $i < $total; $i++) {
            $desde = trim((string) ($aniosDesde[$i] ?? ''));
            $hasta = trim((string) ($aniosHasta[$i] ?? ''));
            $tipo = VacacionTipoDia::normalizar((string) ($tipos[$i] ?? ''));
            $cantidad = (int) ($cantidades[$i] ?? 0);
            $nroRaw = trim((string) ($nros[$i] ?? ''));

            if ($desde === '' && $hasta === '' && $tipo === null && $cantidad <= 0 && $nroRaw === '') {
                continue;
            }

            // La línea 0 es válida (Anita numera desde 0). Vacío = siguiente libre.
            $nro = $nroRaw === '' ? $lineaAuto : max(0, (int) $nroRaw);
            $lineaAuto = max($lineaAuto, $nro) + 1;

            $periodos[] = [
                'nro_linea' => $nro,
                'anios_desde' => $desde !== '' ? max(0, (int) $desde) : null,
                'anios_hasta' => $hasta !== '' ? max(0, (int) $hasta) : null,
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
                $periodos[$idx]['nro_linea'] = max(array_keys($vistos)) + 1;
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

        $conAnios = $this->tieneColumnasAnios();
        foreach ($periodos as $periodo) {
            $fila = [
                'vacacion_id' => $vacacionId,
                'nro_linea' => $periodo['nro_linea'],
                'fecha_desde' => $periodo['fecha_desde'] ?? null,
                'fecha_hasta' => $periodo['fecha_hasta'] ?? null,
                'tipo_dia' => $periodo['tipo_dia'],
                'cantidad_dias' => $periodo['cantidad_dias'],
            ];
            if ($conAnios) {
                $fila['anios_desde'] = $periodo['anios_desde'] ?? null;
                $fila['anios_hasta'] = $periodo['anios_hasta'] ?? null;
            }
            $this->periodoModel->create($fila);
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
