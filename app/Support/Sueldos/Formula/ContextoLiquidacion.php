<?php

namespace App\Support\Sueldos\Formula;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Entorno de evaluacion de una liquidacion para UN empleado. Resuelve:
 *  - variables: empleado.*, periodo.*, corrida.*, y escalares del concepto en
 *    curso (cantidad, valor, factor).
 *  - funciones de dominio: concepto()/im(), acum(), param(), base(),
 *    antiguedad(), dias().
 *
 * Mantiene el estado corriente de acumuladores y conceptos ya calculados, que
 * el calculador va alimentando a medida que recorre la secuencia.
 */
class ContextoLiquidacion implements EntornoFormula
{
    /** @var array<string, float|int|string> */
    private array $vars = [];

    /** @var array<int, array{codigo: string, tipos: array<int,string>, signo: int}> */
    private array $acumDefs = [];

    /** @var array<string, float> Totales corrientes de acumuladores. */
    private array $acum = [];

    /** @var array<int, float> Importe de conceptos ya calculados (codigo => importe). */
    private array $conceptos = [];

    /** @var array<string, float> Bases del empleado (codigo => valor). */
    private array $bases = [];

    /** @var array<int, float>|null Factores por codigo de concepto (hab_factor). Carga perezosa. */
    private ?array $factores = null;

    /** @var array<int, array{periodo: int, cod: string, valor: float, tipo: string}> Historico de acumuladores. */
    private array $historico = [];

    private ParametroSueldosResolver $parametros;

    private int $empleadoId = 0;

    private ?int $empresaId = null;

    private int $anio = 0;

    private int $mes = 0;

    private string $tipoCorrida = 'mensual';

    private Carbon $fechaRef;

    private ?Carbon $fechaIngreso = null;

    private ?Carbon $fechaEgreso = null;

    /**
     * @param  array<int, array{codigo: string, tipos: array<int,string>, signo: int}>  $acumDefs
     */
    public function __construct(
        Empleado_Sueldos $empleado,
        Liquidacion_Sueldos $liquidacion,
        array $acumDefs,
        ParametroSueldosResolver $parametros
    ) {
        $this->acumDefs = $acumDefs;
        $this->parametros = $parametros;
        $this->empleadoId = (int) $empleado->id;
        $this->empresaId = $liquidacion->empresa_id ? (int) $liquidacion->empresa_id : null;
        $this->tipoCorrida = (string) $liquidacion->tipo;
        foreach ($acumDefs as $def) {
            $this->acum[strtoupper($def['codigo'])] = 0.0;
        }
        $this->cargarVariables($empleado, $liquidacion);
        $this->cargarBases($this->empleadoId);
        $this->cargarHistorico($this->empleadoId, (int) ($liquidacion->id ?? 0));
    }

    private function cargarVariables(Empleado_Sueldos $emp, Liquidacion_Sueldos $liq): void
    {
        $fechaRef = $liq->fecha_liquidacion ?: ($liq->periodo_hasta ?: Carbon::now());
        $fechaRef = $fechaRef instanceof Carbon ? $fechaRef : Carbon::parse($fechaRef);
        $this->fechaRef = $fechaRef;
        $this->fechaIngreso = $emp->fecha_ingreso
            ? ($emp->fecha_ingreso instanceof Carbon ? $emp->fecha_ingreso : Carbon::parse($emp->fecha_ingreso))
            : null;
        $this->fechaEgreso = $emp->fecha_egreso
            ? ($emp->fecha_egreso instanceof Carbon ? $emp->fecha_egreso : Carbon::parse($emp->fecha_egreso))
            : null;
        $this->anio = (int) ($liq->periodo_anio ?: $fechaRef->year);
        $this->mes = (int) ($liq->periodo_mes ?: $fechaRef->month);

        [$antAnios, $antMeses] = $this->calcularAntiguedad($emp, $fechaRef);
        $dias = $this->diasPeriodo($liq);
        $claseEgreso = $this->claseMotivoEgreso($emp->motivoegreso_id ? (int) $emp->motivoegreso_id : 0);

        $this->vars = [
            'empleado.sueldo_basico' => (float) $emp->sueldo_basico,
            'empleado.jornal_dia' => (float) $emp->jornal_dia,
            'empleado.jornal_hora' => (float) $emp->jornal_hora,
            'empleado.antiguedad_anios' => $antAnios,
            'empleado.antiguedad_meses' => $antMeses,
            'empleado.legajo' => (int) $emp->legajo,
            'empleado.sexo' => (string) $emp->sexo,
            'empleado.categoria_id' => (int) $emp->categoria_id,
            'empleado.agrupamiento_id' => (int) $emp->agrupamiento_id,
            'empleado.centrocosto_id' => (int) $emp->centrocosto_id,
            'empleado.motivo_egreso_id' => (int) ($emp->motivoegreso_id ?? 0),
            'empleado.motivo_egreso_clase' => $claseEgreso,
            'empleado.tiene_egreso' => $this->fechaEgreso !== null ? 1 : 0,
            'empleado.egreso_anio' => $this->fechaEgreso ? (int) $this->fechaEgreso->year : 0,
            'empleado.egreso_mes' => $this->fechaEgreso ? (int) $this->fechaEgreso->month : 0,
            'periodo.dias' => $dias,
            'periodo.dias_trabajados' => $dias,
            'periodo.anio' => $this->anio,
            'periodo.mes' => $this->mes,
            'periodo.periodo' => $this->anio * 100 + $this->mes,
            'corrida.tipo' => (string) $liq->tipo,
            // Escalares del concepto en curso (los setea el calculador)
            'cantidad' => 0.0,
            'valor' => 0.0,
            'factor' => 0.0,
        ];
    }

    /**
     * @return array{0: int, 1: int} [anios, meses totales]
     */
    private function calcularAntiguedad(Empleado_Sueldos $emp, Carbon $ref): array
    {
        if (! $emp->fecha_ingreso) {
            return [(int) $emp->antiguedad_anterior, (int) $emp->antiguedad_anterior * 12];
        }
        $ingreso = $emp->fecha_ingreso instanceof Carbon ? $emp->fecha_ingreso : Carbon::parse($emp->fecha_ingreso);
        $anteriorAnios = (int) $emp->antiguedad_anterior;
        $meses = $ingreso->diffInMonths($ref) + $anteriorAnios * 12;

        return [intdiv($meses, 12), $meses];
    }

    private function diasPeriodo(Liquidacion_Sueldos $liq): int
    {
        if ($liq->periodo_desde && $liq->periodo_hasta) {
            $d = $liq->periodo_desde instanceof Carbon ? $liq->periodo_desde : Carbon::parse($liq->periodo_desde);
            $h = $liq->periodo_hasta instanceof Carbon ? $liq->periodo_hasta : Carbon::parse($liq->periodo_hasta);

            return (int) $d->diffInDays($h) + 1;
        }

        return 30;
    }

    private function claseMotivoEgreso(int $motivoegresoId): string
    {
        if ($motivoegresoId <= 0) {
            return '';
        }
        try {
            return (string) (DB::table('motivoegreso_sueldos')->where('id', $motivoegresoId)->value('clase') ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function cargarBases(int $empleadoId): void
    {
        try {
            $filas = DB::table('empleado_base_sueldos as eb')
                ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'eb.nombrebase_id')
                ->where('eb.empleado_id', $empleadoId)
                ->orderBy('eb.fecha_vigencia')
                ->get(['nb.codigo', 'eb.valor']);
            foreach ($filas as $f) {
                $this->bases[strtoupper((string) $f->codigo)] = (float) $f->valor;
            }
        } catch (\Throwable $e) {
            // Sin tabla de bases o esquema distinto: base() devolvera 0.
        }
    }

    /**
     * Carga los acumuladores historicos del empleado (excluye la corrida actual
     * para no auto-referenciarse en un recalculo).
     */
    private function cargarHistorico(int $empleadoId, int $liquidacionId): void
    {
        if ($empleadoId <= 0) {
            return;
        }
        try {
            $rows = DB::table('liquidacion_acumulador_sueldos')
                ->where('empleado_id', $empleadoId)
                ->when($liquidacionId > 0, fn ($q) => $q->where('liquidacion_id', '!=', $liquidacionId))
                ->get(['periodo', 'codigo', 'valor', 'tipo_corrida']);
            foreach ($rows as $r) {
                $this->historico[] = [
                    'periodo' => (int) $r->periodo,
                    'cod' => strtoupper((string) $r->codigo),
                    'valor' => (float) $r->valor,
                    'tipo' => (string) $r->tipo_corrida,
                ];
            }
        } catch (\Throwable $e) {
            // Sin tabla de historico todavia: las funciones hacia atras devuelven 0.
        }
    }

    // ---- API que usa el calculador ----

    /**
     * Fija los escalares del concepto en curso, para que la formula de importe
     * pueda referirse a cantidad/valor/factor.
     */
    public function setEscalares(float $cantidad, float $valor, float $factor): void
    {
        $this->vars['cantidad'] = $cantidad;
        $this->vars['valor'] = $valor;
        $this->vars['factor'] = $factor;
    }

    public function registrarConcepto(int $codigo, float $importe): void
    {
        $this->conceptos[$codigo] = $importe;
    }

    /**
     * Suma el importe del concepto a los acumuladores. Regla:
     *   - override excluir  => no suma aunque el tipo coincida.
     *   - override incluir  => suma con el signo del override (aunque el tipo no coincida).
     *   - sin override      => suma si el tipo esta en tipos_incluye, con el signo del acumulador.
     *
     * @param  array<string, array{signo: int, excluir: bool}>  $overrides  keyed por codigo de acumulador
     */
    public function aplicarAcumuladores(string $tipoConcepto, float $importe, array $overrides = []): void
    {
        foreach ($this->acumDefs as $def) {
            $cod = strtoupper($def['codigo']);
            if (array_key_exists($cod, $overrides)) {
                if ($overrides[$cod]['excluir']) {
                    continue;
                }
                $this->acum[$cod] = ($this->acum[$cod] ?? 0.0) + $overrides[$cod]['signo'] * $importe;

                continue;
            }
            if (in_array($tipoConcepto, $def['tipos'], true)) {
                $this->acum[$cod] = ($this->acum[$cod] ?? 0.0) + $def['signo'] * $importe;
            }
        }
    }

    /**
     * @return array<string, float>
     */
    public function acumuladores(): array
    {
        return $this->acum;
    }

    // ---- EntornoFormula ----

    public function variable(string $ruta)
    {
        return $this->vars[$ruta] ?? null;
    }

    public function existeFuncion(string $nombre): bool
    {
        return in_array(strtolower($nombre), [
            'concepto', 'im', 'im_rango', 'concepto_rango', 'factor', 'base_num', 'acum', 'param', 'p', 'base', 'antiguedad', 'ant', 'dias',
            // funciones "hacia atras" (historico)
            'acum_hist', 'mejor_rem_semestre', 'prom_rem_semestre', 'mejor_rem_meses',
            'dias_semestre', 'dias_trabajados_semestre', 'dias_mes', 'dias_trabajados_mes',
            'antiguedad_245', 'antiguedad_meses',
            // Ganancias 4ta
            'ganancias', 'ganancia_linea',
        ], true);
    }

    public function funcion(string $nombre, array $args)
    {
        switch (strtolower($nombre)) {
            case 'concepto':
            case 'im':
                $cod = (int) ($args[0] ?? 0);

                return $this->conceptos[$cod] ?? 0.0;
            case 'im_rango':
            case 'concepto_rango':
                // Anita IR(desde, hasta): suma de importes de conceptos en rango.
                return $this->imRango((int) ($args[0] ?? 0), (int) ($args[1] ?? 0));
            case 'factor':
                // Anita F(n): factor (hab_factor) del concepto n. Sin argumento
                // devuelve el factor del concepto en curso.
                if (! isset($args[0])) {
                    return (float) ($this->vars['factor'] ?? 0.0);
                }

                return $this->factorConcepto((int) $args[0]);
            case 'base_num':
                // Anita B(n): base de calculo numerica. 1/2/3 = basico/jornal dia/hora;
                // resto = base del empleado por codigo numerico de nombrebase.
                return $this->baseNum((int) ($args[0] ?? 0));
            case 'acum':
                $cod = strtoupper((string) ($args[0] ?? ''));

                return $this->acum[$cod] ?? 0.0;
            case 'param':
            case 'p':
                return $this->parametros->valor((string) ($args[0] ?? ''));
            case 'base':
                $cod = strtoupper((string) ($args[0] ?? ''));

                return $this->bases[$cod] ?? 0.0;
            case 'antiguedad':
            case 'ant':
                return $this->vars['empleado.antiguedad_anios'];
            case 'antiguedad_meses':
                return $this->vars['empleado.antiguedad_meses'];
            case 'dias':
                return $this->vars['periodo.dias'];

            // ---- funciones hacia atras (historico) ----
            case 'acum_hist':
                // acum_hist(codigo, desde_yyyymm, hasta_yyyymm [, op])
                return $this->acumHist(
                    (string) ($args[0] ?? ''),
                    (int) ($args[1] ?? 0),
                    (int) ($args[2] ?? 999912),
                    (string) ($args[3] ?? 'sum')
                );
            case 'mejor_rem_semestre':
                // mejor_rem_semestre([codigo]) codigo por defecto REM
                return $this->mejorRemSemestre(strtoupper((string) ($args[0] ?? 'REM')));
            case 'prom_rem_semestre':
                return $this->promRemSemestre(strtoupper((string) ($args[0] ?? 'REM')));
            case 'mejor_rem_meses':
                // mejor_rem_meses(cantidad_meses [, codigo])
                return $this->mejorRemMeses((int) ($args[0] ?? 6), strtoupper((string) ($args[1] ?? 'REM')));
            case 'dias_semestre':
                return $this->diasSemestre();
            case 'dias_trabajados_semestre':
                return $this->diasTrabajadosSemestre();
            case 'dias_mes':
                return $this->diasMes();
            case 'dias_trabajados_mes':
                return $this->diasTrabajadosMes();
            case 'antiguedad_245':
                return $this->antiguedad245();
            case 'ganancias':
                // Retencion del mes (linea RET_GANANCIAS del plan).
                return $this->gananciaLineaResultado('RET_GANANCIAS');
            case 'ganancia_linea':
                return $this->gananciaLineaResultado((string) ($args[0] ?? 'RET_GANANCIAS'));
        }

        return 0.0;
    }

    /**
     * Suma los importes de los conceptos cuyo código está en [desde, hasta].
     * Equivale a IR(desde, hasta) de Anita.
     */
    private function imRango(int $desde, int $hasta): float
    {
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $suma = 0.0;
        foreach ($this->conceptos as $cod => $importe) {
            if ($cod >= $desde && $cod <= $hasta) {
                $suma += (float) $importe;
            }
        }

        return $suma;
    }

    /**
     * Factor (hab_factor) de un concepto por codigo. Carga perezosa cacheada.
     */
    private function factorConcepto(int $codigo): float
    {
        if ($this->factores === null) {
            $this->factores = [];
            try {
                $filas = DB::table('concepto_sueldos')->get(['codigo', 'factor']);
                foreach ($filas as $f) {
                    $this->factores[(int) $f->codigo] = (float) $f->factor;
                }
            } catch (\Throwable $e) {
                // sin tabla: factor 0
            }
        }

        return $this->factores[$codigo] ?? 0.0;
    }

    /**
     * Base de calculo numerica estilo Anita B(n).
     */
    private function baseNum(int $n): float
    {
        switch ($n) {
            case 1:
                return (float) ($this->vars['empleado.sueldo_basico'] ?? 0.0);
            case 2:
                return (float) ($this->vars['empleado.jornal_dia'] ?? 0.0);
            case 3:
                return (float) ($this->vars['empleado.jornal_hora'] ?? 0.0);
        }

        return $this->bases[(string) $n] ?? 0.0;
    }

    /**
     * Lee un valor ya calculado de la planilla de Ganancias (snapshot).
     */
    private function gananciaLineaResultado(string $codigo): float
    {
        if ($this->empleadoId <= 0 || $this->anio <= 0 || $this->mes <= 0) {
            return 0.0;
        }
        try {
            $v = DB::table('ganancia_resultado_sueldos')
                ->where('empleado_id', $this->empleadoId)
                ->where('anio', $this->anio)
                ->where('mes', $this->mes)
                ->where('linea_codigo', strtoupper($codigo))
                ->value('valor');

            return (float) ($v ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    // ---- calculo hacia atras ----

    /**
     * @return array{0: int, 1: int} [desde_yyyymm, hasta_yyyymm] del semestre en curso.
     */
    private function semestrePeriodos(): array
    {
        if ($this->mes <= 6) {
            return [$this->anio * 100 + 1, $this->anio * 100 + 6];
        }

        return [$this->anio * 100 + 7, $this->anio * 100 + 12];
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [desde, hasta] fechas del semestre.
     */
    private function semestreFechas(): array
    {
        if ($this->mes <= 6) {
            return [Carbon::create($this->anio, 1, 1), Carbon::create($this->anio, 6, 30)];
        }

        return [Carbon::create($this->anio, 7, 1), Carbon::create($this->anio, 12, 31)];
    }

    /**
     * Suma/max/min/prom de un acumulador historico por periodo en un rango.
     */
    private function acumHist(string $codigo, int $desde, int $hasta, string $op): float
    {
        $cod = strtoupper($codigo);
        // Sumar por periodo (varias corridas de un mismo mes se suman).
        $porPeriodo = [];
        foreach ($this->historico as $h) {
            if ($h['cod'] !== $cod || $h['periodo'] < $desde || $h['periodo'] > $hasta) {
                continue;
            }
            $porPeriodo[$h['periodo']] = ($porPeriodo[$h['periodo']] ?? 0.0) + $h['valor'];
        }
        if (empty($porPeriodo)) {
            return 0.0;
        }

        return match (strtolower($op)) {
            'max' => (float) max($porPeriodo),
            'min' => (float) min($porPeriodo),
            'prom', 'avg' => array_sum($porPeriodo) / count($porPeriodo),
            'count' => (float) count($porPeriodo),
            default => (float) array_sum($porPeriodo),
        };
    }

    /**
     * Mejor remuneracion mensual del semestre (Ley 27.073). Toma corridas
     * mensuales; si no hay historico usa el acumulador corriente.
     */
    private function mejorRemSemestre(string $codigo): float
    {
        [$d, $h] = $this->semestrePeriodos();
        $cod = strtoupper($codigo);
        $porPeriodo = [];
        foreach ($this->historico as $reg) {
            if ($reg['cod'] !== $cod || $reg['periodo'] < $d || $reg['periodo'] > $h) {
                continue;
            }
            if (! in_array($reg['tipo'], ['mensual', 'quincena_1', 'quincena_2'], true)) {
                continue;
            }
            $porPeriodo[$reg['periodo']] = ($porPeriodo[$reg['periodo']] ?? 0.0) + $reg['valor'];
        }
        $mejorHist = empty($porPeriodo) ? 0.0 : (float) max($porPeriodo);
        // Considera tambien el mes corriente que aun no esta en el historico.
        $corriente = $this->acum[$cod] ?? 0.0;

        return max($mejorHist, $corriente);
    }

    private function promRemSemestre(string $codigo): float
    {
        [$d, $h] = $this->semestrePeriodos();

        return $this->acumHist($codigo, $d, $h, 'prom');
    }

    /**
     * Mejor remuneracion de los ultimos N meses (para art. 245 normal/habitual).
     */
    private function mejorRemMeses(int $meses, string $codigo): float
    {
        $meses = max(1, $meses);
        $ref = Carbon::create($this->anio, $this->mes, 1);
        $desdeC = (clone $ref)->subMonths($meses - 1);
        $desde = $desdeC->year * 100 + $desdeC->month;
        $hasta = $this->anio * 100 + $this->mes;
        $mejorHist = $this->acumHist($codigo, $desde, $hasta, 'max');
        $corriente = $this->acum[strtoupper($codigo)] ?? 0.0;

        return max($mejorHist, $corriente);
    }

    private function diasSemestre(): int
    {
        [$d, $h] = $this->semestreFechas();

        return (int) $d->diffInDays($h) + 1;
    }

    private function diasTrabajadosSemestre(): int
    {
        [$semD, $semH] = $this->semestreFechas();
        $ini = $this->fechaIngreso && $this->fechaIngreso->gt($semD) ? $this->fechaIngreso->copy() : $semD->copy();
        $fin = $semH->copy();
        if ($this->fechaEgreso && $this->fechaEgreso->lt($fin)) {
            $fin = $this->fechaEgreso->copy();
        }
        if ($fin->lt($ini)) {
            return 0;
        }

        return (int) $ini->diffInDays($fin) + 1;
    }

    private function diasMes(): int
    {
        return (int) Carbon::create($this->anio, $this->mes, 1)->daysInMonth;
    }

    private function diasTrabajadosMes(): int
    {
        $mesD = Carbon::create($this->anio, $this->mes, 1);
        $mesH = (clone $mesD)->endOfMonth();
        $ini = $this->fechaIngreso && $this->fechaIngreso->gt($mesD) ? $this->fechaIngreso->copy() : $mesD->copy();
        $fin = $mesH->copy();
        if ($this->fechaEgreso && $this->fechaEgreso->lt($fin)) {
            $fin = $this->fechaEgreso->copy();
        }
        if ($fin->lt($ini)) {
            return 0;
        }

        return (int) $ini->diffInDays($fin) + 1;
    }

    /**
     * Antiguedad en anios computando fraccion mayor a 3 meses como un anio mas
     * (art. 245 LCT). Usa fecha de egreso si existe, si no la fecha de referencia.
     */
    private function antiguedad245(): int
    {
        if (! $this->fechaIngreso) {
            return (int) ($this->vars['empleado.antiguedad_anios'] ?? 0);
        }
        $fin = $this->fechaEgreso ?: $this->fechaRef;
        $meses = $this->fechaIngreso->diffInMonths($fin);
        $anios = intdiv($meses, 12);
        $resto = $meses % 12;

        return $resto > 3 ? $anios + 1 : $anios;
    }
}
