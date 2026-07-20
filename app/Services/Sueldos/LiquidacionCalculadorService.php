<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Acumulador_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Acumulador_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\Formula\ContextoLiquidacion;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\Formula\ParametroSueldosResolver;
use App\Support\Sueldos\Formula\RastreadorFormula;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de calculo de una corrida de liquidacion. Recorre la secuencia de
 * conceptos por cada empleado, evalua las formulas con EvaluadorFormula y
 * persiste recibos + detalles. Soporta un modo de rastreo (depurador) que no
 * persiste y devuelve el arbol de calculo por concepto.
 */
class LiquidacionCalculadorService
{
    private EvaluadorFormula $motor;

    private GananciasPuenteLiquidacionService $puenteGanancias;

    private PlanCuotaLiquidacionService $planCuotas;

    /** Mapa tipo de concepto -> columna del recibo. */
    private const COLUMNA = [
        'remunerativo' => 'haber',
        'no_remunerativo' => 'haber',
        'asignacion' => 'haber',
        'descuento' => 'descuento',
        'aporte' => 'descuento',
        'retencion' => 'descuento',
        'neto' => 'neto',
        'informativo' => 'informativo',
        'contribucion' => 'informativo',
    ];

    public function __construct(
        GananciasPuenteLiquidacionService $puenteGanancias,
        PlanCuotaLiquidacionService $planCuotas
    ) {
        $this->motor = new EvaluadorFormula;
        $this->puenteGanancias = $puenteGanancias;
        $this->planCuotas = $planCuotas;
    }

    /**
     * Calcula y persiste la corrida completa. Devuelve un resumen.
     *
     * @return array{recibos: int, total_neto: float, total_remunerativo: float, sin_conceptos: bool}
     */
    public function calcular(Liquidacion_Sueldos $liquidacion): array
    {
        $conceptos = $this->conceptos();
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $empleados = $this->empleados($liquidacion);
        $parametros = $this->parametros($liquidacion);

        if ($conceptos->isEmpty()) {
            return ['recibos' => 0, 'total_neto' => 0.0, 'total_remunerativo' => 0.0, 'sin_conceptos' => true];
        }

        return DB::transaction(function () use ($liquidacion, $conceptos, $acumDefs, $overrides, $empleados, $parametros) {
            // Limpia resultado previo de esta corrida.
            Liquidacion_Detalle_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();
            Liquidacion_Recibo_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();
            Liquidacion_Acumulador_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();

            $periodoAnio = (int) ($liquidacion->periodo_anio ?: now()->year);
            $periodoMes = (int) ($liquidacion->periodo_mes ?: now()->month);
            $periodo = $periodoAnio * 100 + $periodoMes;
            $snapshots = [];
            $ahora = Carbon::now();

            $numeroRecibo = 0;
            $tot = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'neto' => 0.0];
            $planPendientes = [];

            foreach ($empleados as $emp) {
                $numeroRecibo++;
                $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);
                $lineas = $this->calcularEmpleado($ctx, $emp, $conceptos, $overrides, $liquidacion, $planPendientes);

                $rec = $this->totalesRecibo($lineas);
                $recibo = Liquidacion_Recibo_Sueldos::create([
                    'liquidacion_id' => $liquidacion->id,
                    'empleado_id' => $emp->id,
                    'legajo' => $emp->legajo,
                    'numero_recibo' => $numeroRecibo,
                    'apellido_nombre' => $emp->nombre,
                    'cuil' => $emp->cuil,
                    'categoria_id' => $emp->categoria_id,
                    'categoria_desc' => optional($emp->categoria)->descripcion,
                    'agrupamiento_id' => $emp->agrupamiento_id,
                    'lugartrabajo_id' => $emp->lugartrabajo_id,
                    'obrasocial_id' => $emp->obrasocial_id,
                    'sindicato_id' => $emp->sindicato_id,
                    'fecha_ingreso' => $emp->fecha_ingreso,
                    'sueldo_basico' => $emp->sueldo_basico,
                    'dias_trabajados' => (float) $ctx->variable('periodo.dias_trabajados'),
                    'dias_vacaciones' => 0,
                    'horas' => 0,
                    'total_remunerativo' => $rec['rem'],
                    'total_no_remunerativo' => $rec['norem'],
                    'total_bruto' => $rec['bruto'],
                    'total_descuentos' => $rec['desc'],
                    'total_aportes' => $rec['aportes'],
                    'total_contribuciones' => $rec['contrib'],
                    'total_asignaciones' => $rec['asig'],
                    'neto' => $rec['neto'],
                    'redondeo' => 0,
                    'neto_a_pagar' => $rec['neto'],
                    'estado' => 'calculado',
                ]);

                $nro = 0;
                foreach ($lineas as $l) {
                    $nro++;
                    Liquidacion_Detalle_Sueldos::create([
                        'recibo_id' => $recibo->id,
                        'liquidacion_id' => $liquidacion->id,
                        'empleado_id' => $emp->id,
                        'concepto_id' => $l['concepto_id'],
                        'concepto_codigo' => $l['codigo'],
                        'concepto_descripcion' => $l['descripcion'],
                        'tipo' => $l['tipo'],
                        'nro_linea' => $nro,
                        'columna' => $l['columna'],
                        'cantidad' => $l['cantidad'],
                        'valor' => $l['valor'],
                        'base_calculo' => 0,
                        'importe' => $l['importe'],
                        'remunerativo' => $l['tipo'] === 'remunerativo',
                        'va_recibo' => $l['va_recibo'],
                        'concepto_afip' => $l['concepto_afip'],
                        'leyenda' => $l['leyenda'],
                    ]);
                }

                $tot['rem'] += $rec['rem'];
                $tot['norem'] += $rec['norem'];
                $tot['desc'] += $rec['desc'];
                $tot['neto'] += $rec['neto'];

                // Snapshot de acumuladores para el historico (memoria hacia atras).
                foreach ($ctx->acumuladores() as $cod => $val) {
                    $snapshots[] = [
                        'empresa_id' => $liquidacion->empresa_id,
                        'empleado_id' => $emp->id,
                        'liquidacion_id' => $liquidacion->id,
                        'periodo' => $periodo,
                        'periodo_anio' => $periodoAnio,
                        'periodo_mes' => $periodoMes,
                        'tipo_corrida' => $liquidacion->tipo,
                        'codigo' => $cod,
                        'valor' => round((float) $val, 2),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }

            foreach (array_chunk($snapshots, 500) as $lote) {
                Liquidacion_Acumulador_Sueldos::insert($lote);
            }

            // Cuotas de planes (préstamos) calculadas: quedan pendientes hasta cerrar.
            $this->planCuotas->reemplazarPendientes((int) $liquidacion->id, $planPendientes);

            $liquidacion->update([
                'estado' => 'calculada',
                'fecha_calculo' => Carbon::now(),
                'cantidad_recibos' => $numeroRecibo,
                'total_remunerativo' => $tot['rem'],
                'total_no_remunerativo' => $tot['norem'],
                'total_bruto' => $tot['rem'] + $tot['norem'],
                'total_descuentos' => $tot['desc'],
                'total_neto' => $tot['neto'],
            ]);

            return [
                'recibos' => $numeroRecibo,
                'total_neto' => $tot['neto'],
                'total_remunerativo' => $tot['rem'],
                'sin_conceptos' => false,
            ];
        });
    }

    /**
     * Recorre la secuencia de conceptos para un empleado (sin persistir).
     *
     * @param  \Illuminate\Support\Collection<int, Concepto_Sueldos>  $conceptos
     * @param  array<int, array<string, array{signo: int, excluir: bool}>>  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function calcularEmpleado(
        ContextoLiquidacion $ctx,
        Empleado_Sueldos $emp,
        $conceptos,
        array $overrides = [],
        ?Liquidacion_Sueldos $liquidacion = null,
        ?array &$planPendientes = null
    ): array {
        $lineas = [];
        $gananciasListo = false;
        foreach ($conceptos as $c) {
            if (! $gananciasListo && $liquidacion && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $cantidad = $c->formula_cantidad ? $this->evalNum($ctx, $c->formula_cantidad, $c) : 1.0;
            $valor = $c->formula_valor ? $this->evalNum($ctx, $c->formula_valor, $c) : 0.0;
            $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

            $importe = $c->formula
                ? $this->evalNum($ctx, $c->formula, $c)
                : $cantidad * $valor;
            $importe = round($importe, 2);

            $ctx->registrarConcepto((int) $c->codigo, $importe);
            $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);

            if ($importe == 0.0 && ! $c->va_recibo) {
                continue;
            }
            if ($importe == 0.0 && $c->tipo !== 'informativo') {
                continue;
            }

            $lineas[] = [
                'concepto_id' => $c->id,
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'tipo' => $c->tipo,
                'columna' => self::COLUMNA[$c->tipo] ?? 'informativo',
                'cantidad' => round($cantidad, 4),
                'valor' => round($valor, 4),
                'importe' => $importe,
                'va_recibo' => (bool) $c->va_recibo,
                'concepto_afip' => $c->concepto_afip,
                'leyenda' => $c->leyenda_recibo,
            ];
        }

        // Planes de cuotas (préstamos): un concepto que se liquida N veces y cae solo.
        if ($liquidacion) {
            foreach ($this->planCuotas->lineasPlan($emp, $liquidacion, $ctx) as $pl) {
                $importe = round((float) $pl['importe'], 2);
                $ctx->registrarConcepto((int) $pl['codigo'], $importe);
                $ctx->aplicarAcumuladores((string) $pl['tipo'], $importe, []);

                $lineas[] = [
                    'concepto_id' => $pl['concepto_id'],
                    'codigo' => $pl['codigo'],
                    'descripcion' => $pl['descripcion'],
                    'tipo' => $pl['tipo'],
                    'columna' => self::COLUMNA[$pl['tipo']] ?? 'informativo',
                    'cantidad' => 1.0,
                    'valor' => $importe,
                    'importe' => $importe,
                    'va_recibo' => $pl['va_recibo'],
                    'concepto_afip' => $pl['concepto_afip'],
                    'leyenda' => $pl['leyenda'],
                ];

                if ($planPendientes !== null) {
                    $planPendientes[] = [
                        'plan_id' => $pl['plan_id'],
                        'numero_cuota' => $pl['numero_cuota'],
                        'periodo' => $pl['periodo'],
                        'importe' => $importe,
                        'empleado_id' => (int) $emp->id,
                    ];
                }
            }
        }

        return $lineas;
    }

    /**
     * Modo depurador: calcula un empleado devolviendo el rastro por concepto.
     * No persiste.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trazarEmpleado(Liquidacion_Sueldos $liquidacion, Empleado_Sueldos $emp): array
    {
        $conceptos = $this->conceptos();
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $parametros = $this->parametros($liquidacion);
        $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);

        $pasos = [];
        $gananciasListo = false;
        foreach ($conceptos as $c) {
            if (! $gananciasListo && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $cantidad = $c->formula_cantidad ? $this->evalNum($ctx, $c->formula_cantidad, $c) : 1.0;
            $valor = $c->formula_valor ? $this->evalNum($ctx, $c->formula_valor, $c) : 0.0;
            $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

            $rastro = null;
            if ($c->formula) {
                try {
                    [$importe, $rastro] = $this->motor->evaluarConRastro($c->formula, $ctx);
                    $importe = round((float) $importe, 2);
                } catch (FormulaException $e) {
                    $pasos[] = $this->pasoError($c, $e);

                    continue;
                }
            } else {
                $importe = round($cantidad * $valor, 2);
            }

            $ctx->registrarConcepto((int) $c->codigo, $importe);
            $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);

            $pasos[] = [
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'tipo' => $c->tipo,
                'formula' => $c->formula,
                'cantidad' => round($cantidad, 4),
                'valor' => round($valor, 4),
                'importe' => $importe,
                'error' => null,
                'rastro' => $rastro ? $rastro->arbol() : [],
                'rastro_texto' => $rastro ? $rastro->texto() : '',
                'acumuladores' => $ctx->acumuladores(),
            ];
        }

        // Cuotas de planes (préstamos) — se muestran pero no se persisten al trazar.
        foreach ($this->planCuotas->lineasPlan($emp, $liquidacion, $ctx) as $pl) {
            $importe = round((float) $pl['importe'], 2);
            $ctx->registrarConcepto((int) $pl['codigo'], $importe);
            $ctx->aplicarAcumuladores((string) $pl['tipo'], $importe, []);
            $pasos[] = [
                'codigo' => $pl['codigo'],
                'descripcion' => $pl['descripcion'],
                'tipo' => $pl['tipo'],
                'formula' => '(plan de cuotas) '.$pl['leyenda'],
                'cantidad' => 1,
                'valor' => $importe,
                'importe' => $importe,
                'error' => null,
                'rastro' => [],
                'rastro_texto' => $pl['leyenda'],
                'acumuladores' => $ctx->acumuladores(),
            ];
        }

        return $pasos;
    }

    /**
     * @return array<string, mixed>
     */
    private function pasoError(Concepto_Sueldos $c, FormulaException $e): array
    {
        return [
            'codigo' => $c->codigo,
            'descripcion' => $c->descripcion,
            'tipo' => $c->tipo,
            'formula' => $c->formula,
            'cantidad' => 0,
            'valor' => 0,
            'importe' => 0,
            'error' => $e->getMessage(),
            'rastro' => [],
            'rastro_texto' => '',
            'acumuladores' => [],
        ];
    }

    private function evalNum(ContextoLiquidacion $ctx, string $formula, Concepto_Sueldos $c): float
    {
        try {
            return (float) $this->motor->evaluar($formula, $ctx);
        } catch (FormulaException $e) {
            throw FormulaException::evaluacion(
                "Concepto {$c->codigo} ({$c->descripcion}): ".$e->getMessage()
            );
        }
    }

    private function formulaUsaGanancias(Concepto_Sueldos $c): bool
    {
        $blob = strtolower(
            ($c->formula ?? '').' '.($c->formula_cantidad ?? '').' '.($c->formula_valor ?? '')
        );

        return str_contains($blob, 'ganancias(') || str_contains($blob, 'ganancia_linea(');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<string, float>
     */
    private function totalesRecibo(array $lineas): array
    {
        $t = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'aportes' => 0.0, 'contrib' => 0.0, 'asig' => 0.0];
        foreach ($lineas as $l) {
            switch ($l['tipo']) {
                case 'remunerativo': $t['rem'] += $l['importe']; break;
                case 'no_remunerativo': $t['norem'] += $l['importe']; break;
                case 'asignacion': $t['asig'] += $l['importe']; break;
                case 'descuento': $t['desc'] += $l['importe']; break;
                case 'aporte': $t['desc'] += $l['importe']; $t['aportes'] += $l['importe']; break;
                case 'retencion': $t['desc'] += $l['importe']; break;
                case 'contribucion': $t['contrib'] += $l['importe']; break;
            }
        }
        $t['bruto'] = $t['rem'] + $t['norem'];
        $t['neto'] = round($t['rem'] + $t['norem'] + $t['asig'] - $t['desc'], 2);

        return $t;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Concepto_Sueldos>
     */
    private function conceptos()
    {
        return Concepto_Sueldos::query()
            ->where('activo', true)
            ->where('momento', '!=', 'no_liquida')
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    /**
     * @return array<int, array{codigo: string, tipos: array<int,string>, signo: int}>
     */
    private function acumuladoresDef(?int $empresaId): array
    {
        $defs = Acumulador_Sueldos::query()
            ->where('activo', true)
            ->where(function ($q) use ($empresaId) {
                $q->whereNull('empresa_id');
                if ($empresaId) {
                    $q->orWhere('empresa_id', $empresaId);
                }
            })
            ->orderBy('orden')
            ->get();

        return $defs->map(fn ($a) => [
            'codigo' => (string) $a->codigo,
            'tipos' => $a->tipos_incluye ?? [],
            'signo' => (int) $a->signo,
        ])->all();
    }

    /**
     * Overrides concepto->acumulador: [concepto_id => ['ACUMCOD' => ['signo','excluir']]].
     *
     * @return array<int, array<string, array{signo: int, excluir: bool}>>
     */
    private function overridesConceptoAcumulador(): array
    {
        $map = [];
        try {
            $filas = DB::table('concepto_acumulador_sueldos as ca')
                ->join('acumulador_sueldos as a', 'a.id', '=', 'ca.acumulador_id')
                ->get(['ca.concepto_id', 'a.codigo', 'ca.signo', 'ca.excluir']);
            foreach ($filas as $f) {
                $map[(int) $f->concepto_id][strtoupper((string) $f->codigo)] = [
                    'signo' => (int) $f->signo,
                    'excluir' => (bool) $f->excluir,
                ];
            }
        } catch (\Throwable $e) {
            // Tabla aun no migrada: sin overrides.
        }

        return $map;
    }

    private function parametros(Liquidacion_Sueldos $liq): ParametroSueldosResolver
    {
        $fecha = $liq->fecha_liquidacion
            ? ($liq->fecha_liquidacion instanceof Carbon ? $liq->fecha_liquidacion : Carbon::parse($liq->fecha_liquidacion))
            : Carbon::now();

        return new ParametroSueldosResolver($liq->empresa_id, $fecha->toDateString());
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empleado_Sueldos>
     */
    private function empleados(Liquidacion_Sueldos $liq)
    {
        $query = Empleado_Sueldos::query()
            ->where('empresa_id', $liq->empresa_id)
            ->with(['categoria', 'motivoegreso'])
            ->orderBy('legajo');

        // Liquidacion final: empleados dados de baja (o con fecha de egreso) en el periodo.
        // El resto de corridas: solo activos.
        if ($liq->tipo === 'final') {
            $query->where(function ($q) use ($liq) {
                $q->where('estado', EmpleadoEstados::BAJA)
                    ->orWhereNotNull('fecha_egreso');
            });
            if ($liq->periodo_desde && $liq->periodo_hasta) {
                $query->whereBetween('fecha_egreso', [$liq->periodo_desde, $liq->periodo_hasta]);
            } elseif ($liq->periodo_anio && $liq->periodo_mes) {
                $query->whereYear('fecha_egreso', (int) $liq->periodo_anio)
                    ->whereMonth('fecha_egreso', (int) $liq->periodo_mes);
            }
            if ($liq->motivoegreso_id) {
                $query->where('motivoegreso_id', (int) $liq->motivoegreso_id);
            }
        } else {
            $query->where('estado', EmpleadoEstados::ACTIVO);
        }

        $filtros = $this->filtros($liq);
        if (! empty($filtros['empleado_ids']) && is_array($filtros['empleado_ids'])) {
            $query->whereIn('id', $filtros['empleado_ids']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $query->where('agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Liquidacion_Sueldos $liq): array
    {
        if (empty($liq->filtros_json)) {
            return [];
        }
        if (is_array($liq->filtros_json)) {
            return $liq->filtros_json;
        }
        $dec = json_decode((string) $liq->filtros_json, true);

        return is_array($dec) ? $dec : [];
    }
}
