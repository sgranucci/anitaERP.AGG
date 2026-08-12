<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableCuenta;
use App\Models\Contable\ReporteContableRubro;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use App\Support\Contable\SumasSaldosListadoFiltros;

/**
 * Ejecuta un reporte definible sobre saldos anitaERP.
 */
class ReporteDefinibleProcesador
{
    public function __construct(
        private readonly SumasSaldosProcesador $sumasSaldosProcesador,
        private readonly ReporteDefinibleSaldoReader $saldoReader,
        private readonly ReporteDefiniblePartidaPlanReader $partidaPlanReader,
        private readonly ReporteDefinibleConjuntoSupport $conjuntoSupport,
        private readonly ReporteDefinibleLayoutResolver $layoutResolver,
        private readonly ReporteDefinibleCuentaRangoSupport $cuentaRangoSupport,
        private readonly ReporteDefinibleEliminacionSupport $eliminacionSupport,
        private readonly ReporteDefinibleParticipacionSupport $participacionSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function ejecutar(ReporteContable $reporte, array $filtros): array
    {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0
        )));
        if ($empresaIds === []) {
            return $this->vacio(['Debe seleccionar al menos una empresa.']);
        }

        $modo = (string) ($filtros['modo_periodo'] ?? SumasSaldosListadoFiltros::MODO_PERIODOS);
        if (! in_array($modo, [SumasSaldosListadoFiltros::MODO_PERIODOS, SumasSaldosListadoFiltros::MODO_RANGO], true)) {
            $modo = SumasSaldosListadoFiltros::MODO_PERIODOS;
        }

        $layout = (string) ($filtros['columnas_layout'] ?? ReporteDefinibleSupport::LAYOUT_PERIODOS);
        if (! isset(ReporteDefinibleSupport::layoutsColumnas()[$layout])) {
            $layout = ReporteDefinibleSupport::LAYOUT_PERIODOS;
        }

        $layoutId = (int) ($filtros['layout_id'] ?? 0);
        $layoutModel = $layoutId > 0 ? $this->layoutResolver->find($layoutId) : null;

        [$fechaDesde, $fechaHasta, $periodos, $advFechas] = $this->resolverVentana($modo, $filtros);
        if ($fechaDesde === '' || $fechaHasta === '') {
            return $this->vacio($advFechas !== [] ? $advFechas : ['Período o fechas inválidas.']);
        }

        $baseSaldo = (string) ($filtros['base_saldo'] ?? ReporteDefinibleSupport::BASE_SALDO_PERIODO);
        if (! in_array($baseSaldo, [ReporteDefinibleSupport::BASE_SALDO_PERIODO, ReporteDefinibleSupport::BASE_SALDO_EJERCICIO], true)) {
            $baseSaldo = ReporteDefinibleSupport::BASE_SALDO_PERIODO;
        }

        $mostrarCuentas = (bool) ($filtros['mostrar_cuentas'] ?? false);
        $nivelMax = (int) ($filtros['nivel_max'] ?? 0);
        $modoAsientos = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $monedaId = (int) ($filtros['moneda_id'] ?? CuentacontableSaldoMesSupport::monedaLocalId());
        $soloOrigen = (bool) ($filtros['solo_moneda_origen'] ?? false);
        $filtroCcosto = $this->filtroCcostoRuntime($filtros);

        $reporte->load(['rubros.cuentas.ccostos', 'rubros.cuentas.cuentacontable']);
        $this->conjuntoSupport->expandirEnReporte($reporte);

        if ($layoutModel !== null) {
            return $this->ejecutarLayoutDiseñado(
                $reporte,
                $filtros,
                $layoutModel,
                $empresaIds,
                $fechaDesde,
                $fechaHasta,
                $modo,
                $baseSaldo,
                $mostrarCuentas,
                $nivelMax,
                $modoAsientos,
                $monedaId,
                $soloOrigen,
                $filtroCcosto,
                $advFechas
            );
        }

        $usaCcostoDef = $this->definicionUsaCcosto($reporte);
        $forzarAsientos = $modo === SumasSaldosListadoFiltros::MODO_RANGO
            || $usaCcostoDef
            || $filtroCcosto !== null
            || $layout === ReporteDefinibleSupport::LAYOUT_COMPARATIVO
            || $layout === ReporteDefinibleSupport::LAYOUT_CCOSTO
            || ! empty($filtros['incluir_presupuesto']);

        $advertencias = $advFechas;
        if (! empty($filtros['incluir_presupuesto'])) {
            $advertencias[] = 'Incluye asientos de presupuesto (lectura por asientos).';
        }
        if ($usaCcostoDef) {
            $advertencias[] = 'Filtros de centro de costo de la definición aplicados (lectura por asientos).';
        }
        if ($filtroCcosto !== null) {
            $advertencias[] = sprintf('Filtro runtime c.costo %d–%d.', $filtroCcosto['desde'], $filtroCcosto['hasta']);
        }
        if ($layout === ReporteDefinibleSupport::LAYOUT_CCOSTO) {
            $advertencias[] = 'Apertura por centros de costo en columnas (lectura por asientos).';
        }

        $codigos = $this->codigosDelReporte($reporte);
        $fuente = 'ninguna';
        $ctxCons = $this->contextoConsolidacion($reporte, $filtros, $empresaIds, $fechaHasta, $advertencias);
        $reglasEli = $ctxCons['reglas'];
        $factoresPart = $ctxCons['factores'];
        $fechaCierreFx = $ctxCons['fecha_cierre'];
        if ($ctxCons['forzar_asientos']) {
            $forzarAsientos = true;
        }

        /** @var list<array<string, mixed>> $columnas */
        $columnas = [];
        /** @var array<int, array<string, float>> $directoActual rubroId => [colKey => valor] */
        $directoActual = [];
        /** @var array<int, array<string, float>> $directoPlan */
        $directoPlan = [];
        /** @var array<int, array<string, array<int, float>>> $detalleCuentas rubroId => colKey => codigo => valor */
        $detalleCuentas = [];

        if ($layout === ReporteDefinibleSupport::LAYOUT_CCOSTO) {
            $fdMov = $baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO
                ? sprintf('%04d-01-01', (int) substr($fechaDesde, 0, 4))
                : $fechaDesde;
            $movs = $this->cargarMovimientosConsolidados(
                $empresaIds, $fdMov, $fechaHasta, $codigos, $modoAsientos, $monedaId, $soloOrigen,
                $fechaCierreFx, $reglasEli, $factoresPart
            );
            $fuente = 'asientos';
            $codigosCc = $this->saldoReader->codigosCcostoEnMovimientos($movs);
            $armado = ReporteDefinibleDimensionSupport::armarColumnasCcosto(
                $codigosCc,
                $filtroCcosto,
                (bool) ($filtros['incluir_sin_ccosto'] ?? true),
                (bool) ($filtros['incluir_total_ccosto'] ?? true),
            );
            $columnas = $armado['columnas'];
            foreach ($columnas as &$col) {
                $col['fecha_desde'] = $fechaDesde;
                $col['fecha_hasta'] = $fechaHasta;
            }
            unset($col);
            if ($armado['truncado']) {
                $advertencias[] = sprintf(
                    'Se muestran hasta %d columnas de c.costo (hay %d en el rango). Acotá c.costo desde/hasta.',
                    ReporteDefinibleDimensionSupport::MAX_COLUMNAS_CCOSTO,
                    $armado['total_ccostos']
                );
            }
            if ($columnas === []) {
                $advertencias[] = 'No hay centros de costo con movimiento en el período.';
            }
            $this->cargarDirectosPorColumnasCcosto(
                $reporte, $movs, $columnas, ReporteDefinibleSupport::ORIGEN_REAL,
                $filtroCcosto, $directoActual, $detalleCuentas
            );
        } elseif ($layout === ReporteDefinibleSupport::LAYOUT_COMPARATIVO) {
            $columnas = [
                ['key' => 'actual', 'label' => 'Actual', 'tipo' => 'actual', 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta],
                ['key' => 'plan', 'label' => 'Plan', 'tipo' => 'plan', 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta],
                ['key' => 'var', 'label' => 'Var', 'tipo' => 'var'],
                ['key' => 'var_pct', 'label' => 'Var %', 'tipo' => 'var_pct'],
            ];
            $fdMov = $fechaDesde;
            $fhMov = $fechaHasta;
            if ($baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO) {
                $fdMov = sprintf('%04d-01-01', (int) substr($fechaDesde, 0, 4));
            }
            $movs = $this->cargarMovimientosConsolidados(
                $empresaIds, $fdMov, $fhMov, $codigos, $modoAsientos, $monedaId, $soloOrigen,
                $fechaCierreFx, $reglasEli, $factoresPart
            );
            $fuente = 'asientos';
            $this->cargarDirectosDesdeMovimientos(
                $reporte, $movs, 'actual', ReporteDefinibleSupport::ORIGEN_REAL,
                $filtroCcosto, $directoActual, $detalleCuentas
            );

            $fuentePlan = (string) ($filtros['fuente_plan'] ?? ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO);
            if (! isset(ReporteDefinibleDimensionSupport::fuentesPlan()[$fuentePlan])) {
                $fuentePlan = ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO;
            }

            if ($fuentePlan === ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO) {
                $pdYm = ReporteDefiniblePartidaPlanReader::periodoYmDesdeFecha($fdMov);
                $phYm = ReporteDefiniblePartidaPlanReader::periodoYmDesdeFecha($fhMov);
                if ($baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO) {
                    $pdYm = sprintf('%04d-01', (int) substr($fdMov, 0, 4));
                }
                $planPack = $this->partidaPlanReader->listarMovimientosPlan(
                    $empresaIds,
                    $pdYm,
                    $phYm,
                    $this->codigosDelReporte($reporte, ReporteDefinibleSupport::ORIGEN_REAL) ?: $codigos,
                    isset($filtros['presupuesto_escenario_id']) ? (int) $filtros['presupuesto_escenario_id'] : null,
                    $monedaId,
                );
                foreach ($planPack['advertencias'] as $msg) {
                    $advertencias[] = $msg;
                }
                $this->cargarDirectosDesdeMovimientos(
                    $reporte,
                    $planPack['movimientos'],
                    'plan',
                    ReporteDefinibleSupport::ORIGEN_REAL,
                    $filtroCcosto,
                    $directoPlan,
                    $detalleCuentas
                );
            } else {
                $this->cargarDirectosDesdeMovimientos(
                    $reporte, $movs, 'plan', ReporteDefinibleSupport::ORIGEN_PRESUPUESTO,
                    $filtroCcosto, $directoPlan, $detalleCuentas
                );
            }
        } elseif ($modo === SumasSaldosListadoFiltros::MODO_RANGO) {
            $columnas[] = [
                'key' => 'rango',
                'label' => $this->labelRango($fechaDesde, $fechaHasta),
                'tipo' => 'actual',
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ];
            $fdMov = $baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO
                ? sprintf('%04d-01-01', (int) substr($fechaDesde, 0, 4))
                : $fechaDesde;
            $movs = $this->cargarMovimientosConsolidados(
                $empresaIds, $fdMov, $fechaHasta, $codigos, $modoAsientos, $monedaId, $soloOrigen,
                $fechaCierreFx, $reglasEli, $factoresPart
            );
            $fuente = 'asientos';
            $this->cargarDirectosDesdeMovimientos(
                $reporte, $movs, 'rango', ReporteDefinibleSupport::ORIGEN_REAL,
                $filtroCcosto, $directoActual, $detalleCuentas
            );
            if (! empty($filtros['incluir_presupuesto'])) {
                $this->cargarDirectosDesdeMovimientos(
                    $reporte, $movs, 'rango', ReporteDefinibleSupport::ORIGEN_PRESUPUESTO,
                    $filtroCcosto, $directoActual, $detalleCuentas
                );
            }
        } else {
            foreach ($periodos as $periodo) {
                $key = (string) $periodo;
                [$fd, $fh] = ReporteDefinibleSaldoReader::fechasDesdePeriodo($periodo);
                $columnas[] = [
                    'key' => $key,
                    'label' => $this->labelPeriodo($periodo),
                    'tipo' => 'actual',
                    'periodo' => $periodo,
                    'fecha_desde' => $fd,
                    'fecha_hasta' => $fh,
                ];

                if ($forzarAsientos) {
                    $fdMov = $baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO
                        ? sprintf('%04d-01-01', intdiv($periodo, 100))
                        : $fd;
                    $movs = $this->cargarMovimientosConsolidados(
                        $empresaIds, $fdMov, $fh, $codigos, $modoAsientos, $monedaId, $soloOrigen,
                        $fechaCierreFx, $reglasEli, $factoresPart
                    );
                    $fuente = 'asientos';
                    $this->cargarDirectosDesdeMovimientos(
                        $reporte, $movs, $key, ReporteDefinibleSupport::ORIGEN_REAL,
                        $filtroCcosto, $directoActual, $detalleCuentas
                    );
                    if (! empty($filtros['incluir_presupuesto'])) {
                        $this->cargarDirectosDesdeMovimientos(
                            $reporte, $movs, $key, ReporteDefinibleSupport::ORIGEN_PRESUPUESTO,
                            $filtroCcosto, $directoActual, $detalleCuentas
                        );
                    }
                } else {
                    $mapa = $this->saldosViaSumasSaldos(
                        $empresaIds, $periodo, $codigos, $baseSaldo, $modoAsientos, $monedaId, $advertencias
                    );
                    $mapa['por_codigo'] = $this->eliminacionSupport->filtrarMapaCodigo(
                        $mapa['por_codigo'] ?? [],
                        $reglasEli
                    );
                    $fuente = $mapa['fuente'];
                    $this->cargarDirectosDesdeMapaCodigo(
                        $reporte, $mapa['por_codigo'], $key, ReporteDefinibleSupport::ORIGEN_REAL,
                        $directoActual, $detalleCuentas
                    );
                }
            }
        }

        $byParent = [];
        foreach ($reporte->rubros->sortBy(['orden', 'id']) as $r) {
            $pid = $r->parent_id !== null ? (int) $r->parent_id : 0;
            $byParent[$pid][] = $r;
        }

        $keysRollup = [];
        foreach ($columnas as $col) {
            if (in_array($col['tipo'], ['actual', 'plan', 'ytd', 'anio_ant'], true)) {
                $keysRollup[] = $col['key'];
            }
        }
        $keysRollup = array_values(array_unique($keysRollup));

        $totalesActual = $this->rollup($byParent, $keysRollup, $directoActual, $nivelMax);
        $totalesPlan = $layout === ReporteDefinibleSupport::LAYOUT_COMPARATIVO
            ? $this->rollup($byParent, ['plan'], $directoPlan, $nivelMax)
            : [];

        $this->aplicarFormulas($reporte, $totalesActual, $totalesPlan, $layout);

        $filas = [];
        $this->emitir(
            0,
            0,
            $byParent,
            $columnas,
            $totalesActual,
            $totalesPlan,
            $detalleCuentas,
            $layout,
            $mostrarCuentas,
            $nivelMax,
            $filtros,
            $empresaIds,
            $filas
        );

        if (! empty($filtros['ocultar_ceros'])) {
            [$columnas, $filas] = $this->aplicarOcultarCeros($columnas, $filas);
        }
        $filas = $this->aplicarOcultarSiCeroRubro($filas);

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'advertencias' => array_values(array_unique($advertencias)),
            'fuente' => $fuente,
            'layout' => $layout,
            'layout_id' => null,
            'modo_periodo' => $modo,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'usa_ccosto' => $usaCcostoDef || $filtroCcosto !== null
                || $layout === ReporteDefinibleSupport::LAYOUT_CCOSTO,
        ];
    }

    /**
     * Ejecución con layout persistido (presets / por informe).
     *
     * @param  list<int>  $empresaIds
     * @param  list<string>  $advFechas
     * @param  array{desde: int, hasta: int}|null  $filtroCcosto
     * @return array<string, mixed>
     */
    /**
     * Códigos de cuenta reales (origen real, conjuntos y rangos expandidos) del informe.
     *
     * @return list<int>
     */
    public function codigosRealesDelReporte(ReporteContable $reporte): array
    {
        $reporte->loadMissing(['rubros.cuentas.ccostos', 'rubros.cuentas.cuentacontable']);
        $this->conjuntoSupport->expandirEnReporte($reporte);

        return $this->codigosDelReporte($reporte, ReporteDefinibleSupport::ORIGEN_REAL);
    }

    /**
     * Valor por rubro a partir de una lista de movimientos ya cargada (misma forma que
     * {@see ReporteDefinibleSaldoReader::listarMovimientos()}). Se usa para la paridad
     * contra Anita: mismo árbol, mismas asignaciones y fórmulas, otra fuente de datos.
     *
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>  $movimientos
     * @param  array{desde: int, hasta: int}|null  $filtroCcosto
     * @return array{totales: array<int, float>, detalle: array<int, array<int, float>>}
     */
    public function totalesDesdeMovimientos(
        ReporteContable $reporte,
        array $movimientos,
        int $nivelMax = 0,
        ?array $filtroCcosto = null,
    ): array {
        $reporte->loadMissing(['rubros.cuentas.ccostos', 'rubros.cuentas.cuentacontable']);
        $this->conjuntoSupport->expandirEnReporte($reporte);

        $colKey = 'v';
        $directo = [];
        $detalle = [];
        $this->cargarDirectosDesdeMovimientos(
            $reporte,
            $movimientos,
            $colKey,
            ReporteDefinibleSupport::ORIGEN_REAL,
            $filtroCcosto,
            $directo,
            $detalle
        );

        $byParent = [];
        foreach ($reporte->rubros->sortBy(['orden', 'id']) as $rubro) {
            $pid = $rubro->parent_id !== null ? (int) $rubro->parent_id : 0;
            $byParent[$pid][] = $rubro;
        }

        $totales = $this->rollup($byParent, [$colKey], $directo, $nivelMax);
        $plan = [];
        $this->aplicarFormulas($reporte, $totales, $plan, ReporteDefinibleSupport::LAYOUT_PERIODOS);

        $porRubro = [];
        foreach ($reporte->rubros as $rubro) {
            $rid = (int) $rubro->id;
            if ((string) $rubro->tipo === ReporteDefinibleSupport::RUBRO_TEXTO) {
                continue;
            }
            $valor = (float) ($totales[$rid][$colKey] ?? 0.0);
            $saldos = $this->aplicarLadoPresentacion(
                [$colKey => $valor],
                isset($rubro->lado_presentacion) ? (string) $rubro->lado_presentacion : null
            );
            $porRubro[$rid] = round((float) ($saldos[$colKey] ?? 0.0), 2);
        }

        $detallePorRubro = [];
        foreach ($detalle as $rid => $porCol) {
            foreach ($porCol[$colKey] ?? [] as $codigo => $valor) {
                $detallePorRubro[(int) $rid][(int) $codigo] = round((float) $valor, 2);
            }
        }

        return ['totales' => $porRubro, 'detalle' => $detallePorRubro];
    }

    /**
     * Códigos reales asignados a un solo rubro (para leer únicamente lo que se va a drillear).
     *
     * @return list<int>
     */
    public function codigosRealesDeRubro(ReporteContable $reporte, int $rubroId): array
    {
        $reporte->loadMissing(['rubros.cuentas.ccostos']);
        $this->conjuntoSupport->expandirEnReporte($reporte);

        $codigos = [];
        foreach ($reporte->rubros as $rubro) {
            if ((int) $rubro->id !== $rubroId) {
                continue;
            }
            foreach ($rubro->cuentas as $cta) {
                if ((string) $cta->origen !== ReporteDefinibleSupport::ORIGEN_REAL) {
                    continue;
                }
                foreach ($this->cuentaRangoSupport->expandirAsignacion($cta) as $codigo) {
                    $codigos[(int) $codigo] = true;
                }
            }
        }

        return array_keys($codigos);
    }

    /**
     * Detalle cuenta por cuenta de un rubro, con el mismo signo con que la celda salió impresa.
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{cuentas: array<int, float>, total: float}
     */
    public function detalleCuentasDeRubro(
        ReporteContable $reporte,
        int $rubroId,
        array $movimientos,
        ?array $filtroCcosto = null,
    ): array {
        $reporte->loadMissing(['rubros.cuentas.ccostos', 'rubros.cuentas.cuentacontable']);
        $this->conjuntoSupport->expandirEnReporte($reporte);

        $colKey = 'v';
        $directo = [];
        $detalle = [];
        $this->cargarDirectosDesdeMovimientos(
            $reporte,
            $movimientos,
            $colKey,
            ReporteDefinibleSupport::ORIGEN_REAL,
            $filtroCcosto,
            $directo,
            $detalle
        );

        $rubro = $reporte->rubros->firstWhere('id', $rubroId);
        $lado = $rubro !== null && isset($rubro->lado_presentacion) ? (string) $rubro->lado_presentacion : null;

        $cuentas = [];
        foreach ($detalle[$rubroId][$colKey] ?? [] as $codigo => $valor) {
            $ajustado = $this->aplicarLadoPresentacion([$colKey => (float) $valor], $lado);
            $cuentas[(int) $codigo] = round((float) ($ajustado[$colKey] ?? 0.0), 2);
        }

        return ['cuentas' => $cuentas, 'total' => round(array_sum($cuentas), 2)];
    }

    private function ejecutarLayoutDiseñado(
        ReporteContable $reporte,
        array $filtros,
        \App\Models\Contable\ReporteContableLayout $layoutModel,
        array $empresaIds,
        string $fechaDesde,
        string $fechaHasta,
        string $modo,
        string $baseSaldo,
        bool $mostrarCuentas,
        int $nivelMax,
        string $modoAsientos,
        int $monedaId,
        bool $soloOrigen,
        ?array $filtroCcosto,
        array $advFechas,
    ): array {
        $advertencias = $advFechas;
        $advertencias[] = sprintf('Layout «%s» (%s).', $layoutModel->nombre, $layoutModel->codigo);
        $usaCcostoDef = $this->definicionUsaCcosto($reporte);
        if ($usaCcostoDef) {
            $advertencias[] = 'Filtros de centro de costo de la definición aplicados (lectura por asientos).';
        }
        if ($filtroCcosto !== null) {
            $advertencias[] = sprintf('Filtro runtime c.costo %d–%d.', $filtroCcosto['desde'], $filtroCcosto['hasta']);
        }

        $columnas = $this->layoutResolver->armarColumnas($layoutModel, $fechaDesde, $fechaHasta);
        $codigos = $this->codigosDelReporte($reporte);
        $ctxCons = $this->contextoConsolidacion($reporte, $filtros, $empresaIds, $fechaHasta, $advertencias);
        $reglasEli = $ctxCons['reglas'];
        $factoresPart = $ctxCons['factores'];
        $fechaCierreFx = $ctxCons['fecha_cierre'];
        $directoActual = [];
        $directoPlan = [];
        $detalleCuentas = [];
        $fuente = 'asientos';

        $tiposDatos = ReporteDefinibleLayoutResolver::tiposDatos();

        foreach ($columnas as $col) {
            $tipo = (string) $col['tipo'];
            if (! in_array($tipo, $tiposDatos, true)) {
                continue;
            }
            $fd = (string) $col['fecha_desde'];
            $fh = (string) $col['fecha_hasta'];
            $fdMov = $fd;
            if ($baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO
                && $tipo !== ReporteDefinibleLayoutResolver::TIPO_YTD) {
                $fdMov = sprintf('%04d-01-01', (int) substr($fd, 0, 4));
            }
            $key = (string) $col['key'];
            $metaCol = is_array($col['meta'] ?? null) ? $col['meta'] : [];

            if ($tipo === ReporteDefinibleLayoutResolver::TIPO_PLAN) {
                $fuentePlan = (string) ($filtros['fuente_plan'] ?? ReporteDefinibleDimensionSupport::FUENTE_PLAN_PARTIDAGASTO);
                if ($fuentePlan === ReporteDefinibleDimensionSupport::FUENTE_PLAN_ASIGNACION_P) {
                    $movs = $this->cargarMovimientosConsolidados(
                        $empresaIds, $fdMov, $fh, $codigos, $modoAsientos, $monedaId, $soloOrigen,
                        $fechaCierreFx, $reglasEli, $factoresPart
                    );
                    $this->cargarDirectosDesdeMovimientos(
                        $reporte, $movs, $key, ReporteDefinibleSupport::ORIGEN_PRESUPUESTO,
                        $filtroCcosto, $directoActual, $detalleCuentas
                    );
                } else {
                    $pdYm = ReporteDefiniblePartidaPlanReader::periodoYmDesdeFecha($fdMov);
                    $phYm = ReporteDefiniblePartidaPlanReader::periodoYmDesdeFecha($fh);
                    $escenarioId = null;
                    if (! empty($metaCol['presupuesto_escenario_id'])) {
                        $escenarioId = (int) $metaCol['presupuesto_escenario_id'];
                    } elseif (isset($filtros['presupuesto_escenario_id'])) {
                        $escenarioId = (int) $filtros['presupuesto_escenario_id'];
                    }
                    $planPack = $this->partidaPlanReader->listarMovimientosPlan(
                        $empresaIds,
                        $pdYm,
                        $phYm,
                        $this->codigosDelReporte($reporte, ReporteDefinibleSupport::ORIGEN_REAL) ?: $codigos,
                        $escenarioId && $escenarioId > 0 ? $escenarioId : null,
                        $monedaId,
                    );
                    foreach ($planPack['advertencias'] as $msg) {
                        $advertencias[] = $msg;
                    }
                    // Plan de partidas: no se eliminan (presupuesto operativo)
                    $this->cargarDirectosDesdeMovimientos(
                        $reporte, $planPack['movimientos'], $key, ReporteDefinibleSupport::ORIGEN_REAL,
                        $filtroCcosto, $directoActual, $detalleCuentas
                    );
                }
            } else {
                // actual, ytd, anio_ant, periodo_offset, valuacion
                $val = $this->valuacionColumna($metaCol, $tipo, $modoAsientos, $monedaId, $soloOrigen);
                if ($val['aviso'] !== null) {
                    $advertencias[] = sprintf('Columna «%s»: %s.', (string) $col['label'], $val['aviso']);
                }
                $movs = $this->cargarMovimientosConsolidados(
                        $empresaIds, $fdMov, $fh, $codigos, $val['modo_asientos'], $val['moneda_id'], $val['solo_origen'],
                        $fechaCierreFx, $reglasEli, $factoresPart
                    );
                $sinCotizacion = $this->saldoReader->movimientosSinCotizacion();
                if ($sinCotizacion > 0) {
                    $advertencias[] = sprintf(
                        'Columna «%s»: %d movimiento(s) quedaron afuera porque la moneda %d no tiene ninguna cotización cargada.',
                        (string) $col['label'],
                        $sinCotizacion,
                        $val['moneda_id']
                    );
                }
                $conVigente = $this->saldoReader->movimientosCotizacionVigente();
                if ($conVigente > 0) {
                    $advertencias[] = sprintf(
                        'Columna «%s»: %d movimiento(s) se convirtieron con la cotización vigente de un día anterior '
                        .'(la más antigua usada es del %s) porque la tabla no tiene cotización propia de su fecha.',
                        (string) $col['label'],
                        $conVigente,
                        $this->saldoReader->fechaCotizacionVigenteMasVieja() !== null
                            ? date('d/m/Y', strtotime((string) $this->saldoReader->fechaCotizacionVigenteMasVieja()))
                            : 's/d'
                    );
                }
                $this->cargarDirectosDesdeMovimientos(
                    $reporte, $movs, $key, ReporteDefinibleSupport::ORIGEN_REAL,
                    $filtroCcosto, $directoActual, $detalleCuentas
                );
            }
        }

        $byParent = [];
        foreach ($reporte->rubros->sortBy(['orden', 'id']) as $r) {
            $pid = $r->parent_id !== null ? (int) $r->parent_id : 0;
            $byParent[$pid][] = $r;
        }

        $keysRollup = [];
        foreach ($columnas as $col) {
            if (in_array($col['tipo'], $tiposDatos, true)) {
                $keysRollup[] = $col['key'];
            }
        }
        $keysRollup = array_values(array_unique($keysRollup));
        $totalesActual = $this->rollup($byParent, $keysRollup, $directoActual, $nivelMax);
        $totalesPlan = [];
        $this->aplicarFormulas($reporte, $totalesActual, $totalesPlan, 'diseñado');
        $this->aplicarColumnasDerivadas($columnas, $totalesActual);

        $filas = [];
        $this->emitir(
            0, 0, $byParent, $columnas, $totalesActual, $totalesPlan, $detalleCuentas,
            'diseñado', $mostrarCuentas, $nivelMax, $filtros, $empresaIds, $filas
        );

        if (! empty($filtros['ocultar_ceros'])) {
            [$columnas, $filas] = $this->aplicarOcultarCeros($columnas, $filas);
        }
        $filas = $this->aplicarOcultarSiCeroRubro($filas);

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'advertencias' => array_values(array_unique($advertencias)),
            'fuente' => $fuente,
            'layout' => 'diseñado',
            'layout_id' => (int) $layoutModel->id,
            'modo_periodo' => $modo,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'usa_ccosto' => $usaCcostoDef || $filtroCcosto !== null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function aplicarOcultarSiCeroRubro(array $filas): array
    {
        $ocultarIds = [];
        foreach ($filas as $fila) {
            if (($fila['kind'] ?? '') !== 'rubro') {
                continue;
            }
            if (empty($fila['ocultar_si_cero'])) {
                continue;
            }
            if (($fila['saldos'] ?? null) === null) {
                continue;
            }
            $hay = false;
            foreach ($fila['saldos'] as $v) {
                if ($v !== null && abs((float) $v) >= 0.005) {
                    $hay = true;
                    break;
                }
            }
            if (! $hay) {
                $ocultarIds[(int) $fila['rubro_id']] = true;
            }
        }
        if ($ocultarIds === []) {
            return $filas;
        }

        return array_values(array_filter($filas, function (array $fila) use ($ocultarIds) {
            $rid = (int) ($fila['rubro_id'] ?? 0);

            return ! isset($ocultarIds[$rid]);
        }));
    }

    /**
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string}>  $movimientos
     * @param  list<array<string, mixed>>  $columnas
     * @param  array{desde: int, hasta: int}|null  $filtroRuntime
     * @param  array<int, array<string, float>>  $directo
     * @param  array<int, array<string, array<int, float>>>  $detalle
     */
    private function cargarDirectosPorColumnasCcosto(
        ReporteContable $reporte,
        array $movimientos,
        array $columnas,
        string $origen,
        ?array $filtroRuntime,
        array &$directo,
        array &$detalle,
    ): void {
        foreach ($reporte->rubros as $rubro) {
            $rid = (int) $rubro->id;
            foreach ($rubro->cuentas as $cta) {
                if ((string) $cta->origen !== $origen) {
                    continue;
                }
                $codigos = $this->cuentaRangoSupport->expandirAsignacion($cta);
                $rangos = $this->rangosCcosto($cta);
                $carga = (string) $cta->carga_ccosto;
                $signo = (int) $cta->signo;
                foreach ($codigos as $codigo) {
                    foreach ($columnas as $col) {
                        $key = (string) $col['key'];
                        $ccCodigo = (int) ($col['codigo'] ?? -1);
                        if ($ccCodigo === -1) {
                            $val = $this->saldoReader->sumarAsignacion(
                                $movimientos, $codigo, $carga, $rangos, $filtroRuntime, $signo
                            );
                        } else {
                            $val = $this->saldoReader->sumarAsignacionEnCcosto(
                                $movimientos, $codigo, $carga, $rangos, $ccCodigo, $signo
                            );
                        }
                        $directo[$rid][$key] = ($directo[$rid][$key] ?? 0.0) + $val;
                        $detalle[$rid][$key][$codigo] = ($detalle[$rid][$key][$codigo] ?? 0.0) + $val;
                    }
                }
            }
        }
    }

    /**
     * Quita columnas y filas (no texto) con todos los importes en cero.
     *
     * @param  list<array<string, mixed>>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function aplicarOcultarCeros(array $columnas, array $filas): array
    {
        $keysKeep = [];
        foreach ($columnas as $col) {
            $k = (string) ($col['key'] ?? '');
            if ($k === '') {
                continue;
            }
            $tiene = false;
            foreach ($filas as $fila) {
                if (($fila['saldos'] ?? null) === null) {
                    continue;
                }
                if (abs((float) ($fila['saldos'][$k] ?? 0)) >= 0.005) {
                    $tiene = true;
                    break;
                }
            }
            if ($tiene || in_array($col['tipo'] ?? '', [
                ReporteDefinibleLayoutResolver::TIPO_VAR,
                ReporteDefinibleLayoutResolver::TIPO_VAR_PCT,
                ReporteDefinibleLayoutResolver::TIPO_PCT_SOBRE,
                ReporteDefinibleLayoutResolver::TIPO_FORMULA_COL,
            ], true)) {
                $keysKeep[$k] = true;
            }
        }
        $columnasFiltradas = array_values(array_filter(
            $columnas,
            fn (array $c) => isset($keysKeep[(string) ($c['key'] ?? '')])
        ));
        if ($columnasFiltradas !== []) {
            $columnas = $columnasFiltradas;
        }

        $filasOut = [];
        foreach ($filas as $fila) {
            if (($fila['saldos'] ?? null) === null) {
                $filasOut[] = $fila;
                continue;
            }
            $hay = false;
            foreach ($columnas as $col) {
                $k = (string) ($col['key'] ?? '');
                $v = $fila['saldos'][$k] ?? null;
                if ($v !== null && abs((float) $v) >= 0.005) {
                    $hay = true;
                    break;
                }
            }
            if ($hay) {
                $filasOut[] = $fila;
            }
        }

        return [$columnas, $filasOut];
    }

    /**
     * @param  list<array{codigo: int, ccosto: int, monto: float, fecha: string}>  $movimientos
     * @param  array{desde: int, hasta: int}|null  $filtroRuntime
     * @param  array<int, array<string, float>>  $directo
     * @param  array<int, array<string, array<int, float>>>  $detalle
     */
    private function cargarDirectosDesdeMovimientos(
        ReporteContable $reporte,
        array $movimientos,
        string $colKey,
        string $origen,
        ?array $filtroRuntime,
        array &$directo,
        array &$detalle,
    ): void {
        foreach ($reporte->rubros as $rubro) {
            $rid = (int) $rubro->id;
            foreach ($rubro->cuentas as $cta) {
                if ((string) $cta->origen !== $origen) {
                    continue;
                }
                $codigos = $this->cuentaRangoSupport->expandirAsignacion($cta);
                $rangos = $this->rangosCcosto($cta);
                $carga = (string) $cta->carga_ccosto;
                $signo = (int) $cta->signo;
                foreach ($codigos as $codigo) {
                    $val = $this->saldoReader->sumarAsignacion(
                        $movimientos,
                        $codigo,
                        $carga,
                        $rangos,
                        $filtroRuntime,
                        $signo,
                    );
                    $directo[$rid][$colKey] = ($directo[$rid][$colKey] ?? 0.0) + $val;
                    $detalle[$rid][$colKey][$codigo] = ($detalle[$rid][$colKey][$codigo] ?? 0.0) + $val;
                }
            }
        }
    }

    /**
     * @param  array<int, float>  $porCodigo
     * @param  array<int, array<string, float>>  $directo
     * @param  array<int, array<string, array<int, float>>>  $detalle
     */
    private function cargarDirectosDesdeMapaCodigo(
        ReporteContable $reporte,
        array $porCodigo,
        string $colKey,
        string $origen,
        array &$directo,
        array &$detalle,
    ): void {
        foreach ($reporte->rubros as $rubro) {
            $rid = (int) $rubro->id;
            foreach ($rubro->cuentas as $cta) {
                if ((string) $cta->origen !== $origen) {
                    continue;
                }
                $codigos = $this->cuentaRangoSupport->expandirAsignacion($cta);
                $signo = (int) $cta->signo >= 0 ? 1 : -1;
                foreach ($codigos as $codigo) {
                    $val = ((float) ($porCodigo[$codigo] ?? 0)) * $signo;
                    $directo[$rid][$colKey] = ($directo[$rid][$colKey] ?? 0.0) + $val;
                    $detalle[$rid][$colKey][$codigo] = ($detalle[$rid][$colKey][$codigo] ?? 0.0) + $val;
                }
            }
        }
    }

    /**
     * @param  array<int, list<ReporteContableRubro>>  $byParent
     * @param  list<string>  $keys
     * @param  array<int, array<string, float>>  $directo
     * @return array<int, array<string, float>>
     */
    private function rollup(array $byParent, array $keys, array $directo, int $nivelMax): array
    {
        $totales = [];
        $walk = function (int $parentId) use (&$walk, &$totales, $byParent, $keys, $directo, $nivelMax): array {
            $agg = [];
            foreach ($keys as $k) {
                $agg[$k] = 0.0;
            }
            foreach ($byParent[$parentId] ?? [] as $rubro) {
                if ($nivelMax > 0 && (int) $rubro->nivel > $nivelMax) {
                    continue;
                }
                $rid = (int) $rubro->id;
                $saldos = [];
                foreach ($keys as $k) {
                    $saldos[$k] = (float) ($directo[$rid][$k] ?? 0.0);
                }
                $hijos = $walk($rid);
                foreach ($keys as $k) {
                    $saldos[$k] += $hijos[$k] ?? 0.0;
                }
                $totales[$rid] = $saldos;
                if ((string) $rubro->tipo !== ReporteDefinibleSupport::RUBRO_TEXTO) {
                    foreach ($keys as $k) {
                        $agg[$k] += $saldos[$k];
                    }
                }
            }

            return $agg;
        };
        $walk(0);

        return $totales;
    }

    /**
     * @param  array<int, array<string, float>>  $totalesActual
     * @param  array<int, array<string, float>>  $totalesPlan
     */
    private function aplicarFormulas(
        ReporteContable $reporte,
        array &$totalesActual,
        array &$totalesPlan,
        string $layout,
    ): void {
        $porCodigoLineaActual = [];
        $porCodigoLineaPlan = [];
        foreach ($reporte->rubros as $rubro) {
            $code = strtoupper(trim((string) ($rubro->codigo_linea ?? '')));
            if ($code === '') {
                continue;
            }
            $vals = $totalesActual[(int) $rubro->id] ?? [];
            $porCodigoLineaActual[$code] = (float) array_sum($vals);
            if ($layout === ReporteDefinibleSupport::LAYOUT_COMPARATIVO) {
                $porCodigoLineaPlan[$code] = (float) (($totalesPlan[(int) $rubro->id]['plan'] ?? 0));
            }
        }

        foreach ($reporte->rubros as $rubro) {
            if ((string) $rubro->tipo !== ReporteDefinibleSupport::RUBRO_FORMULA) {
                continue;
            }
            $rid = (int) $rubro->id;
            if ($layout === ReporteDefinibleSupport::LAYOUT_COMPARATIVO) {
                $a = ReporteDefinibleFormulaSupport::evaluar($rubro->formula, $porCodigoLineaActual);
                $p = ReporteDefinibleFormulaSupport::evaluar($rubro->formula, $porCodigoLineaPlan);
                $totalesActual[$rid] = ['actual' => $a ?? 0.0];
                $totalesPlan[$rid] = ['plan' => $p ?? 0.0];
            } else {
                // Misma fórmula sobre cada columna de totalesActual
                $keys = array_keys($totalesActual[$rid] ?? ['rango' => 0.0]);
                if ($keys === []) {
                    // tomar keys de un hermano
                    foreach ($totalesActual as $vals) {
                        $keys = array_keys($vals);
                        break;
                    }
                }
                $nuevo = [];
                foreach ($keys as $k) {
                    $mapa = [];
                    foreach ($reporte->rubros as $r2) {
                        $c2 = strtoupper(trim((string) ($r2->codigo_linea ?? '')));
                        if ($c2 !== '') {
                            $mapa[$c2] = (float) (($totalesActual[(int) $r2->id][$k] ?? 0));
                        }
                    }
                    $nuevo[$k] = ReporteDefinibleFormulaSupport::evaluar($rubro->formula, $mapa) ?? 0.0;
                }
                $totalesActual[$rid] = $nuevo;
            }
        }
    }

    /**
     * @param  array<int, list<ReporteContableRubro>>  $byParent
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<int, array<string, float>>  $totalesActual
     * @param  array<int, array<string, float>>  $totalesPlan
     * @param  array<int, array<string, array<int, float>>>  $detalleCuentas
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $empresaIds
     * @param  list<array<string, mixed>>  $filas
     */
    private function emitir(
        int $parentId,
        int $depth,
        array $byParent,
        array $columnas,
        array $totalesActual,
        array $totalesPlan,
        array $detalleCuentas,
        string $layout,
        bool $mostrarCuentas,
        int $nivelMax,
        array $filtros,
        array $empresaIds,
        array &$filas,
    ): void {
        foreach ($byParent[$parentId] ?? [] as $rubro) {
            if ($nivelMax > 0 && (int) $rubro->nivel > $nivelMax) {
                continue;
            }
            $rid = (int) $rubro->id;
            $esTexto = (string) $rubro->tipo === ReporteDefinibleSupport::RUBRO_TEXTO;
            $saldos = $this->armarSaldosFila(
                $columnas,
                $totalesActual[$rid] ?? [],
                $totalesPlan[$rid] ?? [],
                $layout,
                $esTexto
            );
            if (! $esTexto) {
                $saldos = $this->aplicarLadoPresentacion(
                    $saldos,
                    isset($rubro->lado_presentacion) ? (string) $rubro->lado_presentacion : null
                );
            }

            $codigosRubro = [];
            foreach ($rubro->cuentas as $cta) {
                if ($cta->origen === ReporteDefinibleSupport::ORIGEN_REAL) {
                    foreach ($this->cuentaRangoSupport->expandirAsignacion($cta) as $codigoExp) {
                        $codigosRubro[] = $codigoExp;
                    }
                }
            }
            $codigosRubro = array_values(array_unique($codigosRubro));

            $filas[] = [
                'kind' => 'rubro',
                'rubro_id' => $rid,
                'depth' => $depth,
                'codigo' => (string) ($rubro->codigo_linea ?? ''),
                'nombre' => (string) $rubro->nombre,
                'tipo' => (string) $rubro->tipo,
                'tipo_label' => ReporteDefinibleSupport::etiquetaTipoRubro((string) $rubro->tipo),
                'negrita' => (bool) $rubro->estilo_negrita || (int) $rubro->nivel <= 1,
                'subrayado' => (bool) $rubro->estilo_subrayado,
                'saldos' => $esTexto ? null : $saldos,
                'nivel' => (int) $rubro->nivel,
                'ocultar_si_cero' => (bool) ($rubro->ocultar_si_cero ?? false),
                'drill_url' => $this->urlDrillMayor($codigosRubro, $filtros, $empresaIds, $columnas),
            ];

            if ($mostrarCuentas && ! $esTexto) {
                $nombres = [];
                foreach ($rubro->cuentas as $cta) {
                    if ($cta->origen !== ReporteDefinibleSupport::ORIGEN_REAL) {
                        continue;
                    }
                    foreach ($this->cuentaRangoSupport->expandirAsignacion($cta) as $codigo) {
                        $nombres[$codigo] = $cta->cuentacontable->nombre
                            ?? MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo);
                    }
                }
                foreach ($nombres as $codigo => $nombreCta) {
                    $valoresCta = [];
                    foreach ($columnas as $col) {
                        $tipo = (string) ($col['tipo'] ?? '');
                        if (in_array($tipo, ReporteDefinibleLayoutResolver::tiposDatos(), true)) {
                            $valoresCta[(string) $col['key']] = (float) ($detalleCuentas[$rid][$col['key']][$codigo] ?? 0);
                        }
                    }
                    $saldosCta = $this->resolverSaldosDerivados($columnas, $valoresCta);
                    $saldosCta = $this->aplicarLadoPresentacion(
                        $saldosCta,
                        isset($rubro->lado_presentacion) ? (string) $rubro->lado_presentacion : null
                    );
                    $filas[] = [
                        'kind' => 'cuenta',
                        'rubro_id' => $rid,
                        'depth' => $depth + 1,
                        'codigo' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                        'codigo_num' => $codigo,
                        'nombre' => $nombreCta,
                        'tipo' => 'cuenta',
                        'negrita' => false,
                        'subrayado' => false,
                        'saldos' => $saldosCta,
                        'drill_url' => $this->urlDrillMayor([$codigo], $filtros, $empresaIds, $columnas),
                    ];
                }
            }

            $this->emitir(
                $rid,
                $depth + 1,
                $byParent,
                $columnas,
                $totalesActual,
                $totalesPlan,
                $detalleCuentas,
                $layout,
                $mostrarCuentas,
                $nivelMax,
                $filtros,
                $empresaIds,
                $filas
            );
        }
    }

    /**
     * Completa en totales keys derivadas (var, %, fórmula) tras rollup/fórmulas de rubro.
     *
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<int, array<string, float>>  $totales
     */
    private function aplicarColumnasDerivadas(array $columnas, array &$totales): void
    {
        $hayDerivadas = false;
        foreach ($columnas as $col) {
            if (in_array((string) ($col['tipo'] ?? ''), [
                ReporteDefinibleLayoutResolver::TIPO_VAR,
                ReporteDefinibleLayoutResolver::TIPO_VAR_PCT,
                ReporteDefinibleLayoutResolver::TIPO_PCT_SOBRE,
                ReporteDefinibleLayoutResolver::TIPO_FORMULA_COL,
            ], true)) {
                $hayDerivadas = true;
                break;
            }
        }
        if (! $hayDerivadas) {
            return;
        }
        foreach ($totales as $rid => $vals) {
            $resueltos = $this->resolverSaldosDerivados($columnas, $vals);
            foreach ($resueltos as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $totales[$rid][$k] = (float) $v;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<string, float>  $valoresPorKey
     * @return array<string, float|null>
     */
    private function resolverSaldosDerivados(array $columnas, array $valoresPorKey): array
    {
        $out = [];
        foreach ($columnas as $col) {
            $k = (string) $col['key'];
            $tipo = (string) ($col['tipo'] ?? '');
            $meta = is_array($col['meta'] ?? null) ? $col['meta'] : [];

            if (in_array($tipo, ReporteDefinibleLayoutResolver::tiposDatos(), true)) {
                $out[$k] = (float) ($valoresPorKey[$k] ?? 0);
                continue;
            }

            if ($tipo === ReporteDefinibleLayoutResolver::TIPO_VAR
                || $tipo === ReporteDefinibleLayoutResolver::TIPO_VAR_PCT) {
                $baseKey = (string) ($meta['base_key'] ?? 'actual');
                $refKey = (string) ($meta['ref_key'] ?? 'plan');
                $base = (float) ($valoresPorKey[$baseKey] ?? $out[$baseKey] ?? 0);
                $ref = (float) ($valoresPorKey[$refKey] ?? $out[$refKey] ?? 0);
                if ($tipo === ReporteDefinibleLayoutResolver::TIPO_VAR) {
                    $out[$k] = $base - $ref;
                } else {
                    $out[$k] = abs($ref) < 0.005 ? null : round((($base - $ref) / $ref) * 100, 2);
                }
                continue;
            }

            if ($tipo === ReporteDefinibleLayoutResolver::TIPO_PCT_SOBRE) {
                $numKey = (string) ($meta['numerador_key'] ?? '');
                $denKey = (string) ($meta['denominador_key'] ?? '');
                $num = (float) ($valoresPorKey[$numKey] ?? $out[$numKey] ?? 0);
                $den = (float) ($valoresPorKey[$denKey] ?? $out[$denKey] ?? 0);
                $out[$k] = abs($den) < 0.005 ? null : round(($num / $den) * 100, 2);
                continue;
            }

            if ($tipo === ReporteDefinibleLayoutResolver::TIPO_FORMULA_COL) {
                $expr = (string) ($meta['expr'] ?? '');
                $vals = $valoresPorKey;
                foreach ($out as $ok => $ov) {
                    if ($ov !== null && ! array_key_exists($ok, $vals)) {
                        $vals[$ok] = (float) $ov;
                    }
                }
                $out[$k] = $this->evalFormulaColumna($expr, $vals);
                continue;
            }

            $out[$k] = (float) ($valoresPorKey[$k] ?? 0);
        }

        return $out;
    }

    /**
     * Evalúa expresión simple (+ - * / y paréntesis) sobre keys de columna.
     *
     * @param  array<string, float>  $valoresPorKey
     */
    private function evalFormulaColumna(?string $expr, array $valoresPorKey): ?float
    {
        $expr = trim((string) $expr);
        if ($expr === '') {
            return null;
        }
        if (! preg_match('/^[a-zA-Z0-9_+\-*\/().\s]+$/', $expr)) {
            return null;
        }

        $keys = array_keys($valoresPorKey);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $replaced = $expr;
        foreach ($keys as $key) {
            if ($key === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }
            $val = (float) ($valoresPorKey[$key] ?? 0);
            $replaced = preg_replace(
                '/\b'.preg_quote($key, '/').'\b/',
                '('.var_export($val, true).')',
                $replaced
            ) ?? $replaced;
        }

        if (preg_match('/[a-zA-Z_]/', $replaced)) {
            return null;
        }

        try {
            // phpcs:ignore
            $result = @eval('return (float) ('.$replaced.');');
        } catch (\Throwable) {
            return null;
        }

        if (! is_numeric($result) || ! is_finite((float) $result)) {
            return null;
        }

        return round((float) $result, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<string, float>  $actual
     * @param  array<string, float>  $plan
     * @return array<string, float|null>
     */
    private function armarSaldosFila(array $columnas, array $actual, array $plan, string $layout, bool $esTexto): array
    {
        if ($esTexto) {
            return [];
        }

        if ($layout === 'diseñado') {
            $vals = $actual;
            foreach ($plan as $pk => $pv) {
                if (! array_key_exists($pk, $vals)) {
                    $vals[$pk] = (float) $pv;
                }
            }

            return $this->resolverSaldosDerivados($columnas, $vals);
        }

        $out = [];
        foreach ($columnas as $col) {
            $k = $col['key'];
            $tipo = $col['tipo'];
            if (in_array($tipo, ['actual', 'ytd', 'anio_ant', 'periodo_offset'], true)) {
                $out[$k] = (float) ($actual[$k] ?? 0);
            } elseif ($tipo === 'plan') {
                $out[$k] = (float) ($actual[$k] ?? $plan[$k] ?? $plan['plan'] ?? 0);
            } elseif ($tipo === 'var') {
                $a = (float) ($actual['actual'] ?? 0);
                $p = (float) ($actual['plan'] ?? $plan['plan'] ?? 0);
                $out[$k] = $a - $p;
            } elseif ($tipo === 'var_pct') {
                $a = (float) ($actual['actual'] ?? 0);
                $p = (float) ($actual['plan'] ?? $plan['plan'] ?? 0);
                $out[$k] = abs($p) < 0.005 ? null : round((($a - $p) / $p) * 100, 2);
            } else {
                $out[$k] = (float) ($actual[$k] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, float|null>  $saldos
     * @return array<string, float|null>
     */
    private function aplicarLadoPresentacion(array $saldos, ?string $lado): array
    {
        $lado = $lado !== null ? strtoupper(trim($lado)) : '';
        if ($lado !== 'H') {
            return $saldos;
        }
        foreach ($saldos as $k => $v) {
            if ($v !== null) {
                $saldos[$k] = round((-1) * (float) $v, 2);
            }
        }

        return $saldos;
    }

    /**
     * Valuación efectiva de una columna de datos.
     *
     * Permite el mismo informe en varias valuaciones a la vez: histórico (sin los asientos
     * de ajuste por inflación), ajustado (incluyéndolos) y convertido a otra moneda. Lo que
     * venga explícito en la meta de la columna manda sobre el preset.
     *
     * @param  array<string, mixed>  $meta
     * @return array{modo_asientos: string, moneda_id: int, solo_origen: bool, aviso: string|null}
     */
    private function valuacionColumna(
        array $meta,
        string $tipo,
        string $modoAsientos,
        int $monedaId,
        bool $soloOrigen,
    ): array {
        $aviso = null;
        $valuacion = strtolower(trim((string) ($meta['valuacion'] ?? '')));

        if ($valuacion === '' && $tipo === ReporteDefinibleLayoutResolver::TIPO_VALUACION) {
            $valuacion = ReporteDefinibleLayoutResolver::VALUACION_HISTORICO;
        }

        switch ($valuacion) {
            case ReporteDefinibleLayoutResolver::VALUACION_HISTORICO:
                $modoAsientos = $modoAsientos === 'todos' ? 'sin_inflacion' : $modoAsientos;
                if (! in_array($modoAsientos, ['sin_inflacion', 'sin_cierre_ni_inflacion'], true)) {
                    $modoAsientos = 'sin_cierre_ni_inflacion';
                }
                $aviso = 'valuación histórica (excluye asientos de ajuste por inflación)';
                break;

            case ReporteDefinibleLayoutResolver::VALUACION_AJUSTADO:
                $modoAsientos = $modoAsientos === 'todos' ? 'todos' : 'sin_cierre';
                $aviso = 'valuación ajustada por inflación (incluye los asientos de ajuste)';
                break;

            case ReporteDefinibleLayoutResolver::VALUACION_MONEDA:
                $monedaMeta = (int) ($meta['moneda_id'] ?? 0);
                if ($monedaMeta > 0) {
                    $monedaId = $monedaMeta;
                }
                $soloOrigen = false;
                $aviso = 'convertida a la moneda '.$monedaId.' con la cotización del movimiento';
                break;
        }

        if (array_key_exists('modo_inclusion_asientos', $meta) && (string) $meta['modo_inclusion_asientos'] !== '') {
            $modo = (string) $meta['modo_inclusion_asientos'];
            if (in_array($modo, ['todos', 'sin_cierre', 'sin_inflacion', 'sin_cierre_ni_inflacion'], true)) {
                $modoAsientos = $modo;
            }
        }
        if (! empty($meta['moneda_id'])) {
            $monedaId = (int) $meta['moneda_id'];
        }
        if (array_key_exists('solo_moneda_origen', $meta)) {
            $soloOrigen = (bool) $meta['solo_moneda_origen'];
        }

        return [
            'modo_asientos' => $modoAsientos,
            'moneda_id' => $monedaId,
            'solo_origen' => $soloOrigen,
            'aviso' => $aviso,
        ];
    }

    /**
     * @param  list<int>  $codigos
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $empresaIds
     * @param  list<array<string, mixed>>  $columnas
     */
    private function urlDrillMayor(array $codigos, array $filtros, array $empresaIds, array $columnas): ?string
    {
        $codigos = array_values(array_filter(array_map('intval', $codigos)));
        if ($codigos === []) {
            return null;
        }
        sort($codigos);
        $desde = $codigos[0];
        $hasta = $codigos[count($codigos) - 1];

        $fd = (string) ($filtros['fecha_desde'] ?? '');
        $fh = (string) ($filtros['fecha_hasta'] ?? '');
        if ($fd === '' || $fh === '') {
            foreach ($columnas as $col) {
                if (! empty($col['fecha_desde'])) {
                    $fd = (string) $col['fecha_desde'];
                    $fh = (string) ($col['fecha_hasta'] ?? $col['fecha_desde']);
                    break;
                }
            }
        }
        if ($fd === '' || $fh === '') {
            return null;
        }

        $params = [
            'consultar' => 1,
            'modo_periodo' => 'rango',
            'fecha_desde' => $fd,
            'fecha_hasta' => $fh,
            'cuenta_desde' => $desde,
            'cuenta_hasta' => $hasta,
            'modo_inclusion_asientos' => $filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion',
            'moneda_id' => $filtros['moneda_id'] ?? 1,
        ];
        foreach ($empresaIds as $id) {
            $params['empresa_ids'][] = $id;
        }
        if (empty($filtros['consolidar_empresas'])) {
            $params['consolidar_empresas'] = 0;
        }

        try {
            return route('mayor_plano_cuenta', $params);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{desde: int, hasta: int}>
     */
    private function rangosCcosto(ReporteContableCuenta $cta): array
    {
        $out = [];
        foreach ($cta->ccostos as $cc) {
            $out[] = [
                'desde' => (int) $cc->ccosto_desde,
                'hasta' => (int) ($cc->ccosto_hasta ?: $cc->ccosto_desde),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $codigos
     * @param  list<string>  $advertencias
     * @return array{por_codigo: array<int, float>, fuente: string}
     */
    private function saldosViaSumasSaldos(
        array $empresaIds,
        int $periodo,
        array $codigos,
        string $baseSaldo,
        string $modoAsientos,
        int $monedaId,
        array &$advertencias,
    ): array {
        if ($codigos === []) {
            return ['por_codigo' => [], 'fuente' => 'ninguna'];
        }
        $resultado = $this->sumasSaldosProcesador->generar($empresaIds, [
            'modo_periodo' => SumasSaldosListadoFiltros::MODO_PERIODOS,
            'periodo_desde' => $periodo,
            'periodo_hasta' => $periodo,
            'consolidar_empresas' => true,
            'filtro_cuentas' => SumasSaldosListadoFiltros::CUENTAS_TODAS,
            'modo_inclusion_asientos' => $modoAsientos,
            'moneda_id' => $monedaId,
            'solo_moneda_origen' => false,
            'cuentas' => $codigos,
        ]);
        foreach ($resultado['advertencias'] ?? [] as $adv) {
            $advertencias[] = (string) $adv;
        }
        $porCodigo = [];
        foreach ($resultado['filas'] ?? [] as $fila) {
            $codigo = (int) ($fila['codigo'] ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $valor = $baseSaldo === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO
                ? (float) ($fila['saldo_ejercicio'] ?? 0)
                : (float) ($fila['saldo_periodo'] ?? 0);
            $porCodigo[$codigo] = ($porCodigo[$codigo] ?? 0.0) + $valor;
        }

        return ['por_codigo' => $porCodigo, 'fuente' => (string) ($resultado['fuente'] ?? 'sumas_saldos')];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0: string, 1: string, 2: list<int>, 3: list<string>}
     */
    private function resolverVentana(string $modo, array $filtros): array
    {
        if ($modo === SumasSaldosListadoFiltros::MODO_RANGO) {
            [$fd, $fh] = SumasSaldosListadoFiltros::normalizarRangoFechas(
                (string) ($filtros['fecha_desde'] ?? ''),
                (string) ($filtros['fecha_hasta'] ?? ''),
            );

            return $fd === '' || $fh === ''
                ? ['', '', [], ['Debe indicar fecha desde y hasta.']]
                : [$fd, $fh, [], []];
        }
        $periodoDesde = (int) ($filtros['periodo_desde'] ?? 0);
        $periodoHasta = (int) ($filtros['periodo_hasta'] ?? 0);
        if ($periodoDesde <= 0 || $periodoHasta <= 0) {
            return ['', '', [], ['Período inválido.']];
        }
        $periodos = $this->expandirPeriodos($periodoDesde, $periodoHasta);
        [$fd, $fh] = ReporteDefinibleSaldoReader::fechasDesdePeriodos($periodoDesde, $periodoHasta);

        return [$fd, $fh, $periodos, []];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{desde: int, hasta: int}|null
     */
    private function filtroCcostoRuntime(array $filtros): ?array
    {
        $desde = (int) ($filtros['ccosto_desde'] ?? 0);
        $hasta = (int) ($filtros['ccosto_hasta'] ?? 0);
        if ($desde <= 0 && $hasta <= 0) {
            return null;
        }
        if ($hasta <= 0) {
            $hasta = $desde;
        }
        if ($desde <= 0) {
            $desde = $hasta;
        }

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    private function definicionUsaCcosto(ReporteContable $reporte): bool
    {
        foreach ($reporte->rubros as $rubro) {
            foreach ($rubro->cuentas as $cta) {
                if (ReporteDefinibleSupport::normalizarCargaCcosto((string) $cta->carga_ccosto)
                    !== ReporteDefinibleSupport::CCOSTO_SIN) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<int> */
    private function codigosDelReporte(ReporteContable $reporte, ?string $origen = null): array
    {
        $codigos = [];
        foreach ($reporte->rubros as $rubro) {
            foreach ($rubro->cuentas as $cta) {
                if ($origen !== null && (string) $cta->origen !== $origen) {
                    continue;
                }
                foreach ($this->cuentaRangoSupport->expandirAsignacion($cta) as $codigo) {
                    $codigos[$codigo] = true;
                }
            }
        }

        return array_keys($codigos);
    }

    /** @return list<int> */
    private function expandirPeriodos(int $desde, int $hasta): array
    {
        $out = [];
        $y = intdiv($desde, 100);
        $m = $desde % 100;
        $guard = 0;
        while ($guard++ < 48) {
            $p = $y * 100 + $m;
            if ($p > $hasta) {
                break;
            }
            $out[] = $p;
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }

        return $out;
    }

    private function labelPeriodo(int $periodo): string
    {
        return sprintf('%02d/%04d', $periodo % 100, intdiv($periodo, 100));
    }

    private function labelRango(string $desde, string $hasta): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $desde);
        $h = \DateTime::createFromFormat('Y-m-d', $hasta);

        return ($d && $h)
            ? $d->format('d/m/Y').' – '.$h->format('d/m/Y')
            : $desde.' – '.$hasta;
    }

    /**
     * @param  list<string>  $advertencias
     * @return array<string, mixed>
     */
    private function vacio(array $advertencias): array
    {
        return [
            'columnas' => [],
            'filas' => [],
            'advertencias' => $advertencias,
            'fuente' => 'ninguna',
            'layout' => ReporteDefinibleSupport::LAYOUT_PERIODOS,
            'modo_periodo' => SumasSaldosListadoFiltros::MODO_PERIODOS,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'usa_ccosto' => false,
        ];
    }

    /**
     * @param  list<string>  $advertencias
     * @param  list<int>  $empresaIds
     * @return array{reglas: list<array<string, mixed>>, factores: array<int, float>, fecha_cierre: string|null, forzar_asientos: bool}
     */
    private function contextoConsolidacion(
        ReporteContable $reporte,
        array $filtros,
        array $empresaIds,
        string $fechaHasta,
        array &$advertencias,
    ): array {
        $reglas = [];
        $factores = [];
        $forzar = false;
        if ($this->eliminacionSupport->debeAplicar($filtros, $empresaIds)) {
            $reglas = $this->eliminacionSupport->reglasActivas((int) $reporte->id);
            if ($reglas !== []) {
                $nCod = 0;
                foreach ($reglas as $r) {
                    $nCod += count($r['codigos']);
                }
                $advertencias[] = sprintf(
                    'Consolidación: se aplican %d regla(s) IC (~%d códigos).',
                    count($reglas),
                    $nCod
                );
                if ($this->eliminacionSupport->tieneReglasPareja((int) $reporte->id)) {
                    $forzar = true;
                }
            }
        } elseif (count($empresaIds) >= 2 && empty($filtros['consolidar_empresas'])) {
            $advertencias[] = 'Suma multiempresa sin eliminaciones IC (consolidar desmarcado).';
        }

        if ((bool) ($filtros['consolidar_empresas'] ?? true) && count($empresaIds) >= 2) {
            if ($this->participacionSupport->tienePonderacion((int) $reporte->id, $fechaHasta)) {
                $factores = $this->participacionSupport->mapaFactores((int) $reporte->id, $fechaHasta);
                $advertencias[] = sprintf('Consolidación: participación aplicada a %d empresa(s).', count($factores));
                $forzar = true;
            }
        }

        $fechaCierre = null;
        if (($filtros['tipo_cambio_consolidacion'] ?? 'asiento') === 'cierre') {
            $fechaCierre = $fechaHasta;
            $advertencias[] = 'TC consolidación: cotización de cierre (fecha hasta).';
            $forzar = true;
        }

        return [
            'reglas' => $reglas,
            'factores' => $factores,
            'fecha_cierre' => $fechaCierre,
            'forzar_asientos' => $forzar,
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $codigos
     * @param  list<array<string, mixed>>  $reglas
     * @param  array<int, float>  $factores
     * @return list<array{codigo: int, ccosto: int, monto: float, fecha: string, empresa_id?: int}>
     */
    private function cargarMovimientosConsolidados(
        array $empresaIds,
        string $fdMov,
        string $fhMov,
        array $codigos,
        string $modoAsientos,
        int $monedaId,
        bool $soloOrigen,
        ?string $fechaCierre,
        array $reglas,
        array $factores,
    ): array {
        $movs = $this->saldoReader->listarMovimientos(
            $empresaIds, $fdMov, $fhMov, $codigos, $modoAsientos, $monedaId, $soloOrigen, $fechaCierre
        );
        $movs = $this->eliminacionSupport->filtrarMovimientos($movs, $reglas);
        $movs = $this->participacionSupport->aplicarAMovimientos($movs, $factores);

        return $movs;
    }

}
