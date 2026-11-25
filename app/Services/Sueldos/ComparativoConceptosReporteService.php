<?php

namespace App\Services\Sueldos;

use App\Models\Contable\Centrocosto;
use App\Models\Sueldos\Categoria_Sueldos;
use App\Models\Sueldos\Empleado_Ingreso_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Sindicato_Sueldos;
use App\Support\Sueldos\ComparativoConceptosListadoFiltros as F;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comparativo de conceptos liquidados (Anita l-comparacon.c / auxrec).
 *
 * Pivot: una fila por empleado+concepto; columnas = períodos o liquidaciones.
 * Importe = suma(haber) − suma(descuento), como haberes − deduc en Anita.
 */
class ComparativoConceptosReporteService
{
    public const MAX_COLUMNAS = 24;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   columnas: list<array{clave: string, etiqueta: string, periodo: string, liquidacion_id: int|null, numero: int|null}>,
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, mixed>,
     *   subtitulo: string,
     *   titulo2: string,
     *   formato: string,
     *   muestra_diferencia: bool,
     *   advertencia: string|null
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $formato = (string) ($filtros['formato'] ?? F::FORMATO_IMPORTE);
        $orden = (string) ($filtros['orden'] ?? F::ORDEN_LEGAJO);

        [$columnas, $advertencia] = $this->resolverColumnas($filtros);
        if ($columnas === []) {
            return [
                'columnas' => [],
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'subtitulo' => F::subtitulo($filtros),
                'titulo2' => F::etiquetaOrden($orden),
                'formato' => $formato,
                'muestra_diferencia' => false,
                'advertencia' => $advertencia ?? 'No hay liquidaciones en el rango indicado.',
            ];
        }

        $empleadosOk = $this->empleadosPermitidos($filtros);
        if ($empleadosOk->isEmpty()) {
            return [
                'columnas' => $columnas,
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'subtitulo' => F::subtitulo($filtros),
                'titulo2' => F::etiquetaOrden($orden),
                'formato' => $formato,
                'muestra_diferencia' => count($columnas) === 2,
                'advertencia' => 'No hay empleados que cumplan el filtro.',
            ];
        }

        $agg = $this->agregarDetalles($filtros, $columnas, $empleadosOk->keys()->all());
        $filas = $this->armarFilas($agg, $empleadosOk, $columnas, $orden, $formato);
        $totales = $this->calcularTotales($filas, $columnas);

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'totales' => $totales,
            'subtitulo' => F::subtitulo($filtros),
            'titulo2' => F::etiquetaOrden($orden),
            'formato' => $formato,
            'muestra_diferencia' => count($columnas) === 2,
            'advertencia' => $advertencia,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0: list<array{clave: string, etiqueta: string, periodo: string, liquidacion_id: int|null, numero: int|null}>, 1: string|null}
     */
    private function resolverColumnas(array $filtros): array
    {
        $empresaIds = $this->empresaIdsDesdeFiltros($filtros);
        $forma = (string) ($filtros['forma'] ?? F::FORMA_PERIODO);
        $advertencia = null;

        if ($forma === F::FORMA_LIQUIDACION) {
            $q = Liquidacion_Sueldos::query()
                ->where('simulacion', false)
                ->orderBy('numero');
            if ($empresaIds !== []) {
                $q->whereIn('empresa_id', $empresaIds);
            }
            $desde = $filtros['liquidacion_desde'] ?? null;
            $hasta = $filtros['liquidacion_hasta'] ?? null;
            if ($desde !== null) {
                $q->where('numero', '>=', (int) $desde);
            }
            if ($hasta !== null) {
                $q->where('numero', '<=', (int) $hasta);
            }

            $liqs = $q->limit(self::MAX_COLUMNAS + 1)->get(['id', 'numero', 'descripcion', 'periodo']);
            if ($liqs->count() > self::MAX_COLUMNAS) {
                $advertencia = 'Se muestran las primeras '.self::MAX_COLUMNAS.' liquidaciones del rango.';
                $liqs = $liqs->take(self::MAX_COLUMNAS);
            }

            $cols = [];
            foreach ($liqs as $liq) {
                $cols[] = [
                    'clave' => 'L'.$liq->id,
                    'etiqueta' => trim((string) ($liq->descripcion ?: ('Liq. '.$liq->numero))),
                    'periodo' => (string) ($liq->periodo ?? ''),
                    'liquidacion_id' => (int) $liq->id,
                    'numero' => (int) $liq->numero,
                ];
            }

            return [$cols, $advertencia];
        }

        $periodos = $this->expandirPeriodos(
            (string) ($filtros['periodo_desde'] ?? ''),
            (string) ($filtros['periodo_hasta'] ?? '')
        );
        if (count($periodos) > self::MAX_COLUMNAS) {
            $advertencia = 'Se muestran los primeros '.self::MAX_COLUMNAS.' períodos del rango.';
            $periodos = array_slice($periodos, 0, self::MAX_COLUMNAS);
        }

        $cols = [];
        foreach ($periodos as $p) {
            $cols[] = [
                'clave' => 'P'.$p,
                'etiqueta' => $this->etiquetaPeriodo($p),
                'periodo' => $p,
                'liquidacion_id' => null,
                'numero' => null,
            ];
        }

        return [$cols, $advertencia];
    }

    /**
     * @return list<string>
     */
    private function expandirPeriodos(string $desde, string $hasta): array
    {
        $desde = F::normalizarPeriodo($desde);
        $hasta = F::normalizarPeriodo($hasta);
        if ($desde === '' || $hasta === '') {
            return [];
        }

        $out = [];
        $cur = (int) $desde;
        $fin = (int) $hasta;
        $guard = 0;
        while ($cur <= $fin && $guard < 120) {
            $out[] = sprintf('%06d', $cur);
            $anio = intdiv($cur, 100);
            $mes = $cur % 100;
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $anio++;
            }
            $cur = $anio * 100 + $mes;
            $guard++;
        }

        return $out;
    }

    private function etiquetaPeriodo(string $yyyymm): string
    {
        if (! preg_match('/^(\d{4})(\d{2})$/', $yyyymm, $m)) {
            return $yyyymm;
        }
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes = (int) $m[2];

        return ($meses[$mes] ?? $m[2]).'/'.$m[1];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Empleado_Sueldos> keyed by id
     */
    private function empleadosPermitidos(array $filtros): Collection
    {
        $empresaIds = $this->empresaIdsDesdeFiltros($filtros);
        $q = Empleado_Sueldos::query()
            ->with([
                'categoria:id,codigo,descripcion',
                'centrocosto:id,codigo,nombre',
                'sindicato:id,codigo,descripcion',
            ]);
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }

        $legDesde = $filtros['legajo_desde'] ?? null;
        $legHasta = $filtros['legajo_hasta'] ?? null;
        if ($legDesde !== null) {
            $q->where('legajo', '>=', (int) $legDesde);
        }
        if ($legHasta !== null) {
            $q->where('legajo', '<=', (int) $legHasta);
        }

        $orden = (string) ($filtros['orden'] ?? F::ORDEN_LEGAJO);
        if ($orden === F::ORDEN_CCOSTO) {
            $this->aplicarRangoPorCodigo(
                $q,
                'centrocosto_id',
                Centrocosto::class,
                'codigo',
                $filtros['centrocosto_id_desde'] ?? null,
                $filtros['centrocosto_id_hasta'] ?? null
            );
        } elseif ($orden === F::ORDEN_CATEGORIA) {
            $this->aplicarRangoPorCodigo(
                $q,
                'categoria_id',
                Categoria_Sueldos::class,
                'codigo',
                $filtros['categoria_id_desde'] ?? null,
                $filtros['categoria_id_hasta'] ?? null
            );
        } elseif ($orden === F::ORDEN_SINDICATO) {
            $this->aplicarRangoPorCodigo(
                $q,
                'sindicato_id',
                Sindicato_Sueldos::class,
                'codigo',
                $filtros['sindicato_id_desde'] ?? null,
                $filtros['sindicato_id_hasta'] ?? null
            );
        }

        /** @var Collection<int, Empleado_Sueldos> $emps */
        $emps = $q->get()->keyBy('id');

        $filtro = (string) ($filtros['filtro_empleados'] ?? F::FILTRO_SIN);
        if ($filtro === F::FILTRO_SIN || $emps->isEmpty()) {
            return $emps;
        }

        $fecha = (string) ($filtros['fecha_filtro'] ?? date('Y-m-d'));
        $okIds = $this->idsPorHistoriaIngreso($emps->keys()->all(), $filtro, $fecha);

        return $emps->only($okIds);
    }

    /**
     * @param  list<int>  $empleadoIds
     * @return list<int>
     */
    private function idsPorHistoriaIngreso(array $empleadoIds, string $filtro, string $fecha): array
    {
        if ($empleadoIds === []) {
            return [];
        }

        $rows = Empleado_Ingreso_Sueldos::query()
            ->whereIn('empleado_id', $empleadoIds)
            ->orderBy('empleado_id')
            ->orderBy('fecha_ingreso')
            ->get(['empleado_id', 'fecha_ingreso', 'fecha_egreso']);

        $ok = [];
        foreach ($empleadoIds as $empId) {
            $hist = $rows->where('empleado_id', $empId);
            $pasa = false;
            if ($hist->isEmpty()) {
                // Sin historia: cae al estado de la ficha (fecha_ingreso / fecha_egreso del empleado).
                continue;
            }
            foreach ($hist as $h) {
                $ing = $h->fecha_ingreso?->format('Y-m-d');
                $egr = $h->fecha_egreso?->format('Y-m-d');
                if ($filtro === F::FILTRO_ACTIVOS) {
                    if ($ing !== null && $ing <= $fecha && ($egr === null || $egr >= $fecha)) {
                        $pasa = true;
                        break;
                    }
                } elseif ($filtro === F::FILTRO_BAJAS) {
                    if ($egr !== null && $egr !== '' && $egr <= $fecha) {
                        $pasa = true;
                        break;
                    }
                }
            }
            if ($pasa) {
                $ok[] = (int) $empId;
            }
        }

        // Empleados sin historia: filtro por fechas de ficha.
        $sinHist = array_values(array_diff($empleadoIds, $rows->pluck('empleado_id')->unique()->all()));
        if ($sinHist !== []) {
            $fichas = Empleado_Sueldos::query()
                ->whereIn('id', $sinHist)
                ->get(['id', 'fecha_ingreso', 'fecha_egreso']);
            foreach ($fichas as $f) {
                $ing = $f->fecha_ingreso?->format('Y-m-d');
                $egr = $f->fecha_egreso?->format('Y-m-d');
                if ($filtro === F::FILTRO_ACTIVOS) {
                    if (($ing === null || $ing <= $fecha) && ($egr === null || $egr >= $fecha)) {
                        $ok[] = (int) $f->id;
                    }
                } elseif ($filtro === F::FILTRO_BAJAS) {
                    if ($egr !== null && $egr <= $fecha) {
                        $ok[] = (int) $f->id;
                    }
                }
            }
        }

        return $ok;
    }

    /**
     * Filtra empleados cuyo maestro relacionado tiene código entre el de los IDs elegidos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Sueldos\Empleado_Sueldos>  $q
     * @param  class-string  $modelo
     */
    private function aplicarRangoPorCodigo($q, string $fk, string $modelo, string $campoCodigo, mixed $idDesde, mixed $idHasta): void
    {
        $codDesde = null;
        $codHasta = null;
        if ($idDesde !== null) {
            $codDesde = $modelo::query()->whereKey((int) $idDesde)->value($campoCodigo);
        }
        if ($idHasta !== null) {
            $codHasta = $modelo::query()->whereKey((int) $idHasta)->value($campoCodigo);
        }
        if ($codDesde === null && $codHasta === null) {
            return;
        }

        $ids = $modelo::query()
            ->when($codDesde !== null, fn ($qq) => $qq->where($campoCodigo, '>=', $codDesde))
            ->when($codHasta !== null, fn ($qq) => $qq->where($campoCodigo, '<=', $codHasta))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->whereIn($fk, $ids);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array{clave: string, etiqueta: string, periodo: string, liquidacion_id: int|null, numero: int|null}>  $columnas
     * @param  list<int>  $empleadoIds
     * @return array<string, array{empleado_id: int, concepto_codigo: int, concepto_descripcion: string, cols: array<string, array{importe: float, cantidad: float}>}>
     */
    private function agregarDetalles(array $filtros, array $columnas, array $empleadoIds): array
    {
        if ($empleadoIds === [] || $columnas === []) {
            return [];
        }

        $empresaIds = $this->empresaIdsDesdeFiltros($filtros);
        $forma = (string) ($filtros['forma'] ?? F::FORMA_PERIODO);
        $conceptoDesde = $filtros['concepto_desde'] ?? null;
        $conceptoHasta = $filtros['concepto_hasta'] ?? null;

        $q = DB::table('liquidacion_detalle_sueldos as d')
            ->join('liquidacion_sueldos as l', 'l.id', '=', 'd.liquidacion_id')
            ->whereIn('d.empleado_id', $empleadoIds)
            ->where('l.simulacion', false)
            ->whereNotNull('d.concepto_codigo');

        if ($empresaIds !== []) {
            $q->whereIn('l.empresa_id', $empresaIds);
        }
        if ($conceptoDesde !== null) {
            $q->where('d.concepto_codigo', '>=', (int) $conceptoDesde);
        }
        if ($conceptoHasta !== null) {
            $q->where('d.concepto_codigo', '<=', (int) $conceptoHasta);
        }

        $selectCols = [
            'd.empleado_id',
            'd.concepto_id',
            'd.concepto_codigo',
            'd.concepto_descripcion',
            'd.columna',
            'd.importe',
            'd.cantidad',
            'l.id as liquidacion_id',
            'l.periodo',
        ];

        if ($forma === F::FORMA_LIQUIDACION) {
            $liqIds = array_values(array_filter(array_map(
                fn ($c) => $c['liquidacion_id'],
                $columnas
            )));
            $q->whereIn('l.id', $liqIds);
            $q->select($selectCols);
        } else {
            $periodos = array_values(array_unique(array_map(fn ($c) => $c['periodo'], $columnas)));
            $q->whereIn('l.periodo', $periodos);
            $q->select($selectCols);
        }

        $agg = [];
        foreach ($q->cursor() as $row) {
            $empId = (int) $row->empleado_id;
            $cod = (int) $row->concepto_codigo;
            $claveFila = $empId.'|'.$cod;

            if ($forma === F::FORMA_LIQUIDACION) {
                $claveCol = 'L'.(int) $row->liquidacion_id;
            } else {
                $claveCol = 'P'.(string) $row->periodo;
            }

            if (! isset($agg[$claveFila])) {
                $agg[$claveFila] = [
                    'empleado_id' => $empId,
                    'concepto_id' => (int) ($row->concepto_id ?? 0),
                    'concepto_codigo' => $cod,
                    'concepto_descripcion' => (string) ($row->concepto_descripcion ?? ''),
                    'cols' => [],
                ];
            } elseif ((int) ($agg[$claveFila]['concepto_id'] ?? 0) <= 0 && (int) ($row->concepto_id ?? 0) > 0) {
                $agg[$claveFila]['concepto_id'] = (int) $row->concepto_id;
            }

            if (! isset($agg[$claveFila]['cols'][$claveCol])) {
                $agg[$claveFila]['cols'][$claveCol] = ['importe' => 0.0, 'cantidad' => 0.0];
            }

            $importe = (float) $row->importe;
            if ((string) $row->columna === 'descuento') {
                $importe = -$importe;
            }

            $agg[$claveFila]['cols'][$claveCol]['importe'] += $importe;
            $agg[$claveFila]['cols'][$claveCol]['cantidad'] += (float) $row->cantidad;

            $desc = trim((string) ($row->concepto_descripcion ?? ''));
            if ($desc !== '' && $agg[$claveFila]['concepto_descripcion'] === '') {
                $agg[$claveFila]['concepto_descripcion'] = $desc;
            }
        }

        return $agg;
    }

    /**
     * @param  array<string, array{empleado_id: int, concepto_codigo: int, concepto_descripcion: string, cols: array<string, array{importe: float, cantidad: float}>}>  $agg
     * @param  Collection<int, Empleado_Sueldos>  $empleados
     * @param  list<array{clave: string, etiqueta: string, periodo: string, liquidacion_id: int|null, numero: int|null}>  $columnas
     * @return list<array<string, mixed>>
     */
    private function armarFilas(array $agg, Collection $empleados, array $columnas, string $orden, string $formato): array
    {
        $items = [];
        foreach ($agg as $dato) {
            $emp = $empleados->get($dato['empleado_id']);
            if (! $emp) {
                continue;
            }

            $valores = [];
            $totImp = 0.0;
            $totCant = 0.0;
            $tiene = false;
            foreach ($columnas as $col) {
                $celda = $dato['cols'][$col['clave']] ?? ['importe' => 0.0, 'cantidad' => 0.0];
                $imp = round((float) $celda['importe'], 2);
                $cant = round((float) $celda['cantidad'], 2);
                if (abs($imp) > 0.00001 || abs($cant) > 0.00001) {
                    $tiene = true;
                }
                $valores[$col['clave']] = ['importe' => $imp, 'cantidad' => $cant];
                $totImp += $imp;
                $totCant += $cant;
            }
            if (! $tiene) {
                continue;
            }

            $grupoId = 0;
            $grupoLabel = '';
            $grupoCodigo = '';
            $grupoTipo = '';
            if ($orden === F::ORDEN_CCOSTO) {
                $grupoId = (int) ($emp->centrocosto_id ?? 0);
                $grupoTipo = 'ccosto';
                $grupoCodigo = (string) ($emp->centrocosto->codigo ?? '');
                $grupoLabel = trim(($grupoCodigo !== '' ? $grupoCodigo.' ' : '').(string) ($emp->centrocosto->nombre ?? 'Sin centro'));
            } elseif ($orden === F::ORDEN_CATEGORIA) {
                $grupoId = (int) ($emp->categoria_id ?? 0);
                $grupoTipo = 'categoria';
                $grupoCodigo = (string) ($emp->categoria->codigo ?? $grupoId);
                $grupoLabel = trim($grupoCodigo.' '.(string) ($emp->categoria->descripcion ?? 'Sin categoría'));
            } elseif ($orden === F::ORDEN_SINDICATO) {
                $grupoId = (int) ($emp->sindicato_id ?? 0);
                $grupoTipo = 'sindicato';
                $grupoCodigo = (string) ($emp->sindicato->codigo ?? $grupoId);
                $grupoLabel = trim($grupoCodigo.' '.(string) ($emp->sindicato->descripcion ?? 'Sin sindicato'));
            }

            $items[] = [
                'tipo' => 'dato',
                'grupo_id' => $grupoId,
                'grupo_tipo' => $grupoTipo,
                'grupo_label' => $grupoLabel,
                'grupo_sort' => mb_strtoupper($grupoLabel),
                'empleado_id' => (int) $emp->id,
                'legajo' => (int) $emp->legajo,
                'nombre' => (string) $emp->nombre,
                'fecha_ingreso' => $emp->fecha_ingreso?->format('d/m/Y') ?? '',
                'centrocosto_id' => (int) ($emp->centrocosto_id ?? 0),
                'centrocosto' => (string) ($emp->centrocosto->nombre ?? ''),
                'categoria_id' => (int) ($emp->categoria_id ?? 0),
                'categoria' => (string) ($emp->categoria->descripcion ?? ''),
                'sindicato_id' => (int) ($emp->sindicato_id ?? 0),
                'sindicato' => (string) ($emp->sindicato->descripcion ?? ''),
                'concepto_id' => (int) ($dato['concepto_id'] ?? 0),
                'concepto_codigo' => (int) $dato['concepto_codigo'],
                'concepto_descripcion' => (string) $dato['concepto_descripcion'],
                'valores' => $valores,
                'total_importe' => round($totImp, 2),
                'total_cantidad' => round($totCant, 2),
                'diferencia_importe' => count($columnas) === 2
                    ? round(($valores[$columnas[1]['clave']]['importe'] ?? 0) - ($valores[$columnas[0]['clave']]['importe'] ?? 0), 2)
                    : 0.0,
                'diferencia_cantidad' => count($columnas) === 2
                    ? round(($valores[$columnas[1]['clave']]['cantidad'] ?? 0) - ($valores[$columnas[0]['clave']]['cantidad'] ?? 0), 2)
                    : 0.0,
            ];
        }

        usort($items, function ($a, $b) use ($orden) {
            if ($orden !== F::ORDEN_LEGAJO) {
                $c = strcmp($a['grupo_sort'], $b['grupo_sort']);
                if ($c !== 0) {
                    return $c;
                }
            }
            if ($a['legajo'] !== $b['legajo']) {
                return $a['legajo'] <=> $b['legajo'];
            }

            return $a['concepto_codigo'] <=> $b['concepto_codigo'];
        });

        if ($orden === F::ORDEN_LEGAJO) {
            return $items;
        }

        return $this->insertarCortesGrupo($items, $columnas);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{clave: string}>  $columnas
     * @return list<array<string, mixed>>
     */
    private function insertarCortesGrupo(array $items, array $columnas): array
    {
        $out = [];
        $grupoActual = null;
        $buffer = [];

        $flush = function () use (&$out, &$buffer, &$grupoActual, $columnas) {
            if ($buffer === []) {
                return;
            }
            $out[] = [
                'tipo' => 'grupo',
                'grupo_id' => (int) ($buffer[0]['grupo_id'] ?? 0),
                'grupo_tipo' => (string) ($buffer[0]['grupo_tipo'] ?? ''),
                'grupo_label' => (string) ($buffer[0]['grupo_label'] ?? ''),
                'es_header' => true,
            ];
            foreach ($buffer as $row) {
                $out[] = $row;
            }
            $tot = $this->sumarFilasDatos($buffer, $columnas);
            $out[] = array_merge($tot, [
                'tipo' => 'total_grupo',
                'nombre' => 'Total '.($buffer[0]['grupo_label'] ?? ''),
                'es_total' => true,
            ]);
            $buffer = [];
        };

        foreach ($items as $row) {
            $gid = $row['grupo_id'] ?? null;
            if ($grupoActual !== null && $gid !== $grupoActual) {
                $flush();
            }
            $grupoActual = $gid;
            $buffer[] = $row;
        }
        $flush();

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave: string}>  $columnas
     * @return array<string, mixed>
     */
    private function sumarFilasDatos(array $filas, array $columnas): array
    {
        $valores = [];
        foreach ($columnas as $col) {
            $valores[$col['clave']] = ['importe' => 0.0, 'cantidad' => 0.0];
        }
        $totImp = 0.0;
        $totCant = 0.0;
        $difImp = 0.0;
        $difCant = 0.0;

        foreach ($filas as $f) {
            if (($f['tipo'] ?? '') !== 'dato') {
                continue;
            }
            foreach ($columnas as $col) {
                $k = $col['clave'];
                $valores[$k]['importe'] += (float) ($f['valores'][$k]['importe'] ?? 0);
                $valores[$k]['cantidad'] += (float) ($f['valores'][$k]['cantidad'] ?? 0);
            }
            $totImp += (float) ($f['total_importe'] ?? 0);
            $totCant += (float) ($f['total_cantidad'] ?? 0);
            $difImp += (float) ($f['diferencia_importe'] ?? 0);
            $difCant += (float) ($f['diferencia_cantidad'] ?? 0);
        }

        foreach ($valores as $k => $v) {
            $valores[$k]['importe'] = round($v['importe'], 2);
            $valores[$k]['cantidad'] = round($v['cantidad'], 2);
        }

        return [
            'valores' => $valores,
            'total_importe' => round($totImp, 2),
            'total_cantidad' => round($totCant, 2),
            'diferencia_importe' => round($difImp, 2),
            'diferencia_cantidad' => round($difCant, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave: string}>  $columnas
     * @return array<string, mixed>
     */
    private function calcularTotales(array $filas, array $columnas): array
    {
        $datos = array_values(array_filter($filas, fn ($f) => ($f['tipo'] ?? '') === 'dato'));
        $sum = $this->sumarFilasDatos($datos, $columnas);

        return array_merge($sum, [
            'filas_dato' => count($datos),
            'empleados' => count(array_unique(array_map(fn ($f) => $f['legajo'] ?? 0, $datos))),
            'conceptos' => count(array_unique(array_map(fn ($f) => $f['concepto_codigo'] ?? 0, $datos))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function totalesVacios(): array
    {
        return [
            'valores' => [],
            'total_importe' => 0.0,
            'total_cantidad' => 0.0,
            'diferencia_importe' => 0.0,
            'diferencia_cantidad' => 0.0,
            'filas_dato' => 0,
            'empleados' => 0,
            'conceptos' => 0,
        ];
    }

    /**
     * Catálogos para combos del formulario.
     *
     * @return array{centrocostos: Collection, categorias: Collection, sindicatos: Collection}
     */
    public function catalogos(): array
    {
        return [
            'centrocostos' => Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'categorias' => Categoria_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'sindicatos' => Sindicato_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    private function empresaIdsDesdeFiltros(array $filtros): array
    {
        $ids = collect($filtros['empresa_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($ids === [] && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            $ids = [(int) $filtros['empresa_id']];
        }

        return $ids;
    }
}
