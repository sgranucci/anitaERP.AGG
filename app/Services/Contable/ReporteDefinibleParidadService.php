<?php

namespace App\Services\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContable;
use App\Repositories\Contable\ReporteContableRepository;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAnitaSaldoReader;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleProcesador;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSaldoReader;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use App\Support\Contable\SumasSaldos\SumasSaldosRuntimeSupport;
use App\Support\Contable\SumasSaldosListadoFiltros;
use Illuminate\Support\Collection;

/**
 * Paridad del informe definible: mismo árbol y mismas asignaciones calculados sobre
 * asientos anitaERP vs ctamov + subdiario de Anita (l-infomae).
 *
 * La única diferencia entre ambos brazos es la fuente de datos: cualquier desvío es
 * de datos (asiento faltante, cuenta sin migrar) o de definición (cuenta fuera del plan).
 */
class ReporteDefinibleParidadService
{
    public const TOLERANCIA_DEFAULT = 0.05;

    /**
     * Colección con `nombreempresa` para resolver logos de cabecera en PDF/Excel.
     *
     * @param  list<int>  $empresaIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function coleccionEmpresasParaLogos(array $empresaIds): Collection
    {
        return Empresa::query()
            ->whereIn('id', array_map('intval', $empresaIds))
            ->orderBy('id')
            ->pluck('nombre')
            ->map(static fn ($nombre) => (object) ['nombreempresa' => (string) $nombre]);
    }

    /**
     * Fuente de verdad del período: la contabilidad Anita se subió al ERP hasta
     * `contable.mayor_plano_cuenta.fuente_erp_hasta` (31/12/2025), así que hasta esa fecha manda
     * anitaERP y después manda Anita, hasta que se importe el resto.
     *
     * @return array{fuente: string, etiqueta: string, detalle: string}
     */
    public function fuenteVerdadPeriodo(string $fechaHasta): array
    {
        $corte = trim((string) config('contable.mayor_plano_cuenta.fuente_erp_hasta', ''));
        $corteYmd = (int) (preg_replace('/\D/', '', $corte) ?: 0);
        $hastaYmd = (int) (preg_replace('/\D/', '', substr($fechaHasta, 0, 10)) ?: 0);

        if ($corteYmd > 0 && $hastaYmd > 0 && $hastaYmd <= $corteYmd) {
            return [
                'fuente' => 'erp',
                'etiqueta' => 'anitaERP',
                'detalle' => 'La contabilidad Anita se importó al ERP hasta '
                    .date('d/m/Y', strtotime($corte)).': para este período manda anitaERP y una diferencia indica que Anita ya no conserva el movimiento.',
            ];
        }

        return [
            'fuente' => 'anita',
            'etiqueta' => 'Anita',
            'detalle' => 'Período posterior al corte de importación: manda Anita y una diferencia indica asientos todavía no subidos al ERP.',
        ];
    }

    public function __construct(
        private readonly ReporteContableRepository $repository,
        private readonly ReporteDefinibleProcesador $procesador,
        private readonly ReporteDefinibleSaldoReader $saldoReaderErp,
        private readonly ReporteDefinibleAnitaSaldoReader $saldoReaderAnita,
        private readonly ReporteDefinibleReporteService $reporteService,
    ) {
    }

    /**
     * Valores tal como los imprime el informe (puede leer el snapshot cuentacontable_saldo_mes).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{valores: array<int, float>, fuente: string}
     */
    private function valoresDelInformeImpreso(int $reporteId, array $filtros, string $baseSaldo): array
    {
        $resultado = $this->reporteService->ejecutar($reporteId, array_merge($filtros, [
            'columnas_layout' => ReporteDefinibleSupport::LAYOUT_PERIODOS,
            'layout_id' => 0,
            'ocultar_ceros' => false,
            'mostrar_cuentas' => false,
            'incluir_presupuesto' => false,
        ]));

        $keys = [];
        foreach ($resultado['columnas'] ?? [] as $columna) {
            if (($columna['tipo'] ?? '') === 'actual') {
                $keys[] = (string) ($columna['key'] ?? '');
            }
        }
        // Base ejercicio: cada columna ya es acumulada, se toma la última; base período: se suman los meses.
        $soloUltima = $baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO;

        $valores = [];
        foreach ($resultado['filas'] ?? [] as $fila) {
            if (($fila['kind'] ?? '') !== 'rubro' || ! is_array($fila['saldos'] ?? null)) {
                continue;
            }
            $keysUsadas = $soloUltima && $keys !== [] ? [$keys[count($keys) - 1]] : $keys;
            $suma = 0.0;
            foreach ($keysUsadas as $key) {
                $suma += (float) ($fila['saldos'][$key] ?? 0);
            }
            $valores[(int) $fila['rubro_id']] = round($suma, 2);
        }

        return ['valores' => $valores, 'fuente' => (string) ($resultado['fuente'] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function comparar(int $reporteId, array $filtros, ?float $tolerancia = null): array
    {
        SumasSaldosRuntimeSupport::elevarLimites();
        $tolerancia = $tolerancia !== null ? abs($tolerancia) : self::TOLERANCIA_DEFAULT;

        $reporteErp = $this->repository->findConEstructura($reporteId);
        if (! $reporteErp) {
            return $this->vacio(['Informe no encontrado.']);
        }

        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0
        )));
        if ($empresaIds === []) {
            return $this->vacio(['Debe seleccionar al menos una empresa.']);
        }

        [$fechaDesde, $fechaHasta] = $this->ventana($filtros);
        if ($fechaDesde === '' || $fechaHasta === '') {
            return $this->vacio(['Período o fechas inválidas.']);
        }

        $advertencias = [];
        $baseSaldo = (string) ($filtros['base_saldo'] ?? ReporteDefinibleSupport::BASE_SALDO_PERIODO);
        if ($baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO) {
            $fechaDesde = sprintf('%04d-01-01', (int) substr($fechaHasta, 0, 4));
            $advertencias[] = 'Base saldo de ejercicio: se comparan movimientos desde el 01/01.';
        }

        $modoAsientos = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $monedaId = (int) ($filtros['moneda_id'] ?? 0) ?: null;
        $soloOrigen = (bool) ($filtros['solo_moneda_origen'] ?? false);
        $nivelMax = (int) ($filtros['nivel_max'] ?? 0);
        $filtroCcosto = $this->filtroCcosto($filtros);

        $codigos = $this->procesador->codigosRealesDelReporte($reporteErp);
        if ($codigos === []) {
            return $this->vacio(['El informe no tiene cuentas asignadas (nada que comparar).']);
        }

        $movsErp = $this->saldoReaderErp->listarMovimientos(
            $empresaIds,
            $fechaDesde,
            $fechaHasta,
            $codigos,
            $modoAsientos,
            $monedaId ?: CuentacontableSaldoMesSupport::monedaLocalId(),
            $soloOrigen,
        );

        $anita = $this->saldoReaderAnita->listarMovimientos(
            $empresaIds,
            $fechaDesde,
            $fechaHasta,
            $codigos,
            $modoAsientos,
            $monedaId,
            $soloOrigen,
        );
        foreach ($anita['errores'] as $error) {
            $advertencias[] = 'Bridge Anita: '.$error;
        }

        $ladoErp = $this->procesador->totalesDesdeMovimientos($reporteErp, $movsErp, $nivelMax, $filtroCcosto);
        $reporteAnita = $this->repository->findConEstructura($reporteId);
        $ladoAnita = $this->procesador->totalesDesdeMovimientos($reporteAnita, $anita['movimientos'], $nivelMax, $filtroCcosto);

        $impreso = $this->valoresDelInformeImpreso($reporteId, $filtros, $baseSaldo);

        $filas = [];
        $totalRubros = 0;
        $conDiferencia = 0;
        $conDiferenciaMotor = 0;
        $sumaAbsDiferencia = 0.0;
        $peor = null;
        $peorMotor = null;

        foreach ($reporteErp->rubros->sortBy(['orden', 'id']) as $rubro) {
            $rid = (int) $rubro->id;
            if ((string) $rubro->tipo === ReporteDefinibleSupport::RUBRO_TEXTO) {
                continue;
            }
            if ($nivelMax > 0 && (int) $rubro->nivel > $nivelMax) {
                continue;
            }

            $valorErp = (float) ($ladoErp['totales'][$rid] ?? 0.0);
            $valorAnita = (float) ($ladoAnita['totales'][$rid] ?? 0.0);
            $diferencia = round($valorErp - $valorAnita, 2);
            $cuadra = abs($diferencia) <= $tolerancia;
            $totalRubros++;

            $valorImpreso = array_key_exists($rid, $impreso['valores']) ? (float) $impreso['valores'][$rid] : null;
            $diferenciaMotor = $valorImpreso !== null ? round($valorImpreso - $valorErp, 2) : null;
            $cuadraMotor = $diferenciaMotor === null || abs($diferenciaMotor) <= $tolerancia;
            if (! $cuadraMotor) {
                $conDiferenciaMotor++;
                if ($peorMotor === null || abs($diferenciaMotor) > abs($peorMotor['diferencia'])) {
                    $peorMotor = [
                        'codigo' => (string) ($rubro->codigo_linea ?? ''),
                        'nombre' => (string) $rubro->nombre,
                        'diferencia' => $diferenciaMotor,
                    ];
                }
            }

            if (! $cuadra) {
                $conDiferencia++;
                $sumaAbsDiferencia += abs($diferencia);
                if ($peor === null || abs($diferencia) > abs($peor['diferencia'])) {
                    $peor = ['codigo' => (string) ($rubro->codigo_linea ?? ''), 'nombre' => (string) $rubro->nombre, 'diferencia' => $diferencia];
                }
            }

            $filas[] = [
                'rubro_id' => $rid,
                'codigo' => (string) ($rubro->codigo_linea ?? ''),
                'nombre' => (string) $rubro->nombre,
                'nivel' => (int) $rubro->nivel,
                'tipo' => (string) $rubro->tipo,
                'impreso' => $valorImpreso !== null ? round($valorImpreso, 2) : null,
                'diferencia_motor' => $diferenciaMotor,
                'cuadra_motor' => $cuadraMotor,
                'erp' => round($valorErp, 2),
                'anita' => round($valorAnita, 2),
                'diferencia' => $diferencia,
                'diferencia_pct' => abs($valorAnita) > 0.005 ? round($diferencia / abs($valorAnita) * 100, 2) : null,
                'cuadra' => $cuadra,
                'cuentas' => $cuadra ? [] : $this->cuentasConDiferencia(
                    $ladoErp['detalle'][$rid] ?? [],
                    $ladoAnita['detalle'][$rid] ?? [],
                    $tolerancia
                ),
            ];
        }

        $totalErp = array_sum(array_map('floatval', array_column(array_filter($filas, fn ($f) => (int) $f['nivel'] <= 1), 'erp')));
        $totalAnita = array_sum(array_map('floatval', array_column(array_filter($filas, fn ($f) => (int) $f['nivel'] <= 1), 'anita')));

        $fueraPlan = $this->cuentasFueraDelPlanConMovimiento($anita['movimientos'], $codigos, $tolerancia);
        if ($fueraPlan !== []) {
            $advertencias[] = sprintf(
                '%d cuenta(s) del informe no existen en el plan ERP y tienen movimiento en Anita: el ERP nunca podrá igualar esas líneas hasta darlas de alta.',
                count($fueraPlan)
            );
        }

        if ($this->definicionUsaCcosto($reporteErp)) {
            $advertencias[] = 'La definición filtra centros de costo: el c.costo Anita sale de ctav_ccosto / subd_ccosto_*.';
        }
        if ($conDiferenciaMotor > 0) {
            $advertencias[] = sprintf(
                'El informe impreso (fuente %s) difiere del cálculo por asientos en %d rubro(s): revise el snapshot de sumas y saldos del período.',
                $impreso['fuente'] !== '' ? $impreso['fuente'] : 'desconocida',
                $conDiferenciaMotor
            );
        }

        if ($anita['stats']['movimientos'] === 0) {
            $advertencias[] = 'Anita no devolvió movimientos para el período y cuentas del informe (revise bridge, empresa o rango de fechas).';
        }

        $verdad = $this->fuenteVerdadPeriodo($fechaHasta);
        if ($conDiferencia > 0) {
            $advertencias[] = 'Fuente de verdad del período: '.$verdad['etiqueta'].'. '.$verdad['detalle'];
        }

        return [
            'reporte' => $reporteErp,
            'filas' => $filas,
            'parametros' => [
                'empresa_ids' => $empresaIds,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'base_saldo' => $baseSaldo,
                'modo_inclusion_asientos' => $modoAsientos,
                'moneda_id' => $monedaId,
                'solo_moneda_origen' => $soloOrigen,
                'nivel_max' => $nivelMax,
                'tolerancia' => $tolerancia,
                'cuentas' => count($codigos),
                'verdad' => $verdad,
            ],
            'resumen' => [
                'rubros' => $totalRubros,
                'con_diferencia' => $conDiferencia,
                'cuadra' => $conDiferencia === 0,
                'fuente_impreso' => $impreso['fuente'],
                'con_diferencia_motor' => $conDiferenciaMotor,
                'cuadra_motor' => $conDiferenciaMotor === 0,
                'peor_motor' => $peorMotor,
                'suma_abs_diferencia' => round($sumaAbsDiferencia, 2),
                'peor' => $peor,
                'total_erp' => round((float) $totalErp, 2),
                'total_anita' => round((float) $totalAnita, 2),
            ],
            'cuentas_fuera_plan' => $fueraPlan,
            'stats' => [
                'movimientos_erp' => count($movsErp),
                'movimientos_anita' => $anita['stats']['movimientos'],
                'ctamov_filas' => $anita['stats']['ctamov'],
                'subdiario_filas' => $anita['stats']['subdiario'],
            ],
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    /**
     * @param  array<int, float>  $erp
     * @param  array<int, float>  $anita
     * @return list<array{codigo: int, codigo_fmt: string, erp: float, anita: float, diferencia: float}>
     */
    private function cuentasConDiferencia(array $erp, array $anita, float $tolerancia): array
    {
        $codigos = array_values(array_unique(array_merge(array_keys($erp), array_keys($anita))));
        sort($codigos);
        $out = [];
        foreach ($codigos as $codigo) {
            $a = (float) ($erp[$codigo] ?? 0.0);
            $b = (float) ($anita[$codigo] ?? 0.0);
            $dif = round($a - $b, 2);
            if (abs($dif) <= $tolerancia) {
                continue;
            }
            $out[] = [
                'codigo' => (int) $codigo,
                'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta((int) $codigo),
                'erp' => round($a, 2),
                'anita' => round($b, 2),
                'diferencia' => $dif,
            ];
        }
        usort($out, fn ($x, $y) => abs($y['diferencia']) <=> abs($x['diferencia']));

        return array_slice($out, 0, 25);
    }

    /**
     * Cuentas asignadas al informe que Anita movió pero no existen como imputables en el plan ERP:
     * son diferencias estructurales (alta de cuenta pendiente), no errores de cálculo.
     *
     * @param  list<array{codigo: int, monto: float}>  $movimientosAnita
     * @param  list<int>  $codigosInforme
     * @return list<array{codigo: int, codigo_fmt: string, anita: float}>
     */
    private function cuentasFueraDelPlanConMovimiento(array $movimientosAnita, array $codigosInforme, float $tolerancia): array
    {
        if ($movimientosAnita === [] || $codigosInforme === []) {
            return [];
        }

        $porCodigo = [];
        foreach ($movimientosAnita as $mov) {
            $codigo = (int) $mov['codigo'];
            $porCodigo[$codigo] = ($porCodigo[$codigo] ?? 0.0) + (float) $mov['monto'];
        }

        $enPlan = array_fill_keys(
            Cuentacontable::query()
                ->where('tipocuenta', 1)
                ->whereIn('codigo', array_map('strval', array_keys($porCodigo)))
                ->pluck('codigo')
                ->map(fn ($c) => (int) $c)
                ->all(),
            true
        );

        $out = [];
        foreach ($porCodigo as $codigo => $monto) {
            if (isset($enPlan[$codigo]) || abs($monto) <= $tolerancia) {
                continue;
            }
            $out[] = [
                'codigo' => $codigo,
                'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                'anita' => round((float) $monto, 2),
            ];
        }
        usort($out, fn ($a, $b) => abs($b['anita']) <=> abs($a['anita']));

        return array_slice($out, 0, 50);
    }

    private function definicionUsaCcosto(ReporteContable $reporte): bool
    {
        foreach ($reporte->rubros as $rubro) {
            foreach ($rubro->cuentas as $cuenta) {
                if (ReporteDefinibleSupport::normalizarCargaCcosto((string) $cuenta->carga_ccosto) !== ReporteDefinibleSupport::CCOSTO_SIN) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{desde: int, hasta: int}|null
     */
    private function filtroCcosto(array $filtros): ?array
    {
        $desde = (int) ($filtros['ccosto_desde'] ?? 0);
        $hasta = (int) ($filtros['ccosto_hasta'] ?? 0);
        if ($desde <= 0 && $hasta <= 0) {
            return null;
        }

        return ['desde' => $desde, 'hasta' => $hasta > 0 ? $hasta : $desde];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0: string, 1: string}
     */
    private function ventana(array $filtros): array
    {
        $modo = (string) ($filtros['modo_periodo'] ?? SumasSaldosListadoFiltros::MODO_PERIODOS);
        if ($modo === SumasSaldosListadoFiltros::MODO_RANGO) {
            return [
                trim((string) ($filtros['fecha_desde'] ?? '')),
                trim((string) ($filtros['fecha_hasta'] ?? '')),
            ];
        }

        $desde = (int) ($filtros['periodo_desde'] ?? 0);
        $hasta = (int) ($filtros['periodo_hasta'] ?? 0);
        if ($desde <= 0) {
            $desde = ((int) ($filtros['anio_desde'] ?? 0)) * 100 + (int) ($filtros['mes_desde'] ?? 0);
        }
        if ($hasta <= 0) {
            $hasta = ((int) ($filtros['anio_hasta'] ?? 0)) * 100 + (int) ($filtros['mes_hasta'] ?? 0);
        }
        if ($desde < 190001 || $hasta < $desde) {
            return ['', ''];
        }

        return ReporteDefinibleSaldoReader::fechasDesdePeriodos($desde, $hasta);
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    private function vacio(array $advertencias): array
    {
        return [
            'reporte' => null,
            'filas' => [],
            'parametros' => [],
            'resumen' => [
                'rubros' => 0, 'con_diferencia' => 0, 'cuadra' => false, 'suma_abs_diferencia' => 0.0,
                'peor' => null, 'total_erp' => 0.0, 'total_anita' => 0.0,
                'fuente_impreso' => '', 'con_diferencia_motor' => 0, 'cuadra_motor' => true, 'peor_motor' => null,
            ],
            'cuentas_fuera_plan' => [],
            'stats' => ['movimientos_erp' => 0, 'movimientos_anita' => 0, 'ctamov_filas' => 0, 'subdiario_filas' => 0],
            'advertencias' => $advertencias,
        ];
    }
}
