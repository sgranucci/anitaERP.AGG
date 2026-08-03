<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Novedad_SueldosRepository implements Novedad_SueldosRepositoryInterface
{
    protected Novedad_Sueldos $model;

    protected string $tablaAnita = 'novedad';

    public function __construct(Novedad_Sueldos $model)
    {
        $this->model = $model;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeNovedad($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => NovedadSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'empleado',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'liquidacion_id' => null,
                'empleado_id' => null,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = NovedadSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('novedad_sueldos.*')
            ->leftJoin('empleado_sueldos', 'empleado_sueldos.id', '=', 'novedad_sueldos.empleado_id')
            ->leftJoin('concepto_sueldos', 'concepto_sueldos.id', '=', 'novedad_sueldos.concepto_id')
            ->leftJoin('liquidacion_sueldos', 'liquidacion_sueldos.id', '=', 'novedad_sueldos.liquidacion_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'novedad_sueldos.empresa_id')
            ->with([
                'empleado:id,legajo,nombre,empresa_id',
                'concepto:id,codigo,descripcion',
                'liquidacion:id,numero,descripcion,periodo,estado',
                'empresa:id,nombre',
            ])
            ->addSelect(DB::raw('empresa.nombre as nombreempresa'));

        if (NovedadSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            NovedadSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('novedad_sueldos.id');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(15);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function findOrFail($id)
    {
        $registro = $this->model->newQuery()
            ->with(['empleado', 'concepto', 'liquidacion', 'empresa'])
            ->find($id);
        if ($registro === null) {
            throw (new ModelNotFoundException)->setModel(Novedad_Sueldos::class, [$id]);
        }

        return $registro;
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarPayload($data));
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($this->normalizarPayload($data, $registro));

        return $registro->fresh(['empleado', 'concepto', 'liquidacion', 'empresa']);
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    /**
     * Insert-only desde Anita `novedad`. Clave lógica:
     * empresa + liquidación(numero) + legajo + concepto + nro_interno.
     *
     * Nota: no pedir nov_fecha_vto en el list (el bridge Anita responde []).
     *
     * @param  array{empresa_id?: int, numeros_liquidacion?: list<int>}|null  $filtros
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita(?array $filtros = null): array
    {
        ini_set('max_execution_time', '900');
        ini_set('memory_limit', '-1');

        $resultado = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $empresaFiltro = isset($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : 0;
        $numerosFiltro = [];
        if (! empty($filtros['numeros_liquidacion']) && is_array($filtros['numeros_liquidacion'])) {
            foreach ($filtros['numeros_liquidacion'] as $n) {
                $n = (int) $n;
                if ($n > 0) {
                    $numerosFiltro[$n] = true;
                }
            }
        }

        $api = new ApiAnita();
        // Campos verificados contra bridge (sin nov_fecha_vto).
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tablaAnita,
            'campos' => 'nov_empresa, nov_legajo, nov_concepto, nov_liquidacion,'
                .' nov_valor1, nov_valor2, nov_estado, nov_nro_interno, nov_fecha_liq',
            'orderBy' => 'nov_empresa',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);

        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = 'novedad: '.(string) $parsed['error_lectura'];

            return $resultado;
        }

        $empleados = [];
        foreach (Empleado_Sueldos::query()->select('id', 'empresa_id', 'legajo')->get() as $e) {
            $empleados[$e->empresa_id.':'.$e->legajo] = (int) $e->id;
        }

        $conceptos = Concepto_Sueldos::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();

        $liquidaciones = [];
        foreach (Liquidacion_Sueldos::query()->select('id', 'empresa_id', 'numero', 'periodo')->get() as $l) {
            $liquidaciones[$l->empresa_id.':'.$l->numero] = [
                'id' => (int) $l->id,
                'periodo' => $l->periodo ? (int) $l->periodo : null,
            ];
        }

        $existentes = [];
        foreach (Novedad_Sueldos::query()
            ->select('empresa_id', 'liquidacion_id', 'empleado_id', 'concepto_codigo', 'nro_interno')
            ->get() as $n) {
            $existentes[$this->claveUnica(
                (int) $n->empresa_id,
                (int) ($n->liquidacion_id ?? 0),
                (int) $n->empleado_id,
                (int) $n->concepto_codigo,
                (int) $n->nro_interno
            )] = true;
        }

        $ahora = now();
        $lote = [];
        $enAlcance = 0;

        Novedad_Sueldos::withoutAuditing(function () use (
            $parsed,
            $empleados,
            $conceptos,
            $liquidaciones,
            $empresaFiltro,
            $numerosFiltro,
            &$existentes,
            &$resultado,
            &$lote,
            &$enAlcance,
            $ahora
        ) {
            foreach ($parsed['filas'] as $fila) {
                $empresaId = (int) ($fila->nov_empresa ?? 0);
                $liqNum = (int) ($fila->nov_liquidacion ?? 0);
                $legajo = (int) ($fila->nov_legajo ?? 0);
                $conceptoCodigo = (int) ($fila->nov_concepto ?? 0);
                $nroInterno = (int) ($fila->nov_nro_interno ?? 0);

                if ($empresaFiltro > 0 && $empresaId !== $empresaFiltro) {
                    continue;
                }
                if ($numerosFiltro !== [] && ! isset($numerosFiltro[$liqNum])) {
                    continue;
                }

                if ($empresaId <= 0 || $legajo <= 0 || $conceptoCodigo <= 0) {
                    $resultado['omitidos']++;
                    continue;
                }

                $enAlcance++;

                $empleadoId = $empleados[$empresaId.':'.$legajo] ?? null;
                $conceptoId = $conceptos[$conceptoCodigo] ?? null;
                if ($empleadoId === null || $conceptoId === null) {
                    $resultado['omitidos']++;
                    continue;
                }

                $liq = $liquidaciones[$empresaId.':'.$liqNum] ?? null;
                $liquidacionId = $liq['id'] ?? null;
                $periodo = $liq['periodo'] ?? null;
                if ($periodo === null) {
                    $fechaLiq = (int) ($fila->nov_fecha_liq ?? 0);
                    if ($fechaLiq >= 19000101) {
                        $periodo = (int) floor($fechaLiq / 100);
                    }
                }

                $clave = $this->claveUnica(
                    $empresaId,
                    (int) ($liquidacionId ?? 0),
                    $empleadoId,
                    $conceptoCodigo,
                    $nroInterno
                );
                if (isset($existentes[$clave])) {
                    $resultado['omitidos']++;
                    continue;
                }

                $lote[] = [
                    'empresa_id' => $empresaId,
                    'liquidacion_id' => $liquidacionId,
                    'empleado_id' => $empleadoId,
                    'concepto_id' => $conceptoId,
                    'concepto_codigo' => $conceptoCodigo,
                    'valor1' => (float) ($fila->nov_valor1 ?? 0),
                    'valor2' => (float) ($fila->nov_valor2 ?? 0),
                    'estado' => $this->mapEstadoAnita((string) ($fila->nov_estado ?? 'P')),
                    'fecha_vto' => null,
                    'nro_interno' => $nroInterno,
                    'periodo' => $periodo,
                    'origen' => NovedadSueldosCatalogo::ORIGEN_SYNC_ANITA,
                    'usuario_id' => null,
                    'observacion' => 'Sync Anita liq '.$liqNum,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $existentes[$clave] = true;
                $resultado['importados']++;

                if (count($lote) >= 200) {
                    DB::table('novedad_sueldos')->insert($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                DB::table('novedad_sueldos')->insert($lote);
            }
        });

        $resultado['en_anita'] = $enAlcance;

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{importados: int, omitidos: int, errores: list<string>}
     */
    public function importarFilas(array $filas, string $origen = 'import'): array
    {
        $resultado = ['importados' => 0, 'omitidos' => 0, 'errores' => []];
        $origen = NovedadSueldosCatalogo::normalizarOrigen($origen);

        foreach ($filas as $i => $fila) {
            $linea = $i + 2;
            try {
                $payload = $this->resolverFilaImport($fila);
                if ($payload === null) {
                    $resultado['omitidos']++;
                    continue;
                }
                $payload['origen'] = $origen;
                $payload['usuario_id'] = optional(auth()->user())->id;
                $this->create($payload);
                $resultado['importados']++;
            } catch (\Throwable $e) {
                $resultado['errores'][] = 'Línea '.$linea.': '.$e->getMessage();
                $resultado['omitidos']++;
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data, ?Novedad_Sueldos $existente = null): array
    {
        $empleadoId = (int) ($data['empleado_id'] ?? $existente->empleado_id ?? 0);
        $conceptoId = (int) ($data['concepto_id'] ?? $existente->concepto_id ?? 0);
        $liquidacionId = isset($data['liquidacion_id']) && $data['liquidacion_id'] !== '' && $data['liquidacion_id'] !== null
            ? (int) $data['liquidacion_id']
            : ($existente->liquidacion_id ?? null);

        $empleado = Empleado_Sueldos::query()->findOrFail($empleadoId);
        $concepto = Concepto_Sueldos::query()->findOrFail($conceptoId);

        $empresaId = (int) ($data['empresa_id'] ?? $empleado->empresa_id);
        if ((int) $empleado->empresa_id !== $empresaId) {
            throw new \InvalidArgumentException('El empleado no pertenece a la empresa indicada.');
        }

        $periodo = null;
        if ($liquidacionId) {
            $liq = Liquidacion_Sueldos::query()->findOrFail($liquidacionId);
            if ((int) $liq->empresa_id !== $empresaId) {
                throw new \InvalidArgumentException('La liquidación no pertenece a la empresa indicada.');
            }
            $periodo = $liq->periodo ? (int) $liq->periodo : null;
        } elseif (! empty($data['periodo'])) {
            $periodo = (int) $data['periodo'];
        } elseif ($existente) {
            $periodo = $existente->periodo;
        }

        return [
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liquidacionId,
            'empleado_id' => $empleadoId,
            'concepto_id' => $conceptoId,
            'concepto_codigo' => (int) $concepto->codigo,
            'valor1' => (float) ($data['valor1'] ?? 0),
            'valor2' => (float) ($data['valor2'] ?? 0),
            'estado' => NovedadSueldosCatalogo::normalizarEstado($data['estado'] ?? null),
            'fecha_vto' => ! empty($data['fecha_vto']) ? $data['fecha_vto'] : null,
            'fecha_desde' => ! empty($data['fecha_desde']) ? $data['fecha_desde'] : null,
            'fecha_hasta' => ! empty($data['fecha_hasta']) ? $data['fecha_hasta'] : null,
            'nro_interno' => (int) ($data['nro_interno'] ?? 0),
            'periodo' => $periodo,
            'origen' => NovedadSueldosCatalogo::normalizarOrigen($data['origen'] ?? NovedadSueldosCatalogo::ORIGEN_MANUAL),
            'usuario_id' => $data['usuario_id'] ?? optional(auth()->user())->id,
            'observacion' => isset($data['observacion']) ? trim((string) $data['observacion']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>|null
     */
    private function resolverFilaImport(array $fila): ?array
    {
        $empresaId = (int) ($fila['empresa_id'] ?? 0);
        $legajo = (int) ($fila['legajo'] ?? 0);
        $conceptoCodigo = (int) ($fila['concepto_codigo'] ?? $fila['concepto'] ?? 0);
        $liqNumero = (int) ($fila['liquidacion_numero'] ?? $fila['liquidacion'] ?? 0);

        if ($empresaId <= 0 || $legajo <= 0 || $conceptoCodigo <= 0) {
            return null;
        }

        $empleado = Empleado_Sueldos::query()
            ->where('empresa_id', $empresaId)
            ->where('legajo', $legajo)
            ->first();
        $concepto = Concepto_Sueldos::query()->where('codigo', $conceptoCodigo)->first();
        if (! $empleado || ! $concepto) {
            throw new \InvalidArgumentException('Empleado o concepto no encontrado.');
        }

        $liquidacionId = null;
        if ($liqNumero > 0) {
            $liq = Liquidacion_Sueldos::query()
                ->where('empresa_id', $empresaId)
                ->where('numero', $liqNumero)
                ->first();
            if (! $liq) {
                throw new \InvalidArgumentException('Liquidación N° '.$liqNumero.' no encontrada.');
            }
            $liquidacionId = (int) $liq->id;
        }

        return [
            'empresa_id' => $empresaId,
            'liquidacion_id' => $liquidacionId,
            'empleado_id' => (int) $empleado->id,
            'concepto_id' => (int) $concepto->id,
            'valor1' => (float) ($fila['valor1'] ?? 0),
            'valor2' => (float) ($fila['valor2'] ?? 0),
            'estado' => $fila['estado'] ?? NovedadSueldosCatalogo::ESTADO_PENDIENTE,
            'fecha_vto' => $fila['fecha_vto'] ?? null,
            'fecha_desde' => $fila['fecha_desde'] ?? null,
            'fecha_hasta' => $fila['fecha_hasta'] ?? null,
            'nro_interno' => (int) ($fila['nro_interno'] ?? 0),
            'periodo' => isset($fila['periodo']) ? (int) $fila['periodo'] : null,
            'observacion' => $fila['observacion'] ?? null,
        ];
    }

    private function claveUnica(int $empresaId, int $liquidacionId, int $empleadoId, int $conceptoCodigo, int $nroInterno): string
    {
        return $empresaId.'|'.$liquidacionId.'|'.$empleadoId.'|'.$conceptoCodigo.'|'.$nroInterno;
    }

    private function mapEstadoAnita(string $estado): string
    {
        $c = strtoupper(trim($estado));
        if ($c === '' || $c === 'P' || $c === '0') {
            return NovedadSueldosCatalogo::ESTADO_PENDIENTE;
        }
        if ($c === 'I' || $c === 'L' || $c === '1') {
            return NovedadSueldosCatalogo::ESTADO_INCLUIDA;
        }
        if ($c === 'A' || $c === 'B' || $c === 'X') {
            return NovedadSueldosCatalogo::ESTADO_ANULADA;
        }

        return NovedadSueldosCatalogo::ESTADO_PENDIENTE;
    }

    private function fechaAnita(int $yyyymmdd): ?string
    {
        if ($yyyymmdd < 19000101) {
            return null;
        }
        $s = (string) $yyyymmdd;
        if (strlen($s) !== 8) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
