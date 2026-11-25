<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Acumulador_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Acumulador_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Error_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Illuminate\Support\Facades\Log;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Sueldos\ConceptoAnitaMapeo;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\EmpleadoBajaSupport;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\Formula\ContextoLiquidacion;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\Formula\ParametroSueldosResolver;
use App\Support\Sueldos\Formula\RastreadorFormula;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use App\Support\Sueldos\NovedadSueldosVigencia;
use App\Support\Sueldos\ReciboBaseCalculoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    private ConceptoSetEfectivoService $setEfectivo;

    private ReciboMultiempresaService $multiempresa;

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
        // Contribución empleador: sección CE Anexo III; no suma a bruto/neto.
        'contribucion' => 'contribucion',
    ];

    public function __construct(
        GananciasPuenteLiquidacionService $puenteGanancias,
        PlanCuotaLiquidacionService $planCuotas,
        ConceptoSetEfectivoService $setEfectivo,
        ReciboMultiempresaService $multiempresa
    ) {
        $this->motor = new EvaluadorFormula;
        $this->puenteGanancias = $puenteGanancias;
        $this->planCuotas = $planCuotas;
        $this->setEfectivo = $setEfectivo;
        $this->multiempresa = $multiempresa;
    }

    /**
     * Calcula y persiste la corrida completa. Devuelve un resumen.
     *
     * @return array{
     *   recibos: int,
     *   total_neto: float,
     *   total_remunerativo: float,
     *   sin_conceptos: bool,
     *   errores_formula?: list<array<string, mixed>>,
     *   errores_formula_count?: int,
     *   errores_count?: int,
     *   egresos_periodo?: list<array<string, mixed>>
     * }
     */
    public function calcular(Liquidacion_Sueldos $liquidacion): array
    {
        $conceptos = $this->conceptos();
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $empleados = $this->empleados($liquidacion);
        $parametros = $this->parametros($liquidacion);
        $egresosAviso = $this->egresosEnPeriodoParaAviso($empleados, $liquidacion);

        if ($conceptos->isEmpty()) {
            return [
                'recibos' => 0,
                'total_neto' => 0.0,
                'total_remunerativo' => 0.0,
                'sin_conceptos' => true,
                'egresos_periodo' => $egresosAviso,
            ];
        }

        return DB::transaction(function () use ($liquidacion, $conceptos, $acumDefs, $overrides, $empleados, $parametros, $egresosAviso) {
            // Regenera toda la corrida (motor + eventual import auxconf de paridad).
            EloquentAuditDeleteSupport::each(
                Liquidacion_Detalle_Sueldos::query()->where('liquidacion_id', $liquidacion->id)
            );
            EloquentAuditDeleteSupport::each(
                Liquidacion_Recibo_Sueldos::query()->where('liquidacion_id', $liquidacion->id)
            );
            Liquidacion_Acumulador_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();
            Liquidacion_Error_Sueldos::where('liquidacion_id', $liquidacion->id)->delete();

            $periodoAnio = (int) ($liquidacion->periodo_anio ?: now()->year);
            $periodoMes = (int) ($liquidacion->periodo_mes ?: now()->month);
            $periodo = $periodoAnio * 100 + $periodoMes;
            $snapshots = [];
            $ahora = Carbon::now();

            $numeroRecibo = 0;
            $tot = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'neto' => 0.0];
            $planPendientes = [];
            $erroresFormula = [];
            $erroresPersistir = [];

            foreach ($empleados as $emp) {
                $setInfo = null;
                $conceptosEmp = $this->conceptosParaEmpleado($conceptos, $emp, $liquidacion, $setInfo);
                if ($conceptosEmp->isEmpty()) {
                    $erroresPersistir[] = $this->filaErrorLiquidacion(
                        $liquidacion, $emp, 'omitido',
                        'Sin conceptos aplicables para este empleado en la corrida.',
                        $ahora
                    );
                    continue;
                }

                $ctx = null;
                $lineas = [];
                $erroresEmp = [];
                $planAntes = count($planPendientes);

                try {
                    // Savepoint: un empleado roto no revierte el resto de la corrida.
                    DB::transaction(function () use (
                        &$ctx, &$lineas, &$erroresEmp, &$planPendientes,
                        $emp, $conceptosEmp, $overrides, $liquidacion, $setInfo,
                        $acumDefs, $parametros
                    ) {
                        $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);
                        $lineas = $this->calcularEmpleado(
                            $ctx, $emp, $conceptosEmp, $overrides, $liquidacion, $planPendientes,
                            $setInfo['meta'] ?? [],
                            $erroresEmp
                        );
                    });
                } catch (\Throwable $e) {
                    // Deshace cuotas parciales de este empleado si el savepoint revirtió mid-cálculo.
                    if (count($planPendientes) > $planAntes) {
                        $planPendientes = array_slice($planPendientes, 0, $planAntes);
                    }
                    $msg = $e->getMessage();
                    $erroresPersistir[] = $this->filaErrorLiquidacion(
                        $liquidacion, $emp, 'empleado', $msg
                    );
                    Log::warning('LiquidacionCalculador: error por empleado', [
                        'liquidacion_id' => $liquidacion->id,
                        'empleado_id' => $emp->id,
                        'legajo' => $emp->legajo,
                        'error' => $msg,
                        'exception' => get_class($e),
                    ]);
                    continue;
                }

                foreach ($erroresEmp as $ef) {
                    $erroresFormula[] = $ef;
                    $pos = isset($ef['formula_pos']) ? (int) $ef['formula_pos'] : FormulaException::extraerPosicion($ef['error'] ?? null);
                    $erroresPersistir[] = [
                        'liquidacion_id' => $liquidacion->id,
                        'empleado_id' => $emp->id,
                        'legajo' => $emp->legajo !== null ? (int) $emp->legajo : null,
                        'apellido_nombre' => (string) ($emp->nombre ?? ''),
                        'concepto_id' => isset($ef['concepto_id']) ? (int) $ef['concepto_id'] : null,
                        'concepto_codigo' => isset($ef['concepto']) ? (int) $ef['concepto'] : null,
                        'concepto_descripcion' => (string) ($ef['concepto_descripcion'] ?? ''),
                        'formula_pos' => $pos >= 0 ? $pos : null,
                        'formula_campo' => (string) ($ef['formula_campo'] ?? 'formula'),
                        'etapa' => 'formula',
                        'mensaje' => (string) ($ef['error'] ?? 'Error de fórmula'),
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                // Sin líneas (fórmulas en 0 / sin novedad ni saldo): no arma recibo vacío.
                if ($lineas === []) {
                    continue;
                }

                try {
                    DB::transaction(function () use (
                        &$numeroRecibo, &$tot, &$snapshots, &$ctx, $lineas, $emp,
                        $liquidacion, $periodo, $periodoAnio, $periodoMes, $ahora
                    ) {
                        $numeroRecibo++;
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
                            'redondeo' => $this->importeConcepto($lineas, ConceptoAnitaMapeo::CODIGO_AJUSTE_REDONDEO),
                            'neto_a_pagar' => $rec['neto'],
                            'estado' => 'calculado',
                            ...self::atributosOrigenReciboMotor($emp),
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
                                'base_calculo' => $l['base_calculo'] ?? null,
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
                    });
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    $erroresPersistir[] = $this->filaErrorLiquidacion(
                        $liquidacion, $emp, 'recibo', $msg, $ahora
                    );
                    Log::warning('LiquidacionCalculador: error al persistir recibo', [
                        'liquidacion_id' => $liquidacion->id,
                        'empleado_id' => $emp->id,
                        'legajo' => $emp->legajo,
                        'error' => $msg,
                        'exception' => get_class($e),
                    ]);
                }
            }

            foreach (array_chunk($snapshots, 500) as $lote) {
                Liquidacion_Acumulador_Sueldos::insert($lote);
            }

            foreach (array_chunk($erroresPersistir, 200) as $loteErr) {
                Liquidacion_Error_Sueldos::insert($loteErr);
            }

            // Cuotas de planes (préstamos) calculadas: quedan pendientes hasta cerrar.
            $this->planCuotas->reemplazarPendientes((int) $liquidacion->id, $planPendientes);

            \App\Support\Sueldos\LiquidacionDetalleTotalesSupport::renumerarRecibos((int) $liquidacion->id);
            \App\Support\Sueldos\LiquidacionDetalleTotalesSupport::recalcularCabecera($liquidacion->fresh());
            $liquidacion->refresh();
            $liquidacion->update([
                'estado' => 'calculada',
                'fecha_calculo' => Carbon::now(),
            ]);

            $erroresCount = count($erroresPersistir);

            return [
                'recibos' => (int) $liquidacion->cantidad_recibos,
                'total_neto' => (float) $liquidacion->total_neto,
                'total_remunerativo' => (float) $liquidacion->total_remunerativo,
                'sin_conceptos' => false,
                'errores_formula' => $erroresFormula,
                'errores_formula_count' => count($erroresFormula),
                'errores_count' => $erroresCount,
                'egresos_periodo' => $egresosAviso,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function filaErrorLiquidacion(
        Liquidacion_Sueldos $liquidacion,
        Empleado_Sueldos $emp,
        string $etapa,
        string $mensaje,
        ?Carbon $ahora = null
    ): array {
        $ahora = $ahora ?? Carbon::now();

        return [
            'liquidacion_id' => $liquidacion->id,
            'empleado_id' => $emp->id,
            'legajo' => $emp->legajo !== null ? (int) $emp->legajo : null,
            'apellido_nombre' => (string) ($emp->nombre ?? ''),
            'concepto_id' => null,
            'concepto_codigo' => null,
            'concepto_descripcion' => null,
            'formula_pos' => null,
            'formula_campo' => null,
            'etapa' => $etapa,
            'mensaje' => $mensaje,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }

    /**
     * Legajos del universo con fecha de egreso en el período (provisoria o definitiva).
     *
     * @param  \Illuminate\Support\Collection<int, Empleado_Sueldos>  $empleados
     * @return list<array{legajo:int|string, nombre:string, fecha_egreso:string, modo:string}>
     */
    private function egresosEnPeriodoParaAviso($empleados, Liquidacion_Sueldos $liq): array
    {
        $desde = $liq->periodo_desde;
        $hasta = $liq->periodo_hasta;
        if (! $desde || ! $hasta) {
            if ($liq->periodo_anio && $liq->periodo_mes) {
                $desde = Carbon::create((int) $liq->periodo_anio, (int) $liq->periodo_mes, 1)->toDateString();
                $hasta = Carbon::parse($desde)->endOfMonth()->toDateString();
            } else {
                return [];
            }
        }

        $out = [];
        foreach ($empleados as $emp) {
            if (! EmpleadoBajaSupport::egresoEnPeriodo($emp, $desde, $hasta)) {
                continue;
            }
            $out[] = [
                'legajo' => $emp->legajo,
                'nombre' => (string) ($emp->nombre ?? ''),
                'fecha_egreso' => Carbon::parse($emp->fecha_egreso)->format('d/m/Y'),
                'modo' => EmpleadoBajaSupport::esBajaProvisoria($emp) ? 'provisoria' : 'definitiva',
            ];
        }

        return $out;
    }

    /**
     * Recorre la secuencia de conceptos para un empleado (sin persistir).
     *
     * @param  \Illuminate\Support\Collection<int, Concepto_Sueldos>  $conceptos
     * @param  array<int, array<string, array{signo: int, excluir: bool}>>  $overrides
     * @param  array<int, array{origen?: string, detalle?: string, origen_label?: string}>  $origenMeta
     * @param  list<array{legajo: int|string, concepto: int, concepto_descripcion?: string, error: string}>  $erroresFormula
     * @return array<int, array<string, mixed>>
     */
    private function calcularEmpleado(
        ContextoLiquidacion $ctx,
        Empleado_Sueldos $emp,
        $conceptos,
        array $overrides = [],
        ?Liquidacion_Sueldos $liquidacion = null,
        ?array &$planPendientes = null,
        array $origenMeta = [],
        ?array &$erroresFormula = null
    ): array {
        $lineas = [];
        $gananciasListo = false;
        foreach ($conceptos as $c) {
            if (! $gananciasListo && $liquidacion && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $formulaCampo = 'formula';
            try {
                $tieneCantidadExplicita = filled($c->formula_cantidad);
                $tieneValorExplicito = filled($c->formula_valor);
                if ($tieneCantidadExplicita) {
                    $formulaCampo = 'formula_cantidad';
                    $cantidad = $this->evalNum($ctx, $c->formula_cantidad, $c);
                } else {
                    $cantidad = 1.0;
                }
                if ($tieneValorExplicito) {
                    $formulaCampo = 'formula_valor';
                    $valor = $this->evalNum($ctx, $c->formula_valor, $c);
                } else {
                    $valor = 0.0;
                }
                $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

                if ($c->formula) {
                    $formulaCampo = 'formula';
                    $importe = $this->evalNum($ctx, $c->formula, $c);
                } else {
                    $importe = $cantidad * $valor;
                }
                // INF/NAN (p.ej. división por 0 Anita): no persistir; tratar como 0.
                if (! is_finite($importe)) {
                    $importe = 0.0;
                }
                $importe = round($importe, 2);
                if (! is_finite($cantidad)) {
                    $cantidad = 0.0;
                }
                if (! is_finite($valor)) {
                    $valor = 0.0;
                }
            } catch (FormulaException $e) {
                // No aborta la corrida: registra 0 y sigue (piloto / fórmulas Anita incompletas).
                $ctx->registrarConcepto((int) $c->codigo, 0.0);
                if (is_array($erroresFormula)) {
                    $pos = $e->posicion();
                    if ($pos < 0) {
                        $pos = FormulaException::extraerPosicion($e->getMessage());
                    }
                    $erroresFormula[] = [
                        'legajo' => $emp->legajo,
                        'concepto_id' => (int) $c->id,
                        'concepto' => (int) $c->codigo,
                        'concepto_descripcion' => (string) ($c->descripcion ?? ''),
                        'formula_pos' => $pos,
                        'formula_campo' => $formulaCampo,
                        'error' => $e->getMessage(),
                    ];
                }

                continue;
            }

            $ctx->registrarConcepto((int) $c->codigo, $importe);
            // Anita acum_pases: no acumula si hab_va_recibo=='1' (va_recibo=false) ni tipo 3.
            // Contribución / informativo tampoco alimentan REM/NOREM/DESC.
            if ((bool) $c->va_recibo
                && ! in_array((string) $c->tipo, ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, true)) {
                $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);
            }

            if ($importe == 0.0 && ! $c->va_recibo) {
                continue;
            }
            if ($importe == 0.0 && $c->tipo !== 'informativo' && $c->tipo !== 'contribucion') {
                continue;
            }

            $unidad = ReciboBaseCalculoSupport::normalizarUnidad($c->unidad_medida)
                ?: (ReciboBaseCalculoSupport::inferirUnidad($c->descripcion, $c->factor !== null ? (float) $c->factor : null, (string) $c->tipo) ?? '');
            $tieneValorExplicito = $tieneValorExplicito || abs($valor) > 0.0000001;
            // % sin CA: alícuota desde factor (0.11 → 11) solo para presentar BASE.
            $cantidadBase = (float) $cantidad;
            $cantExplicitaBase = $tieneCantidadExplicita;
            if ($unidad === '%' && ! $tieneCantidadExplicita && $c->factor !== null) {
                $f = abs((float) $c->factor);
                if ($f > 0 && $f < 1) {
                    $cantidadBase = $f * 100.0;
                    $cantExplicitaBase = true;
                } elseif ($f >= 1 && $f <= 100) {
                    $cantidadBase = $f;
                    $cantExplicitaBase = true;
                }
            }
            $base = ReciboBaseCalculoSupport::derivar(
                $importe,
                $cantidadBase,
                (float) $valor,
                $unidad,
                $cantExplicitaBase,
                $tieneValorExplicito,
            );

            $origen = (string) ($origenMeta[$c->id]['origen'] ?? ConceptoElegibilidadCatalogo::ORIGEN_SISTEMA);
            $lineas[] = [
                'concepto_id' => $c->id,
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'tipo' => $c->tipo,
                'columna' => self::COLUMNA[$c->tipo] ?? 'informativo',
                'cantidad' => round($cantidad, 4),
                'valor' => round($valor, 4),
                'base_calculo' => $base,
                'unidad_medida' => $unidad !== '' ? $unidad : null,
                'importe' => $importe,
                'va_recibo' => (bool) $c->va_recibo,
                'concepto_afip' => $c->concepto_afip,
                'leyenda' => $c->leyenda_recibo,
                'origen' => $origen,
                'origen_label' => ConceptoElegibilidadCatalogo::origenLabel($origen),
                'origen_badge' => ConceptoElegibilidadCatalogo::origenBadge($origen),
                'origen_detalle' => (string) ($origenMeta[$c->id]['detalle'] ?? ''),
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
                    'base_calculo' => ReciboBaseCalculoSupport::derivar(
                        $importe,
                        1.0,
                        $importe,
                        null,
                        false,
                        true,
                    ),
                    'unidad_medida' => null,
                    'importe' => $importe,
                    'va_recibo' => $pl['va_recibo'],
                    'concepto_afip' => $pl['concepto_afip'],
                    'leyenda' => $pl['leyenda'],
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'origen_badge' => ConceptoElegibilidadCatalogo::origenBadge(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'origen_detalle' => (string) ($pl['leyenda'] ?? 'Plan de cuotas del legajo'),
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
     * Preview de recibo para un empleado (no persiste).
     * Usa corrida real del mismo período/tipo si existe; si no, liquidación virtual.
     *
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   totales: array{haber: float, descuento: float, contribucion: float, neto: float, cantidad: int},
     *   periodo: int,
     *   tipo: string,
     *   liquidacion_id: int|null,
     *   liquidacion_label: string,
     *   errores: list<string>
     * }
     */
    public function simularEmpleado(Empleado_Sueldos $emp, string $periodoInput, string $tipo = 'mensual'): array
    {
        [$periodoYm, $anio, $mes] = $this->parsePeriodoInput($periodoInput);
        if (! isset(Liquidacion_Sueldos::TIPOS[$tipo])) {
            $tipo = 'mensual';
        }

        $liqReal = Liquidacion_Sueldos::query()
            ->where('empresa_id', $emp->empresa_id)
            ->where('periodo', $periodoYm)
            ->where('tipo', $tipo)
            ->whereIn('estado', array_merge(Liquidacion_Sueldos::ESTADOS_EDITABLES, ['cerrada', 'contabilizada', 'pagada']))
            ->orderByRaw(SqlDialectSupport::ordenPorLista('estado', ['borrador', 'calculada', 'revisada', 'cerrada', 'contabilizada', 'pagada']))
            ->orderByDesc('numero')
            ->first();

        $liq = $liqReal ?? $this->liquidacionVirtual($emp, $periodoYm, $anio, $mes, $tipo);

        $setInfo = null;
        $conceptos = $this->conceptosParaEmpleado($this->conceptos(), $emp, $liq, $setInfo);
        $acumDefs = $this->acumuladoresDef($liq->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $parametros = $this->parametros($liq);
        $ctx = new ContextoLiquidacion($emp, $liq, $acumDefs, $parametros);

        $errores = [];
        $lineas = [];
        $planPendientes = null;
        try {
            $lineas = $this->calcularEmpleado(
                $ctx, $emp, $conceptos, $overrides, $liq, $planPendientes,
                $setInfo['meta'] ?? []
            );
        } catch (FormulaException $e) {
            $errores[] = $e->getMessage();
        }

        $totales = ['haber' => 0.0, 'descuento' => 0.0, 'contribucion' => 0.0, 'neto' => 0.0, 'cantidad' => count($lineas)];
        foreach ($lineas as $l) {
            $col = $l['columna'] ?? 'informativo';
            $imp = (float) ($l['importe'] ?? 0);
            if ($col === 'haber') {
                $totales['haber'] += $imp;
            } elseif ($col === 'descuento') {
                $totales['descuento'] += $imp;
            } elseif ($col === 'contribucion' || ($l['tipo'] ?? '') === 'contribucion') {
                $totales['contribucion'] += $imp;
            } elseif ($col === 'neto') {
                $totales['neto'] += $imp;
            }
        }
        if ($totales['neto'] == 0.0) {
            $totales['neto'] = $totales['haber'] - $totales['descuento'];
        }

        $label = $liqReal
            ? 'Corrida N° '.$liqReal->numero.' · '.$liqReal->descripcion.' ('.$liqReal->estado.')'
            : 'Simulación (sin corrida abierta para este período)';

        return [
            'lineas' => $lineas,
            'totales' => $totales,
            'periodo' => $periodoYm,
            'tipo' => $tipo,
            'liquidacion_id' => $liqReal ? (int) $liqReal->id : null,
            'liquidacion_label' => $label,
            'errores' => $errores,
            'set_efectivo' => [
                'modo' => $setInfo['modo'] ?? null,
                'modo_label' => $setInfo['modo_label'] ?? null,
                'grupos' => $setInfo['grupos'] ?? [],
                'cantidad_conceptos' => isset($setInfo['conceptos']) ? $setInfo['conceptos']->count() : 0,
                'cantidad_excluidos' => count($setInfo['excluidos'] ?? []),
                'excluidos' => $setInfo['excluidos'] ?? [],
                'meta' => $setInfo['meta'] ?? [],
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int} periodoYm, anio, mes
     */
    private function parsePeriodoInput(string $periodoInput): array
    {
        $periodoInput = trim($periodoInput);
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodoInput, $m)) {
            $anio = (int) $m[1];
            $mes = (int) $m[2];
        } elseif (preg_match('/^(\d{6})$/', $periodoInput, $m)) {
            $anio = (int) substr($m[1], 0, 4);
            $mes = (int) substr($m[1], 4, 2);
        } else {
            $now = Carbon::now();
            $anio = (int) $now->year;
            $mes = (int) $now->month;
        }
        if ($mes < 1 || $mes > 12) {
            $mes = 1;
        }

        return [$anio * 100 + $mes, $anio, $mes];
    }

    private function liquidacionVirtual(
        Empleado_Sueldos $emp,
        int $periodoYm,
        int $anio,
        int $mes,
        string $tipo
    ): Liquidacion_Sueldos {
        $ini = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $ini->copy()->endOfMonth();
        $liq = new Liquidacion_Sueldos([
            'empresa_id' => $emp->empresa_id,
            'numero' => 0,
            'descripcion' => 'Simulación preview',
            'tipo' => $tipo,
            'periodo' => (string) $periodoYm,
            'periodo_anio' => $anio,
            'periodo_mes' => $mes,
            'periodo_desde' => $ini->toDateString(),
            'periodo_hasta' => $fin->toDateString(),
            'fecha_liquidacion' => $fin->toDateString(),
            'estado' => 'borrador',
            'simulacion' => true,
            'acumula_novedades' => true,
        ]);
        $liq->id = 0;

        return $liq;
    }

    /**
     * Modo depurador legacy (vista trazar de corrida): solo pasos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trazarEmpleado(Liquidacion_Sueldos $liquidacion, Empleado_Sueldos $emp): array
    {
        return $this->depurarSobreLiquidacion($emp, $liquidacion)['pasos'];
    }

    /**
     * Traza completa para la pantalla de ejecución: pasos, set y contexto.
     *
     * @return array<string, mixed>
     */
    public function trazarEmpleadoCompleto(Liquidacion_Sueldos $liquidacion, Empleado_Sueldos $emp): array
    {
        return $this->depurarSobreLiquidacion($emp, $liquidacion);
    }

    /**
     * Debugger de fórmulas: preview por período/tipo (con o sin corrida abierta).
     *
     * @param  array{formula?: ?string, formula_cantidad?: ?string, formula_valor?: ?string}|null  $overridesFormula
     * @return array<string, mixed>
     */
    public function depurarEmpleado(
        Empleado_Sueldos $emp,
        string $periodoInput,
        string $tipo = 'mensual',
        ?int $soloCodigo = null,
        ?array $overridesFormula = null,
        bool $incluirContexto = true
    ): array {
        [$periodoYm, $anio, $mes] = $this->parsePeriodoInput($periodoInput);
        if (! isset(Liquidacion_Sueldos::TIPOS[$tipo])) {
            $tipo = 'mensual';
        }

        $liqReal = Liquidacion_Sueldos::query()
            ->where('empresa_id', $emp->empresa_id)
            ->where('periodo', $periodoYm)
            ->where('tipo', $tipo)
            ->whereIn('estado', array_merge(Liquidacion_Sueldos::ESTADOS_EDITABLES, ['cerrada', 'contabilizada', 'pagada']))
            ->orderByRaw(SqlDialectSupport::ordenPorLista('estado', ['borrador', 'calculada', 'revisada', 'cerrada', 'contabilizada', 'pagada']))
            ->orderByDesc('numero')
            ->first();

        $liq = $liqReal ?? $this->liquidacionVirtual($emp, $periodoYm, $anio, $mes, $tipo);

        return $this->depurarSobreLiquidacion(
            $emp,
            $liq,
            $soloCodigo,
            $overridesFormula,
            $incluirContexto,
            $liqReal ? 'Corrida N° '.$liqReal->numero.' · '.$liqReal->descripcion : 'Simulación (sin corrida)'
        );
    }

    /**
     * Valida sintaxis de una fórmula (sin evaluar).
     */
    public function validarFormula(string $formula): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return ['ok' => true, 'mensaje' => 'Fórmula vacía (se usará cantidad × valor).'];
        }
        $err = $this->motor->validar($formula);

        return $err === null
            ? ['ok' => true, 'mensaje' => 'Sintaxis OK']
            : ['ok' => false, 'mensaje' => $err];
    }

    /**
     * @param  array{formula?: ?string, formula_cantidad?: ?string, formula_valor?: ?string}|null  $overridesFormula
     * @return array<string, mixed>
     */
    private function depurarSobreLiquidacion(
        Empleado_Sueldos $emp,
        Liquidacion_Sueldos $liquidacion,
        ?int $soloCodigo = null,
        ?array $overridesFormula = null,
        bool $incluirContexto = true,
        string $liquidacionLabel = ''
    ): array {
        $setInfo = null;
        $conceptos = $this->conceptosParaEmpleado($this->conceptos(), $emp, $liquidacion, $setInfo);
        $origenMeta = $setInfo['meta'] ?? [];
        $acumDefs = $this->acumuladoresDef($liquidacion->empresa_id);
        $overrides = $this->overridesConceptoAcumulador();
        $parametros = $this->parametros($liquidacion);
        $ctx = new ContextoLiquidacion($emp, $liquidacion, $acumDefs, $parametros);
        $contextoInicial = $incluirContexto ? $ctx->snapshotDebug() : null;

        $pasos = [];
        $gananciasListo = false;
        $focusEncontrado = false;

        foreach ($conceptos as $c) {
            $codigo = (int) $c->codigo;
            $esFocus = $soloCodigo === null || $codigo === $soloCodigo;

            if (! $gananciasListo && $this->formulaUsaGanancias($c)) {
                $this->puenteGanancias->sincronizarDesdeContexto($ctx, $emp, $liquidacion);
                $gananciasListo = true;
            }

            $fCant = (string) ($c->formula_cantidad ?? '');
            $fValor = (string) ($c->formula_valor ?? '');
            $fImp = (string) ($c->formula ?? '');
            if ($esFocus && is_array($overridesFormula)) {
                if (array_key_exists('formula_cantidad', $overridesFormula) && $overridesFormula['formula_cantidad'] !== null) {
                    $fCant = (string) $overridesFormula['formula_cantidad'];
                }
                if (array_key_exists('formula_valor', $overridesFormula) && $overridesFormula['formula_valor'] !== null) {
                    $fValor = (string) $overridesFormula['formula_valor'];
                }
                if (array_key_exists('formula', $overridesFormula) && $overridesFormula['formula'] !== null) {
                    $fImp = (string) $overridesFormula['formula'];
                }
            }

            $rastroCant = null;
            $rastroValor = null;
            $rastro = null;
            $error = null;
            $formulaCampo = 'formula';
            $formulaPos = -1;

            try {
                if ($fCant !== '') {
                    $formulaCampo = 'formula_cantidad';
                    if ($esFocus) {
                        [$cantidad, $rastroCant] = $this->motor->evaluarConRastro($fCant, $ctx);
                        $cantidad = (float) $cantidad;
                    } else {
                        $cantidad = (float) $this->motor->evaluar($fCant, $ctx);
                    }
                } else {
                    $cantidad = 1.0;
                }

                if ($fValor !== '') {
                    $formulaCampo = 'formula_valor';
                    if ($esFocus) {
                        [$valor, $rastroValor] = $this->motor->evaluarConRastro($fValor, $ctx);
                        $valor = (float) $valor;
                    } else {
                        $valor = (float) $this->motor->evaluar($fValor, $ctx);
                    }
                } else {
                    $valor = 0.0;
                }

                $ctx->setEscalares($cantidad, $valor, (float) $c->factor);

                if ($fImp !== '') {
                    $formulaCampo = 'formula';
                    if ($esFocus) {
                        [$importe, $rastro] = $this->motor->evaluarConRastro($fImp, $ctx);
                        $importe = round((float) $importe, 2);
                    } else {
                        $importe = round((float) $this->motor->evaluar($fImp, $ctx), 2);
                    }
                } else {
                    $importe = round($cantidad * $valor, 2);
                }
            } catch (FormulaException $e) {
                $error = $e->getMessage();
                $formulaPos = $e->posicion();
                if ($formulaPos < 0) {
                    $formulaPos = FormulaException::extraerPosicion($error);
                }
                $cantidad = $cantidad ?? 0.0;
                $valor = $valor ?? 0.0;
                $importe = 0.0;
            }

            if ($error === null) {
                $ctx->registrarConcepto($codigo, $importe);
                // Misma regla que calcularEmpleado: Anita no acumula si va_recibo=false.
                if ((bool) $c->va_recibo
                    && ! in_array((string) $c->tipo, ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES, true)) {
                    $ctx->aplicarAcumuladores((string) $c->tipo, $importe, $overrides[$c->id] ?? []);
                }
            }

            if ($esFocus) {
                $focusEncontrado = true;
                $origen = (string) ($origenMeta[$c->id]['origen'] ?? ConceptoElegibilidadCatalogo::ORIGEN_SISTEMA);
                $pasos[] = [
                    'concepto_id' => (int) $c->id,
                    'codigo' => $codigo,
                    'descripcion' => $c->descripcion,
                    'tipo' => $c->tipo,
                    'formula' => $fImp !== '' ? $fImp : null,
                    'formula_cantidad' => $fCant !== '' ? $fCant : null,
                    'formula_valor' => $fValor !== '' ? $fValor : null,
                    'formula_override' => $esFocus && is_array($overridesFormula),
                    'formula_campo' => $error !== null ? $formulaCampo : null,
                    'formula_pos' => $error !== null && $formulaPos >= 0 ? $formulaPos : null,
                    'cantidad' => round((float) $cantidad, 4),
                    'valor' => round((float) $valor, 4),
                    'importe' => $importe,
                    'error' => $error,
                    'rastro' => $rastro instanceof RastreadorFormula ? $rastro->arbol() : [],
                    'rastro_texto' => $rastro instanceof RastreadorFormula ? $rastro->texto() : '',
                    'rastro_cantidad' => $rastroCant instanceof RastreadorFormula ? $rastroCant->arbol() : [],
                    'rastro_valor' => $rastroValor instanceof RastreadorFormula ? $rastroValor->arbol() : [],
                    'acumuladores' => $ctx->acumuladores(),
                    'contexto_tras' => $incluirContexto ? $ctx->snapshotDebug() : null,
                    'origen' => $origen,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel($origen),
                    'en_set' => true,
                ];

                // Si filtramos un solo código, seguir calculando silenciosamente no hace falta
                // para el resto del set… salvo que el usuario quiera ver acumuladores previos.
                // Calculamos todos para que Iconcepto/acum previos existan; solo devolvemos focus.
                if ($soloCodigo !== null && $error !== null) {
                    // con error igual seguimos registrando 0? ya no registramos — ok
                }
            } elseif ($error !== null) {
                // Concepto previo falló: corta la cadena (igual que liquidar).
                break;
            }
        }

        if ($soloCodigo === null) {
            foreach ($this->planCuotas->lineasPlan($emp, $liquidacion, $ctx) as $pl) {
                $importe = round((float) $pl['importe'], 2);
                $ctx->registrarConcepto((int) $pl['codigo'], $importe);
                $ctx->aplicarAcumuladores((string) $pl['tipo'], $importe, []);
                $pasos[] = [
                    'concepto_id' => (int) ($pl['concepto_id'] ?? 0),
                    'codigo' => $pl['codigo'],
                    'descripcion' => $pl['descripcion'],
                    'tipo' => $pl['tipo'],
                    'formula' => '(plan de cuotas) '.$pl['leyenda'],
                    'formula_cantidad' => null,
                    'formula_valor' => null,
                    'formula_override' => false,
                    'cantidad' => 1.0,
                    'valor' => $importe,
                    'importe' => $importe,
                    'error' => null,
                    'rastro' => [],
                    'rastro_texto' => $pl['leyenda'],
                    'rastro_cantidad' => [],
                    'rastro_valor' => [],
                    'acumuladores' => $ctx->acumuladores(),
                    'contexto_tras' => null,
                    'origen' => ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA,
                    'origen_label' => ConceptoElegibilidadCatalogo::origenLabel(ConceptoElegibilidadCatalogo::ORIGEN_PLAN_CUOTA),
                    'en_set' => true,
                ];
            }
        } elseif (! $focusEncontrado && $soloCodigo !== null) {
            // Concepto fuera del set: evaluar igual en sandbox (útil al programar en ABM).
            $c = Concepto_Sueldos::query()->where('codigo', $soloCodigo)->where('activo', true)->first();
            if ($c) {
                $fCant = (string) ($overridesFormula['formula_cantidad'] ?? $c->formula_cantidad ?? '');
                $fValor = (string) ($overridesFormula['formula_valor'] ?? $c->formula_valor ?? '');
                $fImp = (string) ($overridesFormula['formula'] ?? $c->formula ?? '');
                try {
                    $cantidad = $fCant !== '' ? (float) $this->motor->evaluar($fCant, $ctx) : 1.0;
                    $valor = $fValor !== '' ? (float) $this->motor->evaluar($fValor, $ctx) : 0.0;
                    $ctx->setEscalares($cantidad, $valor, (float) $c->factor);
                    $rastro = null;
                    if ($fImp !== '') {
                        [$importe, $rastro] = $this->motor->evaluarConRastro($fImp, $ctx);
                        $importe = round((float) $importe, 2);
                    } else {
                        $importe = round($cantidad * $valor, 2);
                    }
                    $pasos[] = [
                        'concepto_id' => (int) $c->id,
                        'codigo' => (int) $c->codigo,
                        'descripcion' => $c->descripcion,
                        'tipo' => $c->tipo,
                        'formula' => $fImp !== '' ? $fImp : null,
                        'formula_cantidad' => $fCant !== '' ? $fCant : null,
                        'formula_valor' => $fValor !== '' ? $fValor : null,
                        'formula_override' => is_array($overridesFormula),
                        'cantidad' => round($cantidad, 4),
                        'valor' => round($valor, 4),
                        'importe' => $importe,
                        'error' => null,
                        'rastro' => $rastro instanceof RastreadorFormula ? $rastro->arbol() : [],
                        'rastro_texto' => $rastro instanceof RastreadorFormula ? $rastro->texto() : '',
                        'rastro_cantidad' => [],
                        'rastro_valor' => [],
                        'acumuladores' => $ctx->acumuladores(),
                        'contexto_tras' => $incluirContexto ? $ctx->snapshotDebug() : null,
                        'origen' => 'fuera_set',
                        'origen_label' => 'Fuera del set',
                        'en_set' => false,
                        'aviso' => 'Este concepto no está en el set efectivo del legajo; se evaluó igual para depurar.',
                    ];
                } catch (FormulaException $e) {
                    $pasos[] = array_merge($this->pasoError($c, $e), [
                        'en_set' => false,
                        'origen' => 'fuera_set',
                        'origen_label' => 'Fuera del set',
                        'aviso' => 'Concepto fuera del set; falló al evaluar en sandbox.',
                    ]);
                }
            }
        }

        return [
            'pasos' => $pasos,
            'periodo' => (int) ($liquidacion->periodo ?: 0),
            'tipo' => (string) $liquidacion->tipo,
            'liquidacion_id' => (int) ($liquidacion->id ?? 0) ?: null,
            'liquidacion_label' => $liquidacionLabel,
            'empleado' => [
                'id' => (int) $emp->id,
                'legajo' => (int) $emp->legajo,
                'nombre' => (string) $emp->nombre,
                'empresa_id' => (int) $emp->empresa_id,
            ],
            'set_efectivo' => [
                'modo' => $setInfo['modo'] ?? null,
                'modo_label' => $setInfo['modo_label'] ?? null,
                'cantidad_conceptos' => isset($setInfo['conceptos']) ? $setInfo['conceptos']->count() : 0,
                'cantidad_excluidos' => count($setInfo['excluidos'] ?? []),
            ],
            'contexto_inicial' => $contextoInicial,
            'contexto_final' => $incluirContexto ? $ctx->snapshotDebug() : null,
        ];
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
                "Concepto {$c->codigo} ({$c->descripcion}): ".$e->getMessage(),
                $e->posicion()
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
            // Internos (Anita hab_va_recibo=1 → va_recibo=false): se calculan para im()/fórmulas
            // pero no componen bruto/neto/CE del recibo. Ej. 999 BRUTO SIN TOPE.
            if (empty($l['va_recibo'])) {
                continue;
            }
            switch ($l['tipo']) {
                case 'remunerativo': $t['rem'] += $l['importe'];
                    break;
                case 'no_remunerativo': $t['norem'] += $l['importe'];
                    break;
                case 'asignacion': $t['asig'] += $l['importe'];
                    break;
                case 'descuento': $t['desc'] += $l['importe'];
                    break;
                case 'aporte': $t['desc'] += $l['importe'];
                    $t['aportes'] += $l['importe'];
                    break;
                case 'retencion': $t['desc'] += $l['importe'];
                    break;
                case 'contribucion': $t['contrib'] += $l['importe'];
                    break;
            }
        }
        $t['bruto'] = $t['rem'] + $t['norem'];
        $t['neto'] = round($t['rem'] + $t['norem'] + $t['asig'] - $t['desc'], 2);

        return $t;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function importeConcepto(array $lineas, int $codigo): float
    {
        foreach ($lineas as $l) {
            if ((int) ($l['codigo'] ?? 0) === $codigo) {
                return round((float) ($l['importe'] ?? 0), 2);
            }
        }

        return 0.0;
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
     * Filtra el catálogo global al set efectivo del legajo
     * (grupos + elegibilidad + novedades en modo grupos + explícitos).
     *
     * @param  \Illuminate\Support\Collection<int, Concepto_Sueldos>  $todos
     * @param  array<string, mixed>|null  $setInfo  salida del resolver (meta, modo, …)
     * @return \Illuminate\Support\Collection<int, Concepto_Sueldos>
     */
    private function conceptosParaEmpleado($todos, Empleado_Sueldos $emp, Liquidacion_Sueldos $liq, ?array &$setInfo = null)
    {
        $setInfo = $this->setEfectivo->resolver($emp, $liq);
        $ids = $setInfo['conceptos']->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return $todos->take(0);
        }
        $flip = array_flip($ids);

        return $todos->filter(fn ($c) => isset($flip[(int) $c->id]))->values();
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
     * Origen del recibo al persistir desde el motor (incluye flag del legajo).
     *
     * @return array{origen: string, confidencial: bool}
     */
    public static function atributosOrigenReciboMotor(Empleado_Sueldos $emp): array
    {
        return [
            'origen' => Liquidacion_Recibo_Sueldos::ORIGEN_MOTOR,
            'confidencial' => (bool) ($emp->confidencial ?? false),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empleado_Sueldos>
     */
    private function empleados(Liquidacion_Sueldos $liq)
    {
        // Incluye legajos confidenciales: misma corrida que la nómina normal.
        $query = Empleado_Sueldos::query()
            ->where('empresa_id', $liq->empresa_id)
            ->with(['categoria', 'motivoegreso'])
            ->orderBy('legajo');

        // Liquidacion final: egreso en el período (provisoria A+fecha o definitiva B).
        // Resto: activos (incluye baja provisoria: siguen en nómina hasta confirmar definitiva).
        if ($liq->tipo === 'final') {
            $query->whereNotNull('fecha_egreso');
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

        // Modelo Anita: grupo O novedad. En vacaciones solo legajos con días a liquidar
        // (novedad 420/422 o ausencia vacacional que solapa la ventana); evita liquidar
        // a toda la nómina por el fallback TVAC de la fórmula 422.
        $filtros = $this->filtros($liq);

        // array_key_exists: selección explícita vacía = nadie (tras quitar el último recibo).
        if (array_key_exists('empleado_ids', $filtros) && is_array($filtros['empleado_ids'])) {
            $query->whereIn('id', $filtros['empleado_ids']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $query->where('agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }

        if ($liq->tipo === 'vacaciones') {
            $idsVac = $this->empleadoIdsConVacacionesALiquidar($liq);
            if ($idsVac === []) {
                return collect();
            }
            $query->whereIn('id', $idsVac);
        }

        $this->multiempresa->aplicarAlcanceAlQueryEmpleados($query, $liq);

        return $query->get();
    }

    /**
     * Empleados con novedad 420/422 vigente o ausencia de vacaciones que solapa
     * la ventana de la corrida (periodo_desde/hasta o mes del período).
     *
     * @return list<int>
     */
    private function empleadoIdsConVacacionesALiquidar(Liquidacion_Sueldos $liq): array
    {
        $periodoYm = (int) ($liq->periodo ?: (((int) $liq->periodo_anio) * 100 + (int) $liq->periodo_mes));
        try {
            if (! empty($liq->periodo_desde) && ! empty($liq->periodo_hasta)) {
                $ini = Carbon::parse($liq->periodo_desde)->toDateString();
                $fin = Carbon::parse($liq->periodo_hasta)->toDateString();
            } else {
                [$cIni, $cFin] = NovedadSueldosVigencia::limitesPeriodo($periodoYm);
                $ini = $cIni->toDateString();
                $fin = $cFin->toDateString();
            }
        } catch (\Throwable $e) {
            return [];
        }

        $ids = [];

        if (Schema::hasTable('novedad_sueldos')) {
            $novIds = DB::table('novedad_sueldos')
                ->whereIn('concepto_codigo', [420, 422])
                ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA)
                ->where(function ($q) use ($liq, $periodoYm, $ini, $fin) {
                    if ((int) $liq->id > 0) {
                        $q->orWhere('liquidacion_id', (int) $liq->id);
                    }
                    $q->orWhere(function ($q2) use ($periodoYm) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('liquidacion_id')->orWhere('liquidacion_id', 0);
                        })->where('periodo', $periodoYm);
                    });
                    $q->orWhere(function ($q2) use ($ini, $fin) {
                        $q2->where(function ($q3) {
                            $q3->whereNull('liquidacion_id')->orWhere('liquidacion_id', 0);
                        })
                            ->whereNotNull('fecha_desde')
                            ->whereDate('fecha_desde', '<=', $fin)
                            ->where(function ($q3) use ($ini) {
                                $q3->whereNull('fecha_hasta')
                                    ->orWhereDate('fecha_hasta', '>=', $ini);
                            });
                    });
                })
                ->pluck('empleado_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $ids = array_merge($ids, $novIds);
        }

        if (Schema::hasTable('empleado_ausencia_sueldos') && Schema::hasTable('tipo_ausencia_sueldos')) {
            // Salida (fecha_desde) dentro de la ventana: 1ra=01–15, 2da=16–fin.
            $ausIds = DB::table('empleado_ausencia_sueldos as a')
                ->join('tipo_ausencia_sueldos as t', 't.id', '=', 'a.tipo_ausencia_id')
                ->whereIn('a.estado', ['planificada', 'aprobada', 'tomada'])
                ->where(function ($q) {
                    $q->where('t.categoria', 'vacaciones')
                        ->orWhere('t.afecta_saldo_vacaciones', true);
                })
                ->whereDate('a.fecha_desde', '>=', $ini)
                ->whereDate('a.fecha_desde', '<=', $fin)
                ->pluck('a.empleado_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $ids = array_merge($ids, $ausIds);
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
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
