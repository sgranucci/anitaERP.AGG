<?php

namespace App\Support\Sueldos\Formula;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\AntiguedadTablaResolver;
use App\Support\Sueldos\CategoriaOrigenBases;
use App\Support\Sueldos\Lsd\LsdDetraccionSupport;
use App\Support\Sueldos\NovedadSueldosVigencia;
use App\Support\Sueldos\VacacionEscalaAntiguedad;
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

    /** @var array<int, float> Valor1 de novedades de la corrida/período actual (concepto_codigo => suma). */
    private array $novedadesV1 = [];

    /** @var array<int, float> Valor2 de novedades de la corrida/período actual. */
    private array $novedadesV2 = [];

    /** @var list<array{periodo: int, cod: int, v1: float, v2: float}> Histórico de novedades del empleado. */
    private array $novedadesHist = [];

    private ParametroSueldosResolver $parametros;

    private int $empleadoId = 0;

    private ?int $empresaId = null;

    private int $liquidacionId = 0;

    private int $periodoYm = 0;

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
        $this->liquidacionId = (int) ($liquidacion->id ?? 0);
        $this->periodoYm = (int) ($liquidacion->periodo ?: ($this->anio * 100 + $this->mes));
        $this->cargarBases($empleado, $this->fechaRef);
        // Tras cargar bases (categoría T o legajo C), B(1)/sueldo efectivo.
        if (isset($this->bases['1']) && $this->bases['1'] > 0) {
            $this->vars['empleado.sueldo_basico'] = $this->bases['1'];
        }
        $this->cargarHistorico($this->empleadoId, $this->liquidacionId);
        $this->cargarNovedades();
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
        $diaEgreso = 0;
        if ($this->fechaEgreso
            && (int) $this->fechaEgreso->year === $this->anio
            && (int) $this->fechaEgreso->month === $this->mes) {
            $diaEgreso = (int) $this->fechaEgreso->day;
        }
        $codigos = $this->codigosMaestrosEmpleado($emp);
        $vag = $this->variablesAgrupamiento($emp);
        $tipoVac = $this->tipoCorrida === 'vacaciones' ? 1 : 0;

        $this->vars = [
            'empleado.sueldo_basico' => (float) $emp->sueldo_basico,
            'empleado.jornal_dia' => (float) $emp->jornal_dia,
            'empleado.jornal_hora' => (float) $emp->jornal_hora,
            'empleado.antiguedad_anios' => $antAnios,
            'empleado.antiguedad_meses' => $antMeses,
            'empleado.legajo' => (int) $emp->legajo,
            'empleado.sexo' => (string) $emp->sexo,
            'empleado.categoria_id' => (int) $emp->categoria_id,
            'empleado.categoria_codigo' => $codigos['categoria'],
            'empleado.agrupamiento_id' => (int) $emp->agrupamiento_id,
            'empleado.agrupamiento_codigo' => $codigos['agrupamiento'],
            'empleado.sindicato_codigo' => $codigos['sindicato'],
            'empleado.obrasocial_codigo' => $codigos['obrasocial'],
            'empleado.lugartrabajo_codigo' => $codigos['lugartrabajo'],
            'empleado.empresa_codigo' => $codigos['empresa'],
            'empleado.agrupamiento_var1' => $vag[1],
            'empleado.agrupamiento_var2' => $vag[2],
            'empleado.agrupamiento_var3' => $vag[3],
            'empleado.agrupamiento_var4' => $vag[4],
            // Anita emp_modalidad (SIJP). La detracción Ley 27.430 usa detraccion(), no el 1002.
            'empleado.modalidad_sijp' => (int) ($emp->modalidad_sijp ?? 0),
            'empleado.condicion_sijp' => (string) ($emp->condicion_sijp ?? '01'),
            'empleado.mano_obra' => is_numeric($emp->mano_obra ?? null) ? (int) $emp->mano_obra : 0,
            // Anita GRRE/GRDE / emp_grp*: espejo de los primeros 3 del pivot N (o códigos sync)
            'empleado.grupo_remuneracion' => (int) ($emp->grupo_concepto_1_codigo ?? 0),
            'empleado.grupo_deduccion' => (int) ($emp->grupo_concepto_2_codigo ?? 0),
            'empleado.grupo_concepto_1' => (int) ($emp->grupo_concepto_1_codigo ?? 0),
            'empleado.grupo_concepto_2' => (int) ($emp->grupo_concepto_2_codigo ?? 0),
            'empleado.grupo_concepto_3' => (int) ($emp->grupo_concepto_3_codigo ?? 0),
            'empleado.grupo_aporte' => 0,
            'empleado.centrocosto_id' => (int) $emp->centrocosto_id,
            'empleado.motivo_egreso_id' => (int) ($emp->motivoegreso_id ?? 0),
            'empleado.motivo_egreso_clase' => $claseEgreso,
            'empleado.tiene_egreso' => $this->fechaEgreso !== null ? 1 : 0,
            'empleado.egreso_anio' => $this->fechaEgreso ? (int) $this->fechaEgreso->year : 0,
            'empleado.egreso_mes' => $this->fechaEgreso ? (int) $this->fechaEgreso->month : 0,
            'empleado.dia_egreso' => $diaEgreso,
            'empleado.fecha_ingreso_n' => $this->fechaIngreso ? (int) $this->fechaIngreso->format('Ymd') : 0,
            'empleado.fecha_egreso_n' => $this->fechaEgreso ? (int) $this->fechaEgreso->format('Ymd') : 0,
            'periodo.dias' => $dias,
            'periodo.dias_trabajados' => $dias,
            'periodo.anio' => $this->anio,
            'periodo.mes' => $this->mes,
            'periodo.periodo' => $this->anio * 100 + $this->mes,
            'periodo.fecha_liq' => (int) $fechaRef->format('Ymd'),
            'periodo.tipo_vacaciones' => $tipoVac,
            // Anita TLQ = tlq() sobre mael_tipo_liq (parser.fc). No confundir con TLIQ.
            'periodo.tipo_liq_n' => $this->tlqAnita((string) $liq->tipo, $this->mes),
            // Anita TLIQ = tliq() según emp_men_quin (frecuencia del legajo).
            'empleado.tliq_n' => $this->tliqAnita($emp),
            'corrida.tipo' => (string) $liq->tipo,
            // Escalares del concepto en curso (los setea el calculador)
            'cantidad' => 0.0,
            'valor' => 0.0,
            'factor' => 0.0,
        ];
    }

    /**
     * Códigos Anita de maestros vinculados al empleado (SIND/OSOC/LUGT/CATE/AGRU/EACT).
     *
     * @return array{categoria: int, agrupamiento: int, sindicato: int, obrasocial: int, lugartrabajo: int, empresa: int}
     */
    private function codigosMaestrosEmpleado(Empleado_Sueldos $emp): array
    {
        $out = [
            'categoria' => 0,
            'agrupamiento' => 0,
            'sindicato' => 0,
            'obrasocial' => 0,
            'lugartrabajo' => 0,
            'empresa' => (int) ($emp->empresa_id ?? 0),
        ];
        $map = [
            'categoria' => ['categoria_sueldos', (int) ($emp->categoria_id ?? 0)],
            'agrupamiento' => ['agrupamiento_sueldos', (int) ($emp->agrupamiento_id ?? 0)],
            'sindicato' => ['sindicato_sueldos', (int) ($emp->sindicato_id ?? 0)],
            'obrasocial' => ['obrasocial_sueldos', (int) ($emp->obrasocial_id ?? 0)],
            'lugartrabajo' => ['lugartrabajo_sueldos', (int) ($emp->lugartrabajo_id ?? 0)],
        ];
        foreach ($map as $clave => [$tabla, $id]) {
            if ($id <= 0) {
                continue;
            }
            try {
                $out[$clave] = (int) (DB::table($tabla)->where('id', $id)->value('codigo') ?? 0);
            } catch (\Throwable $e) {
                $out[$clave] = 0;
            }
        }

        return $out;
    }

    /**
     * Anita VAG1..VAG4 ← agrupamiento.variable1..4 (premio fallo de caja, etc.).
     *
     * @return array{1: float, 2: float, 3: float, 4: float}
     */
    private function variablesAgrupamiento(Empleado_Sueldos $emp): array
    {
        $out = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $id = (int) ($emp->agrupamiento_id ?? 0);
        if ($id <= 0) {
            return $out;
        }
        try {
            $row = DB::table('agrupamiento_sueldos')->where('id', $id)->first([
                'variable1', 'variable2', 'variable3', 'variable4',
            ]);
            if ($row === null) {
                return $out;
            }
            $out[1] = (float) ($row->variable1 ?? 0);
            $out[2] = (float) ($row->variable2 ?? 0);
            $out[3] = (float) ($row->variable3 ?? 0);
            $out[4] = (float) ($row->variable4 ?? 0);
        } catch (\Throwable $e) {
            // Columnas aún no migradas: VAG queda en 0.
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: int} [anios, meses totales]
     */
    private function calcularAntiguedad(Empleado_Sueldos $emp, Carbon $ref): array
    {
        $anterior = VacacionEscalaAntiguedad::componentesAntiguedadAnterior(
            $emp->antiguedad_anterior
        );
        if (! $emp->fecha_ingreso) {
            $meses = $anterior['anios'] * 12 + $anterior['meses'];

            return [intdiv($meses, 12), $meses];
        }
        $ingreso = $emp->fecha_ingreso instanceof Carbon ? $emp->fecha_ingreso : Carbon::parse($emp->fecha_ingreso);
        // Anita guarda antigüedad reconocida como duración aa-mm-dd. Restarla a
        // la fecha de ingreso preserva los acarreos de meses/días antes de medir.
        $ingresoComputable = $ingreso->copy()
            ->subYears($anterior['anios'])
            ->subMonths($anterior['meses'])
            ->subDays($anterior['dias']);
        $meses = (int) $ingresoComputable->diffInMonths($ref);

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

    /**
     * Carga nombrebase vigentes: si la categoría es origen T → tabla categoría;
     * si es C → bases del legajo. Espejo Anita cat_tabla.
     */
    private function cargarBases(Empleado_Sueldos $emp, Carbon $fechaRef): void
    {
        try {
            $fecha = $fechaRef->toDateString();
            $origen = null;
            $categoriaId = (int) ($emp->categoria_id ?? 0);
            if ($categoriaId > 0) {
                $origen = DB::table('categoria_sueldos')->where('id', $categoriaId)->value('origen_bases');
            }

            if ($categoriaId > 0 && CategoriaOrigenBases::usaTablaCategoria($origen)) {
                $filas = DB::table('categoria_base_sueldos as cb')
                    ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'cb.nombrebase_id')
                    ->where('cb.categoria_id', $categoriaId)
                    ->where(function ($q) use ($fecha) {
                        $q->whereNull('cb.fecha_vigencia')
                            ->orWhere('cb.fecha_vigencia', '<=', $fecha);
                    })
                    ->orderBy('nb.codigo')
                    ->orderBy('cb.fecha_vigencia')
                    ->get(['nb.codigo', 'cb.valor', 'cb.fecha_vigencia']);
            } else {
                $filas = DB::table('empleado_base_sueldos as eb')
                    ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'eb.nombrebase_id')
                    ->where('eb.empleado_id', (int) $emp->id)
                    ->where(function ($q) use ($fecha) {
                        $q->whereNull('eb.fecha_vigencia')
                            ->orWhere('eb.fecha_vigencia', '<=', $fecha);
                    })
                    ->orderBy('nb.codigo')
                    ->orderBy('eb.fecha_vigencia')
                    ->get(['nb.codigo', 'eb.valor', 'eb.fecha_vigencia']);
            }

            // Última vigencia por código (orderBy fecha asc → sobrescribe).
            foreach ($filas as $f) {
                $this->bases[(string) $f->codigo] = (float) $f->valor;
            }

            // Con origen T (tabla categoría) aún pueden vivir bases de legajo
            // (ej. mutual B(7) AMUPEJA). Completar códigos ausentes desde el empleado.
            if ($categoriaId > 0 && CategoriaOrigenBases::usaTablaCategoria($origen)) {
                $extra = DB::table('empleado_base_sueldos as eb')
                    ->join('nombrebase_sueldos as nb', 'nb.id', '=', 'eb.nombrebase_id')
                    ->where('eb.empleado_id', (int) $emp->id)
                    ->where(function ($q) use ($fecha) {
                        $q->whereNull('eb.fecha_vigencia')
                            ->orWhere('eb.fecha_vigencia', '<=', $fecha);
                    })
                    ->orderBy('nb.codigo')
                    ->orderBy('eb.fecha_vigencia')
                    ->get(['nb.codigo', 'eb.valor']);
                foreach ($extra as $f) {
                    $cod = (string) $f->codigo;
                    if (! isset($this->bases[$cod])) {
                        $this->bases[$cod] = (float) $f->valor;
                    }
                }
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

    /**
     * Carga novedades del empleado: mapa de la corrida/período actual + histórico por período.
     */
    private function cargarNovedades(): void
    {
        if ($this->empleadoId <= 0) {
            return;
        }
        try {
            $rows = DB::table('novedad_sueldos')
                ->where('empleado_id', $this->empleadoId)
                ->where('estado', '!=', 'anulada')
                ->get([
                    'liquidacion_id', 'concepto_codigo', 'valor1', 'valor2',
                    'periodo', 'fecha_desde', 'fecha_hasta',
                ]);
            foreach ($rows as $r) {
                $cod = (int) $r->concepto_codigo;
                if ($cod <= 0) {
                    continue;
                }
                $v1 = (float) $r->valor1;
                $v2 = (float) $r->valor2;
                if (NovedadSueldosVigencia::aplicaACorrida($r, $this->liquidacionId, $this->periodoYm)) {
                    $this->novedadesV1[$cod] = ($this->novedadesV1[$cod] ?? 0.0) + $v1;
                    $this->novedadesV2[$cod] = ($this->novedadesV2[$cod] ?? 0.0) + $v2;
                }
                // Histórico: one-shot por su período; recurrentes expandidas al período actual
                // y a su período base si lo tienen (VC/IC resuelven por período pedido).
                $perHist = (int) ($r->periodo ?? 0);
                if ($perHist > 0 && NovedadSueldosVigencia::aplicaAPeriodoHistorico($r, $perHist)) {
                    $this->novedadesHist[] = [
                        'periodo' => $perHist,
                        'cod' => $cod,
                        'v1' => $v1,
                        'v2' => $v2,
                    ];
                }
                // Para recurrentes: también registrar el período de la corrida en curso
                // si aplica (permite VC con meses atrás sobre vigencia viva).
                if (! empty($r->fecha_desde)
                    && $this->periodoYm > 0
                    && $this->periodoYm !== $perHist
                    && NovedadSueldosVigencia::aplicaAPeriodoHistorico($r, $this->periodoYm)
                ) {
                    $this->novedadesHist[] = [
                        'periodo' => $this->periodoYm,
                        'cod' => $cod,
                        'v1' => $v1,
                        'v2' => $v2,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Tabla ausente o sin migrar: novedad() queda en 0.
        }
    }

    private function novedadValor(int $conceptoCodigo, int $cual): float
    {
        if ($conceptoCodigo <= 0) {
            return 0.0;
        }

        return $cual === 2
            ? (float) ($this->novedadesV2[$conceptoCodigo] ?? 0.0)
            : (float) ($this->novedadesV1[$conceptoCodigo] ?? 0.0);
    }

    private function novedadRango(int $desde, int $hasta): float
    {
        if ($desde <= 0 || $hasta < $desde) {
            return 0.0;
        }
        $suma = 0.0;
        foreach ($this->novedadesV1 as $cod => $valor) {
            if ($cod >= $desde && $cod <= $hasta) {
                $suma += $valor;
            }
        }

        return $suma;
    }

    private function novedadEnPeriodo(int $conceptoCodigo, int $periodoYm, int $cual): float
    {
        if ($conceptoCodigo <= 0 || $periodoYm <= 0) {
            return 0.0;
        }
        // Primero el mapa histórico ya expandido.
        $suma = 0.0;
        $vioHist = false;
        foreach ($this->novedadesHist as $h) {
            if ($h['cod'] === $conceptoCodigo && $h['periodo'] === $periodoYm) {
                $suma += $cual === 2 ? $h['v2'] : $h['v1'];
                $vioHist = true;
            }
        }
        if ($vioHist) {
            return $suma;
        }

        // Recurrentes: evaluar vigencia on-the-fly para el período pedido (VC/IC).
        try {
            $rows = DB::table('novedad_sueldos')
                ->where('empleado_id', $this->empleadoId)
                ->where('concepto_codigo', $conceptoCodigo)
                ->where('estado', '!=', 'anulada')
                ->whereNotNull('fecha_desde')
                ->get(['liquidacion_id', 'periodo', 'fecha_desde', 'fecha_hasta', 'valor1', 'valor2']);
            foreach ($rows as $r) {
                if (NovedadSueldosVigencia::aplicaAPeriodoHistorico($r, $periodoYm)) {
                    $suma += $cual === 2 ? (float) $r->valor2 : (float) $r->valor1;
                }
            }
        } catch (\Throwable $e) {
            return 0.0;
        }

        return $suma;
    }

    /**
     * VC(concepto, mesesAtras|periodoYYYYMM): valor1 histórico.
     * Si el 2º arg >= 190001 se interpreta como período YYYYMM; si no, meses hacia atrás.
     */
    private function novedadHist(int $conceptoCodigo, int $mesesOPeriodo): float
    {
        if ($conceptoCodigo <= 0) {
            return 0.0;
        }
        if ($mesesOPeriodo >= 190001) {
            return $this->novedadEnPeriodo($conceptoCodigo, $mesesOPeriodo, 1);
        }
        $meses = max(1, $mesesOPeriodo);
        $anio = (int) floor($this->periodoYm / 100);
        $mes = $this->periodoYm % 100;
        $mes -= $meses;
        while ($mes <= 0) {
            $mes += 12;
            $anio--;
        }
        $periodo = $anio * 100 + $mes;

        return $this->novedadEnPeriodo($conceptoCodigo, $periodo, 1);
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

    /**
     * Snapshot para el debugger de fórmulas (no muta estado).
     *
     * @return array{
     *   variables: array<string, float|int|string>,
     *   acumuladores: array<string, float>,
     *   conceptos_calculados: array<int, float>,
     *   novedades_v1: array<int, float>,
     *   novedades_v2: array<int, float>,
     *   bases: array<string, float>
     * }
     */
    public function snapshotDebug(): array
    {
        ksort($this->vars);
        $acum = $this->acum;
        ksort($acum);
        $conceptos = $this->conceptos;
        ksort($conceptos);
        $bases = $this->bases;
        ksort($bases);

        return [
            'variables' => $this->vars,
            'acumuladores' => $acum,
            'conceptos_calculados' => $conceptos,
            'novedades_v1' => $this->novedadesV1,
            'novedades_v2' => $this->novedadesV2,
            'bases' => $bases,
        ];
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
            // Dominio Anita (stubs / parciales hasta tener novedades y tablas auxiliares)
            'novedad', 'novedad2', 'novedad_rango', 'novedad_periodo', 'novedad_hist',
            'novedad_empresa', 'novedad2_empresa',
            'dias_trabajados', 'dias_no_trabajados', 'dias_del_mes', 'meses_trabajados',
            'im_liquidacion', 'im_empresa', 'valor_liquidacion', 'cantidad_liquidacion',
            'aux_rango', 'acum_periodos', 'acum_variable_periodos', 'acum_anio', 'mejor_mes_acum',
            'acum_cantidad_concepto', 'acum_valor_concepto', 'acum_importe_concepto', 'acum_importe_por_concepto',
            'aguinaldo', 'cantidad_vacaciones', 'dias_vacaciones', 'total_vacaciones',
            'cantidad_asignacion', 'importe_asignacion', 'tabla_empleado', 'descuento_bruto',
            'base_categoria', 'es_empresa_madre', 'es_asociacion', 'im_concepto_rem', 'val',
            'antiguedad_tabla', 'detraccion', 'detraccion_lsd',
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

            case 'novedad':
                return $this->novedadValor((int) ($args[0] ?? 0), 1);
            case 'novedad2':
                return $this->novedadValor((int) ($args[0] ?? 0), 2);
            case 'novedad_rango':
                return $this->novedadRango((int) ($args[0] ?? 0), (int) ($args[1] ?? 0));
            case 'novedad_periodo':
                return $this->novedadEnPeriodo((int) ($args[0] ?? 0), (int) ($args[1] ?? 0), 1);
            case 'novedad_hist':
                return $this->novedadHist((int) ($args[0] ?? 0), (int) ($args[1] ?? 1));
            // ---- Dominio Anita: stubs seguros (0) hasta cablear aux / otras tablas ----
            case 'novedad_empresa':
            case 'novedad2_empresa':
            case 'im_liquidacion':
            case 'im_empresa':
            case 'valor_liquidacion':
            case 'cantidad_liquidacion':
            case 'aux_rango':
            case 'acum_periodos':
            case 'acum_variable_periodos':
            case 'acum_anio':
            case 'mejor_mes_acum':
            case 'acum_cantidad_concepto':
            case 'acum_valor_concepto':
            case 'acum_importe_concepto':
            case 'acum_importe_por_concepto':
            case 'aguinaldo':
            case 'cantidad_vacaciones':
            case 'dias_vacaciones':
            case 'total_vacaciones':
            case 'cantidad_asignacion':
            case 'importe_asignacion':
            case 'tabla_empleado':
                return 0.0;
            case 'descuento_bruto':
                // Anita DTBR(concepto): factor del haber si no se liquidó ya en el período.
                return $this->descuentoBruto((int) ($args[0] ?? 0));
            case 'detraccion':
            case 'detraccion_lsd':
                // Ley 27.430 art. 4. Reemplaza la fórmula Anita del 1002.
                return LsdDetraccionSupport::importeParaFormula(
                    $this->parametros,
                    (int) round((float) ($this->vars['periodo.dias_trabajados'] ?? $this->vars['periodo.dias'] ?? 30)),
                    (int) ($this->vars['empleado.modalidad_sijp'] ?? 0),
                    (string) ($this->vars['empleado.condicion_sijp'] ?? '01'),
                    (float) ($this->acum['REM'] ?? 0),
                );
            case 'base_categoria':
            case 'im_concepto_rem':
                return 0.0;
            case 'antiguedad_tabla':
                // Anita ANT(tabla): suma % de tramos con anio <= años de antigüedad.
                return AntiguedadTablaResolver::porcentaje(
                    (int) ($args[0] ?? 0),
                    (float) ($this->vars['empleado.antiguedad_anios'] ?? 0),
                    $this->empresaId
                );
            case 'dias_trabajados':
                return (float) ($this->vars['periodo.dias_trabajados'] ?? $this->vars['periodo.dias'] ?? 0);
            case 'dias_no_trabajados':
                $tot = (float) ($this->vars['periodo.dias'] ?? 30);
                $trab = (float) ($this->vars['periodo.dias_trabajados'] ?? $tot);

                return max(0.0, $tot - $trab);
            case 'dias_del_mes':
                return (float) ($this->vars['periodo.dias'] ?? 30);
            case 'meses_trabajados':
                return (float) ($this->vars['empleado.antiguedad_meses'] ?? 0);
            case 'es_empresa_madre':
            case 'es_asociacion':
                return 0.0;
            case 'val':
                return 1.0;
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
     * Anita tlq() (parser.fc): código numérico del tipo de maeliq.
     * Biyemas: mensuales suelen ser MENSUAL_SQUIN (=3); finales del mismo corte también.
     */
    private function tlqAnita(string $tipoCorrida, int $mes): int
    {
        return match ($tipoCorrida) {
            'quincena_1' => 1,
            'quincena_2' => 2,
            'mensual', 'final' => 3, // MAEL_MENSUAL_SQUIN (FFEP/ART fija usa TLQ=3)
            'semanal' => 4,
            'vacaciones' => 5,
            'sac' => ($mes >= 7 ? 7 : 6), // 1er / 2do aguinaldo
            'ajuste' => 8, // MAEL_ESP_CRET (aprox.)
            default => 3,
        };
    }

    /**
     * Anita tliq(): frecuencia de liquidación del legajo (emp_men_quin).
     */
    private function tliqAnita(Empleado_Sueldos $emp): int
    {
        $menQuin = (int) ($emp->codigo_liquidacion ?? 0);
        if ($menQuin === 1) {
            // Quincenal: 1 o 2 según corrida actual.
            return $this->tipoCorrida === 'quincena_2' ? 2 : 1;
        }
        if ($menQuin === 2) {
            return 3;
        }

        return 4;
    }

    /**
     * Anita DTBR(concepto): devuelve hab_factor del concepto si aún no se
     * liquidó en otra corrida del mismo período; si ya hay importe, 0.
     */
    private function descuentoBruto(int $codigoConcepto): float
    {
        if ($codigoConcepto <= 0) {
            return 0.0;
        }

        $factor = $this->factorConcepto($codigoConcepto);
        if (abs($factor) < 0.0000001) {
            return 0.0;
        }

        // Ya calculado en esta corrida.
        if (isset($this->conceptos[$codigoConcepto]) && abs($this->conceptos[$codigoConcepto]) > 0.0000001) {
            return 0.0;
        }

        // Otras corridas del mismo período / mismo legajo.
        if ($this->empleadoId > 0 && $this->periodoYm > 0) {
            try {
                $ya = (float) DB::table('liquidacion_detalle_sueldos as d')
                    ->join('liquidacion_recibo_sueldos as r', 'r.id', '=', 'd.recibo_id')
                    ->join('liquidacion_sueldos as l', 'l.id', '=', 'r.liquidacion_id')
                    ->where('r.empleado_id', $this->empleadoId)
                    ->where('d.concepto_codigo', $codigoConcepto)
                    ->where('l.periodo', (string) $this->periodoYm)
                    ->when($this->liquidacionId > 0, fn ($q) => $q->where('l.id', '!=', $this->liquidacionId))
                    ->sum('d.importe');
                if (abs($ya) > 0.0000001) {
                    return 0.0;
                }
            } catch (\Throwable $e) {
                // sin tablas: usa factor
            }
        }

        return $factor;
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
