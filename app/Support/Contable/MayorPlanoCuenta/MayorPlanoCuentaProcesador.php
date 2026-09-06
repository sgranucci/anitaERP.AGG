<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Services\Contable\AnitaAsientoImportService;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorFuenteConsultaSupport;
use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use Illuminate\Support\Facades\DB;

/**
 * Motor del mayor analítico por cuenta (l-mayor.c): ctamov + subdiario opcional.
 */
class MayorPlanoCuentaProcesador
{
    /** @var array<int, array{codigo: int, nombre: string}> */
    private array $nombresCuenta = [];

    /** @var array<int, string> */
    private array $nombresEmpresa = [];

    /** @var array<string, string> */
    private array $nombresCentrocosto = [];

    private bool $soloMovimientosVentas = false;

    private string $fuenteMayorModo = MayorFuenteConsultaSupport::MODO_AUTO;

    public function __construct(
        private readonly MayorPlanoCuentaAnitaBridgeReader $reader = new MayorPlanoCuentaAnitaBridgeReader,
        private readonly MayorPlanoCuentaErpAsientoReader $erpReader = new MayorPlanoCuentaErpAsientoReader,
        private readonly SumasSaldosProcesador $sumasSaldosProcesador = new SumasSaldosProcesador,
    ) {}

    /**
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function generar(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        int $cuentaDesde,
        int $cuentaHasta,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        bool $incluyeSubdiario,
        string $modoInclusionAsientos,
        MayorConceptoMonedaConverter $monedaConverter,
        array $cuentas = [],
        ?MayorPlanoCuentaCentrocostoFiltroSupport $centrocostoFiltro = null,
        bool $agruparPorCc = false,
        bool $soloMovimientosVentas = false,
        string $fuenteMayor = MayorFuenteConsultaSupport::MODO_AUTO,
    ): array {
        $this->soloMovimientosVentas = $soloMovimientosVentas;
        $this->fuenteMayorModo = MayorFuenteConsultaSupport::normalizarModo($fuenteMayor);
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            return $this->resultadoVacio();
        }

        $cuentas = array_values(array_unique(array_filter(array_map('intval', $cuentas), fn (int $c) => $c > 0)));
        sort($cuentas);
        $centrocostoFiltro ??= new MayorPlanoCuentaCentrocostoFiltroSupport;

        $t0 = microtime(true);
        $this->precargarNombres($empresaIds);

        $inicioEjercicio = MayorPlanoCuentaSupport::inicioEjercicio($fechaDesde);
        $fechaComienzoAjustada = MayorPlanoCuentaSupport::fechaComienzoEjercicioAjustada(
            $fechaDesde,
            $inicioEjercicio,
        );
        $cutoffErp = MayorFuenteConsultaSupport::corteEfectivo(
            $this->fuenteMayorModo,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );

        if ($this->soloMovimientosVentas) {
            // Totales del mes: subdiario sistema V (detalle Anita) + ctamov ERP (asi_mon_ref=-1),
            // sin tramo de saldo previo. El resumen ctamov (asi_mon_ref ≠ -1) se deduplica al normalizar.
            $diagSaldo = [
                'fecha_comienzo_ejercicio' => $inicioEjercicio,
                'fecha_comienzo_ajustada' => $fechaComienzoAjustada,
                'fecha_saldo_desde' => $fechaDesde,
                'origen_minimo' => MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD,
                'por_empresa' => [],
                'origen' => 'solo_ventas_periodo',
            ];
            $fechaSaldoDesde = $fechaDesde;
            $planSaldo = [
                'usar_saldos_mes' => false,
                'por_codigo' => [],
                'fuente' => 'movimientos_solo_ventas',
                'movimientos_restados' => 0,
                'fecha_saldo_movimientos_desde' => $fechaDesde,
                'advertencias' => [],
            ];
            $saldosInicialesPorCuenta = [];
            $omitirCargaSaldoErpCompleto = true;
            $fechaSaldoMovimientosDesde = $fechaDesde;
            $incluyeSubdiarioEfectivo = true;
            $soloPeriodoBridge = true;
        } else {
            $diagSaldo = $this->reader->diagnosticarSaldoInicial(
                $empresaIds,
                $fechaDesde,
                $fechaComienzoAjustada,
            );
            $fechaSaldoDesde = (int) ($diagSaldo['fecha_saldo_desde'] ?? MayorPlanoCuentaSupport::SALDO_ORIGEN_MINIMO_YMD);

            // Tramo cubierto por asientos ERP importados: no aplicar piso Anita 20260101.
            if ($cutoffErp > 0 && $fechaDesde <= $cutoffErp) {
                $fechaSaldoDesde = $inicioEjercicio;
                $diagSaldo['fecha_saldo_desde'] = $fechaSaldoDesde;
                $diagSaldo['origen'] = 'erp_ejercicio';
            }

            $planSaldo = $this->planSaldoInicialDesdeSaldosMes(
                $empresaIds,
                $fechaDesde,
                $fechaSaldoDesde,
                $cutoffErp,
                $cuentaDesde,
                $cuentaHasta,
                $cuentas,
                $monedaReporteId,
                $soloMonedaOrigen,
                $incluyeSubdiario,
                $modoInclusionAsientos,
                $centrocostoFiltro->tieneFiltro() || $agruparPorCc,
            );
            $saldosInicialesPorCuenta = $planSaldo['por_codigo'];
            $omitirCargaSaldoErpCompleto = (bool) ($planSaldo['usar_saldos_mes'] ?? false);
            $fechaSaldoMovimientosDesde = (int) ($planSaldo['fecha_saldo_movimientos_desde'] ?? $fechaSaldoDesde);
            $incluyeSubdiarioEfectivo = $incluyeSubdiario;
            $soloPeriodoBridge = false;
        }

        $datos = $this->cargarPeriodoHibrido(
            $empresaIds,
            $fechaDesde,
            $fechaHasta,
            $omitirCargaSaldoErpCompleto ? $fechaSaldoMovimientosDesde : $fechaSaldoDesde,
            $incluyeSubdiarioEfectivo,
            $cuentaDesde,
            $cuentaHasta,
            $cuentas,
            $this->soloMovimientosVentas
                ? true
                : ($omitirCargaSaldoErpCompleto && $fechaSaldoMovimientosDesde <= 0),
            $soloPeriodoBridge,
        );

        $erroresBridge = $datos['errores'] ?? [];
        $timings = $datos['timings'] ?? [];

        // pago/auxpag solo si hay OP en los movimientos (leyendas OP y nro OC).
        // Antes se bajaba TODO el che_ban desde enero → minutos y OOM aunque filtraras 1 cuenta.
        $pago = [];
        $auxpag = [];
        $cargoPagoAuxpag = false;
        $tramoAnitaDesde = (int) ($datos['tramo_anita_desde'] ?? 0);
        $tramoAnitaHasta = (int) ($datos['tramo_anita_hasta'] ?? 0);
        if ($tramoAnitaDesde > 0 && $tramoAnitaHasta >= $tramoAnitaDesde
            && $this->reader->hayOrdenesPagoEnMovimientos($datos['ctamov'] ?? [], $datos['subdiario'] ?? [])) {
            $cargoPagoAuxpag = true;
            $extra = $this->reader->cargarPagoYAuxpagPeriodo(
                $empresaIds,
                $tramoAnitaDesde,
                $tramoAnitaHasta,
                $erroresBridge,
            );
            $pago = $extra['pago'] ?? [];
            $auxpag = $extra['auxpag'] ?? [];
            $timings = array_merge($timings, $extra['timings'] ?? []);
        }

        $resolverOc = new MayorPlanoCuentaOrdencompraResolver;
        $statsOc = $resolverOc->preparar($auxpag, $erroresBridge);

        $leyendasPago = MayorPlanoCuentaPagoLeyendaIndex::desdeFilas($pago);

        $movimientos = $this->normalizarMovimientos(
            $datos['ctamov'] ?? [],
            $datos['subdiario'] ?? [],
            $incluyeSubdiarioEfectivo,
            $leyendasPago,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
            $modoInclusionAsientos,
            $cuentaDesde,
            $cuentaHasta,
            $cuentas,
            $centrocostoFiltro,
        );

        $movimientos = $resolverOc->aplicarAMovimientos($movimientos);
        $statsOc['movimientos_oc_resueltos'] = $resolverOc->cantidadMovimientosResueltos();

        $cuentasSeccion = $this->cuentasEnRango($movimientos, $cuentaDesde, $cuentaHasta, $cuentas);
        if ($saldosInicialesPorCuenta !== []) {
            foreach ($saldosInicialesPorCuenta as $codigoSaldo => $importeSaldo) {
                $codigoSaldo = (int) $codigoSaldo;
                if ($codigoSaldo <= 0 || abs((float) $importeSaldo) < 0.005) {
                    continue;
                }
                if (! $this->cuentaEnFiltro($codigoSaldo, $cuentaDesde, $cuentaHasta, $cuentas)) {
                    continue;
                }
                $cuentasSeccion[] = $codigoSaldo;
            }
            $cuentasSeccion = array_values(array_unique($cuentasSeccion));
            sort($cuentasSeccion);
        }
        $secciones = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalLineas = 0;

        foreach ($cuentasSeccion as $cuenta) {
            $argumentosCuenta = [
                $cuenta,
                $movimientos,
                $fechaDesde,
                $fechaHasta,
                $omitirCargaSaldoErpCompleto ? $fechaSaldoMovimientosDesde : $fechaSaldoDesde,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
                $modoInclusionAsientos,
                $saldosInicialesPorCuenta[$cuenta] ?? null,
            ];
            $seccion = $agruparPorCc
                ? $this->procesarCuentaAgrupada(...$argumentosCuenta)
                : $this->procesarCuenta(...$argumentosCuenta);

            if ($seccion === null) {
                continue;
            }

            $secciones[] = $seccion;
            $totalDebe += (float) ($seccion['total_debe'] ?? 0);
            $totalHaber += (float) ($seccion['total_haber'] ?? 0);
            $totalLineas += (int) ($seccion['cantidad_lineas'] ?? 0);
        }

        $timings['total_ms'] = round((microtime(true) - $t0) * 1000, 1);
        $timings['cargo_pago_auxpag'] = $cargoPagoAuxpag;
        \Illuminate\Support\Facades\Log::info('mayor_plano_cuenta.generar', [
            'empresas' => $empresaIds,
            'cuenta_desde' => $cuentaDesde,
            'cuenta_hasta' => $cuentaHasta,
            'cuentas' => $cuentas,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_saldo_desde' => $fechaSaldoDesde,
            'timings' => $timings,
        ]);

        return [
            'parametros' => [
                'empresa_ids' => $empresaIds,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'fecha_comienzo_ejercicio' => $inicioEjercicio,
                'fecha_comienzo_ejercicio_ajustada' => $fechaComienzoAjustada,
                'fecha_saldo_desde' => $fechaSaldoDesde,
                'saldo_inicial' => $diagSaldo,
                'cuenta_desde' => $cuentaDesde,
                'cuenta_hasta' => $cuentaHasta,
                'cuentas' => $cuentas,
                'moneda_id' => $monedaReporteId,
                'solo_moneda_origen' => $soloMonedaOrigen,
                'incluye_subdiario' => $incluyeSubdiarioEfectivo,
                'incluye_subdiario_solicitado' => $incluyeSubdiario,
                'solo_movimientos_ventas' => $this->soloMovimientosVentas,
                'modo_inclusion_asientos' => $modoInclusionAsientos,
                'centrocostos_codigo' => $centrocostoFiltro->codigos(),
                'centrocostos_meta' => $centrocostoFiltro->metaTexto(),
                'agrupar_por_cc' => $agruparPorCc,
                'incluir_sin_cc' => $centrocostoFiltro->incluirSinCc(),
                'fuente_erp_hasta' => (int) ($datos['fuente_erp_hasta'] ?? 0),
                'tramo_erp_desde' => (int) ($datos['tramo_erp_desde'] ?? 0),
                'tramo_erp_hasta' => (int) ($datos['tramo_erp_hasta'] ?? 0),
                'tramo_anita_desde' => (int) ($datos['tramo_anita_desde'] ?? 0),
                'tramo_anita_hasta' => (int) ($datos['tramo_anita_hasta'] ?? 0),
                'fuente_mayor' => $this->fuenteMayorModo,
                'fuente_etiqueta' => (string) ($datos['fuente_etiqueta'] ?? ''),
                'saldo_inicial_fuente' => (string) ($planSaldo['fuente'] ?? 'movimientos'),
                'saldo_inicial_usar_saldos_mes' => $omitirCargaSaldoErpCompleto,
            ],
            'secciones' => $secciones,
            'totales' => [
                'debe' => round($totalDebe, 2),
                'haber' => round($totalHaber, 2),
                'lineas' => $totalLineas,
                'cuentas' => count($secciones),
            ],
            'errores_bridge' => $erroresBridge,
            'stats' => array_merge([
                'ctamov_filas' => count($datos['ctamov'] ?? []),
                'subdiario_filas' => count($datos['subdiario'] ?? []),
                'erp_asientos_filas' => (int) ($datos['timings']['erp_asientos_filas'] ?? 0),
                'erp_asientos_movimientos_origen' => (int) ($datos['timings']['erp_asientos_movimientos_origen'] ?? 0),
                'erp_asientos_grupos' => (int) ($datos['timings']['erp_asientos_grupos'] ?? 0),
                'pago_filas' => count($pago),
                'pago_leyendas_indexadas' => $leyendasPago->cantidadClaves(),
                'saldo_mes_cuentas' => count($saldosInicialesPorCuenta),
                'saldo_mes_movimientos_restados' => (int) ($planSaldo['movimientos_restados'] ?? 0),
                'timings' => $timings,
            ], $statsOc),
        ];
    }

    /**
     * Hasta MAYOR_PLANO_CUENTA_FUENTE_ERP_HASTA lee asientos ERP; después bridge Anita.
     * El modo `fuente_mayor` puede forzar Anita en el tramo ya migrado.
     *
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @return array<string, mixed>
     */
    private function cargarPeriodoHibrido(
        array $empresaIds,
        int $fechaDesde,
        int $fechaHasta,
        int $fechaSaldoDesde,
        bool $incluyeSubdiario,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas,
        bool $omitirCargaSaldoErp = false,
        bool $soloPeriodoBridge = false,
    ): array {
        $tramos = MayorFuenteConsultaSupport::resolverTramos(
            $fechaDesde,
            $fechaHasta,
            $this->fuenteMayorModo,
            'contable.mayor_plano_cuenta.fuente_erp_hasta',
        );
        $cutoff = (int) $tramos['corte'];

        // Sin cutoff o forzar Anita: 100% bridge Anita (comportamiento histórico / control).
        if (! $tramos['usa_erp']) {
            $anita = $this->reader->cargarPeriodo(
                $empresaIds,
                $fechaDesde,
                $fechaHasta,
                $fechaSaldoDesde,
                $incluyeSubdiario,
                $cuentaDesde,
                $cuentaHasta,
                $cuentas,
                $this->soloMovimientosVentas,
                $soloPeriodoBridge,
            );

            $anita['ctamov'] = MayorPlanoCuentaAnitaErpMetadatosSupport::adjuntarEmisorDesdeAsientoErp(
                $anita['ctamov'] ?? []
            );

            return array_merge($anita, [
                'fuente_erp_hasta' => $cutoff,
                'tramo_erp_desde' => 0,
                'tramo_erp_hasta' => 0,
                'tramo_anita_desde' => $fechaDesde,
                'tramo_anita_hasta' => $fechaHasta,
                'fuente_etiqueta' => $tramos['etiqueta'],
                'fuente_mayor' => $this->fuenteMayorModo,
            ]);
        }

        $errores = [];
        $timings = [];
        $ctamov = [];
        $subdiario = [];
        $postCutoff = MayorFuenteConsultaSupport::fechaSiguiente($cutoff);

        // 1) Saldo inicial [fechaSaldoDesde, día anterior a fechaDesde], si aplica.
        //    Si ya se tomó de cuentacontable_saldo_mes (SyS), se omite o solo se lee
        //    el tramo parcial del mes (fechaDesde no es día 1).
        $saldoHasta = $this->fechaAnterior($fechaDesde);
        $tramoErpDesde = 0;
        $tramoErpHasta = 0;
        if (! $omitirCargaSaldoErp && $fechaSaldoDesde > 0 && $saldoHasta >= $fechaSaldoDesde) {
            $erpSaldoDesde = $fechaSaldoDesde;
            $erpSaldoHasta = min($saldoHasta, $cutoff);
            if ($erpSaldoDesde <= $erpSaldoHasta) {
                $tramoErpDesde = $erpSaldoDesde;
                $tramoErpHasta = $erpSaldoHasta;
                $erp = $this->erpReader->cargarPeriodo(
                    $empresaIds,
                    $erpSaldoDesde,
                    $erpSaldoHasta,
                    $incluyeSubdiario,
                    $cuentaDesde,
                    $cuentaHasta,
                    $cuentas,
                    false,
                    $this->soloMovimientosVentas,
                );
                $ctamov = array_merge($ctamov, $erp['ctamov'] ?? []);
                $errores = array_merge($errores, $erp['errores'] ?? []);
                $timings = array_merge($timings, ['erp_saldo' => $erp['timings'] ?? []]);
            }
        }

        // 2) Período consultado [fechaDesde, fechaHasta] — tramo ERP acotado al corte.
        $erpPerDesde = (int) $tramos['tramo_erp_desde'];
        $erpPerHasta = (int) $tramos['tramo_erp_hasta'];
        if ($erpPerDesde > 0 && $erpPerHasta >= $erpPerDesde) {
            if ($tramoErpDesde === 0) {
                $tramoErpDesde = $erpPerDesde;
            }
            $tramoErpHasta = $erpPerHasta;
            $erp = $this->erpReader->cargarPeriodo(
                $empresaIds,
                $erpPerDesde,
                $erpPerHasta,
                $incluyeSubdiario,
                $cuentaDesde,
                $cuentaHasta,
                $cuentas,
                true,
                $this->soloMovimientosVentas,
            );
            $ctamov = array_merge($ctamov, $erp['ctamov'] ?? []);
            $errores = array_merge($errores, $erp['errores'] ?? []);
            $timings = array_merge($timings, $erp['timings'] ?? []);
        }

        $tramoAnitaDesde = 0;
        $tramoAnitaHasta = 0;
        $anitaPeriodoDesde = (int) $tramos['tramo_anita_desde'];
        $anitaPeriodoHasta = (int) $tramos['tramo_anita_hasta'];
        if ($anitaPeriodoDesde > 0 && $anitaPeriodoHasta >= $anitaPeriodoDesde) {
            $tramoAnitaDesde = $anitaPeriodoDesde;
            $tramoAnitaHasta = $anitaPeriodoHasta;
            $anitaSaldoPedido = max($fechaSaldoDesde, $postCutoff);
            // Saldo Anita solo si hay tramo pre-período después del cutoff.
            if ($anitaSaldoPedido >= $anitaPeriodoDesde) {
                $anitaSaldoPedido = $anitaPeriodoDesde;
            }
            $anita = $this->reader->cargarPeriodo(
                $empresaIds,
                $anitaPeriodoDesde,
                $anitaPeriodoHasta,
                $anitaSaldoPedido,
                $incluyeSubdiario,
                $cuentaDesde,
                $cuentaHasta,
                $cuentas,
                $this->soloMovimientosVentas,
                $soloPeriodoBridge,
            );
            $ctamov = array_merge($ctamov, $anita['ctamov'] ?? []);
            $subdiario = array_merge($subdiario, $anita['subdiario'] ?? []);
            $errores = array_merge($errores, $anita['errores'] ?? []);
            $timings = array_merge($timings, $anita['timings'] ?? []);
        }

        $ctamov = MayorPlanoCuentaAnitaErpMetadatosSupport::adjuntarEmisorDesdeAsientoErp($ctamov);

        return [
            'ctamov' => $ctamov,
            'subdiario' => $subdiario,
            'pago' => [],
            'auxpag' => [],
            'errores' => $errores,
            'timings' => $timings,
            'fuente_erp_hasta' => $cutoff,
            'tramo_erp_desde' => $tramoErpDesde,
            'tramo_erp_hasta' => $tramoErpHasta,
            'tramo_anita_desde' => $tramoAnitaDesde,
            'tramo_anita_hasta' => $tramoAnitaHasta,
            'fuente_etiqueta' => $tramos['etiqueta'],
            'fuente_mayor' => $this->fuenteMayorModo,
        ];
    }

    private function fechaAnterior(int $ymd): int
    {
        if ($ymd <= 0) {
            return 0;
        }
        $dt = \DateTimeImmutable::createFromFormat('Ymd', (string) $ymd);
        if (! $dt) {
            return $ymd - 1;
        }

        return (int) $dt->modify('-1 day')->format('Ymd');
    }

    /**
     * Reutiliza SumasSaldosProcesador (cuentacontable_saldo_mes − exclusiones)
     * cuando el tramo de saldo inicial está cubierto por ERP.
     *
     * @param  list<int>  $empresaIds
     * @param  list<int>  $cuentas
     * @return array{
     *   usar_saldos_mes: bool,
     *   por_codigo: array<int, float>,
     *   fuente: string,
     *   movimientos_restados: int,
     *   fecha_saldo_movimientos_desde: int,
     *   advertencias: list<string>
     * }
     */
    private function planSaldoInicialDesdeSaldosMes(
        array $empresaIds,
        int $fechaDesde,
        int $fechaSaldoDesde,
        int $cutoffErp,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        bool $incluyeSubdiario,
        string $modoInclusionAsientos,
        bool $requiereGranoCentrocosto,
    ): array {
        $vacio = [
            'usar_saldos_mes' => false,
            'por_codigo' => [],
            'fuente' => 'movimientos',
            'movimientos_restados' => 0,
            'fecha_saldo_movimientos_desde' => $fechaSaldoDesde,
            'advertencias' => [],
        ];

        if ($requiereGranoCentrocosto
            || $cutoffErp <= 0 || $fechaDesde > $cutoffErp || $fechaSaldoDesde <= 0) {
            return $vacio;
        }

        $saldoHasta = $this->fechaAnterior($fechaDesde);
        if ($saldoHasta < $fechaSaldoDesde || $saldoHasta > $cutoffErp) {
            return $vacio;
        }

        // Moneda extranjera sin “solo origen” exige cotización por asiento (mismo criterio SyS).
        if (! $soloMonedaOrigen && $monedaReporteId !== CuentacontableSaldoMesSupport::monedaLocalId()) {
            return $vacio;
        }

        $periodoDesde = (int) intdiv($fechaDesde, 100);
        $t0 = microtime(true);
        $resultado = $this->sumasSaldosProcesador->saldosInicialesPorCodigo($empresaIds, $periodoDesde, [
            'modo_inclusion_asientos' => $modoInclusionAsientos,
            'moneda_id' => $monedaReporteId,
            'solo_moneda_origen' => $soloMonedaOrigen,
            'cuenta_desde' => $cuentaDesde,
            'cuenta_hasta' => $cuentaHasta,
            'cuentas' => $cuentas,
            'excluir_origen_subdiario' => ! $incluyeSubdiario,
        ]);

        if (($resultado['fuente'] ?? '') !== 'saldos_mes') {
            return array_merge($vacio, [
                'fuente' => (string) ($resultado['fuente'] ?? 'movimientos'),
                'advertencias' => $resultado['advertencias'] ?? [],
            ]);
        }

        $dia = (int) ($fechaDesde % 100);
        // Día 1: no hace falta leer movimientos previos. Si no, solo el tramo del mes.
        $fechaMovDesde = $dia <= 1
            ? 0
            : ((int) (intdiv($fechaDesde, 100) * 100) + 1);

        return [
            'usar_saldos_mes' => true,
            'por_codigo' => $resultado['por_codigo'] ?? [],
            'fuente' => 'saldos_mes',
            'movimientos_restados' => (int) ($resultado['movimientos_restados'] ?? 0),
            'fecha_saldo_movimientos_desde' => $fechaMovDesde,
            'advertencias' => $resultado['advertencias'] ?? [],
            'timings_ms' => round((microtime(true) - $t0) * 1000, 1),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function precargarNombres(array $empresaIds): void
    {
        $this->nombresCuenta = [];
        $this->nombresEmpresa = [];
        $this->nombresCentrocosto = [];

        foreach ($empresaIds as $empresaId) {
            $nombreEmp = DB::table('empresa')->where('id', $empresaId)->value('nombre');
            $this->nombresEmpresa[$empresaId] = $nombreEmp ? (string) $nombreEmp : 'Empresa '.$empresaId;

            $cuentas = DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->get(['codigo', 'nombre']);

            foreach ($cuentas as $c) {
                $codigo = (int) $c->codigo;
                if ($codigo > 0) {
                    $this->nombresCuenta[$codigo] = [
                        'codigo' => $codigo,
                        'nombre' => (string) $c->nombre,
                    ];
                }
            }
        }

        foreach (DB::table('centrocosto')->get(['codigo', 'nombre']) as $cc) {
            $codigo = $this->normalizarCodigoCentrocosto($cc->codigo ?? null);
            if ($codigo !== '') {
                $this->nombresCentrocosto[$codigo] = trim((string) ($cc->nombre ?? ''));
            }
        }
    }

    /**
     * @param  list<object>  $ctamov
     * @param  list<object>  $subdiario
     * @return list<array<string, mixed>>
     */
    private function normalizarMovimientos(
        array $ctamov,
        array $subdiario,
        bool $incluyeSubdiario,
        MayorPlanoCuentaPagoLeyendaIndex $leyendasPago,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $modoInclusionAsientos,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas = [],
        ?MayorPlanoCuentaCentrocostoFiltroSupport $centrocostoFiltro = null,
    ): array {
        $movs = [];
        $centrocostoFiltro ??= new MayorPlanoCuentaCentrocostoFiltroSupport;

        // El mayor de ventas toma el detalle de subdiario sistema V. El ctamov de
        // cierre (V/C/T y asi_mon_ref ≠ -1) duplica ese detalle. Las FAC del ERP
        // (asi_mon_ref = -1) no están en subdiario: hay que sumarlas desde ctamov.
        $omitirResumenCtamov = $this->soloMovimientosVentas && $incluyeSubdiario && $subdiario !== [];

        foreach ($ctamov as $linea) {
            if ($omitirResumenCtamov && AnitaAsientoImportService::esAsientoResumenSubdiario($linea)) {
                continue;
            }
            $mov = $this->desdeCtamov($linea, $leyendasPago);
            if ($mov !== null && $this->movimientoAplica($mov, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $modoInclusionAsientos, $cuentaDesde, $cuentaHasta, $cuentas, $centrocostoFiltro)) {
                $movs[] = $mov;
            }
        }

        if ($incluyeSubdiario) {
            $movs = array_merge(
                $movs,
                $this->normalizarSubdiario(
                    $subdiario,
                    $leyendasPago,
                    $monedaConverter,
                    $monedaReporteId,
                    $soloMonedaOrigen,
                    $modoInclusionAsientos,
                    $cuentaDesde,
                    $cuentaHasta,
                    $cuentas,
                    $centrocostoFiltro,
                ),
            );
        }

        return $movs;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @param  list<int>  $cuentas
     */
    private function movimientoAplica(
        array $mov,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $modoInclusionAsientos,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas = [],
        ?MayorPlanoCuentaCentrocostoFiltroSupport $centrocostoFiltro = null,
    ): bool {
        $cuenta = (int) ($mov['cuenta'] ?? 0);
        if ($cuenta <= 0) {
            return false;
        }

        if (! $this->cuentaEnFiltro($cuenta, $cuentaDesde, $cuentaHasta, $cuentas)) {
            return false;
        }

        if (! $this->soloMovimientosVentas && ($mov['balancea'] ?? 'S') !== 'S') {
            return false;
        }

        if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento(
            (string) ($mov['tipo_asiento'] ?? ''),
            $modoInclusionAsientos,
        )) {
            return false;
        }

        $centrocostoFiltro ??= new MayorPlanoCuentaCentrocostoFiltroSupport;
        if (! $centrocostoFiltro->pasaFiltro($mov['centrocosto_codigo'] ?? null)) {
            return false;
        }

        if ($this->soloMovimientosVentas && ! MayorPlanoCuentaVentasFiltroSupport::esMovimientoVentas($mov)) {
            return false;
        }

        return MayorPlanoCuentaSupport::movimientoVisibleMoneda(
            (string) ($mov['cod_mon'] ?? '1'),
            (float) ($mov['cotizacion'] ?? 0),
            $monedaConverter->codigoAnitaDesdeMonedaId($monedaReporteId),
            $soloMonedaOrigen,
        );
    }

    /**
     * @param  list<int>  $cuentas
     */
    private function cuentaEnFiltro(int $cuenta, int $cuentaDesde, int $cuentaHasta, array $cuentas): bool
    {
        $tieneLista = $cuentas !== [];
        $tieneRango = $cuentaDesde > 0 || $cuentaHasta > 0;

        if (! $tieneLista && ! $tieneRango) {
            return true;
        }

        if ($tieneLista && in_array($cuenta, $cuentas, true)) {
            return true;
        }

        if ($tieneRango) {
            if ($cuentaDesde > 0 && $cuenta < $cuentaDesde) {
                return false;
            }
            if ($cuentaHasta > 0 && $cuenta > $cuentaHasta) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Subdiario l-mayor.c: dos pasadas (índice subd_cuenta y subd_contrapartida), agrupando por
     * nro_operacion + D/H; asiento = subd_nro_operacion con prefijo S al mostrar.
     *
     * @param  list<object>  $subdiario
     * @return list<array<string, mixed>>
     */
    private function normalizarSubdiario(
        array $subdiario,
        MayorPlanoCuentaPagoLeyendaIndex $leyendasPago,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $modoInclusionAsientos,
        int $cuentaDesde,
        int $cuentaHasta,
        array $cuentas = [],
        ?MayorPlanoCuentaCentrocostoFiltroSupport $centrocostoFiltro = null,
    ): array {
        $movs = [];
        $centrocostoFiltro ??= new MayorPlanoCuentaCentrocostoFiltroSupport;

        foreach (['cuenta', 'contrapartida'] as $pasada) {
            foreach ($this->agruparSubdiario($subdiario, $pasada) as $grupo) {
                $mov = $this->movimientoDesdeGrupoSubdiario($grupo, $pasada, $leyendasPago, $monedaConverter, $monedaReporteId, $soloMonedaOrigen);
                if ($mov === null) {
                    continue;
                }
                if ($this->movimientoAplica($mov, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $modoInclusionAsientos, $cuentaDesde, $cuentaHasta, $cuentas, $centrocostoFiltro)) {
                    $movs[] = $mov;
                }
            }
        }

        return $movs;
    }

    /**
     * @param  list<object>  $subdiario
     * @return list<array{lineas: list<object>, cuenta: int, dh: string, pasada: string, centrocosto_codigo: string}>
     */
    private function agruparSubdiario(array $subdiario, string $pasada): array
    {
        /** @var array<string, array{lineas: list<object>, cuenta: int, dh: string, pasada: string, centrocosto_codigo: string}> $grupos */
        $grupos = [];

        foreach ($subdiario as $linea) {
            if ($pasada === 'cuenta') {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                $dh = strtoupper(trim((string) ($linea->subd_tipo_mov ?? 'D')));
                $centrocostoCodigo = $this->normalizarCodigoCentrocosto($linea->subd_ccosto_cta ?? null);
            } else {
                $cuenta = (int) ($linea->subd_contrapartida ?? 0);
                if ($cuenta <= 0) {
                    continue;
                }
                $tipoMov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? 'D')));
                $dh = $tipoMov === 'D' ? 'H' : 'D';
                $centrocostoCodigo = $this->normalizarCodigoCentrocosto($linea->subd_ccosto_con ?? null);
            }

            if ($cuenta <= 0) {
                continue;
            }

            $empresa = (int) ($linea->subd_empresa ?? 0);
            $fecha = (int) ($linea->subd_fecha ?? 0);
            $nroOp = (int) ($linea->subd_nro_operacion ?? 0);
            $clave = $empresa.'|'.$cuenta.'|'.$fecha.'|'.$nroOp.'|'.$dh.'|'.$centrocostoCodigo;

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'lineas' => [],
                    'cuenta' => $cuenta,
                    'dh' => $dh,
                    'pasada' => $pasada,
                    'centrocosto_codigo' => $centrocostoCodigo,
                ];
            }

            $grupos[$clave]['lineas'][] = $linea;
        }

        return array_values($grupos);
    }

    /**
     * @param  array{lineas: list<object>, cuenta: int, dh: string, pasada: string, centrocosto_codigo: string}  $grupo
     * @return array<string, mixed>|null
     */
    private function movimientoDesdeGrupoSubdiario(
        array $grupo,
        string $pasada,
        MayorPlanoCuentaPagoLeyendaIndex $leyendasPago,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): ?array {
        $lineas = $grupo['lineas'] ?? [];
        if ($lineas === []) {
            return null;
        }

        $lineaRef = $lineas[0];
        $codMonReporte = $monedaConverter->codigoAnitaDesdeMonedaId($monedaReporteId);
        $importeTotal = 0.0;
        $importeNativoTotal = 0.0;
        $codMonGrupo = trim((string) ($lineaRef->subd_cod_mon ?? '1')) !== ''
            ? trim((string) ($lineaRef->subd_cod_mon ?? '1'))
            : '1';
        $cotizGrupo = (float) ($lineaRef->subd_cotizacion ?? 0);
        $fecha = (int) ($lineaRef->subd_fecha ?? 0);

        foreach ($lineas as $linea) {
            $codMon = trim((string) ($linea->subd_cod_mon ?? '1')) !== ''
                ? trim((string) ($linea->subd_cod_mon ?? '1'))
                : '1';
            $cotiz = (float) ($linea->subd_cotizacion ?? 0);

            if (! MayorPlanoCuentaSupport::movimientoVisibleMoneda($codMon, $cotiz, $codMonReporte, $soloMonedaOrigen)) {
                continue;
            }

            $importeNativo = (float) ($linea->subd_importe ?? 0);
            $importeNativoTotal += $importeNativo;
            $importeTotal += $monedaConverter->convertirImporte(
                $importeNativo,
                $codMon,
                $cotiz,
                (int) ($linea->subd_fecha ?? $fecha),
                $monedaReporteId,
            );
        }

        if (abs($importeTotal) < 0.005) {
            return null;
        }

        $subdTipo = trim((string) ($lineaRef->subd_tipo ?? ''));
        $tipoComp = strtoupper(substr($subdTipo, 0, 3)) === 'AOP'
            ? $subdTipo
            : trim((string) ($lineaRef->subd_ref_tipo ?? $subdTipo));
        $sucursal = (int) ($lineaRef->subd_ref_sucursal ?? $lineaRef->subd_sucursal ?? 0);
        $nro = (int) ($lineaRef->subd_ref_nro ?? $lineaRef->subd_nro ?? 0);
        // subd_emisor es proveedor, cliente o cuenta de caja según el subsistema.
        $emisor = MayorPlanoCuentaEmisorSupport::resolver(
            (string) ($lineaRef->subd_sistema ?? ''),
            $tipoComp,
            (string) ($lineaRef->subd_emisor ?? ''),
            (string) ($lineaRef->subd_desc_mov ?? ''),
        );

        return [
            'origen' => 'subdiario',
            'sistema' => strtoupper(trim((string) ($lineaRef->subd_sistema ?? ''))),
            'empresa_id' => (int) ($lineaRef->subd_empresa ?? 0),
            'cuenta' => (int) $grupo['cuenta'],
            'centrocosto_codigo' => (string) ($grupo['centrocosto_codigo'] ?? ''),
            'fecha' => $fecha,
            'nro_asiento' => (int) ($lineaRef->subd_nro_operacion ?? 0),
            'nro_linea' => $pasada === 'cuenta' ? 1 : 2,
            'dh' => (string) $grupo['dh'],
            'importe' => $importeTotal,
            'tipo_comp' => $tipoComp,
            'letra' => trim((string) ($lineaRef->subd_ref_letra ?? $lineaRef->subd_letra ?? ' ')),
            'sucursal' => $sucursal,
            'nro' => $nro,
            'descripcion' => MayorPlanoCuentaSupport::resolverDescripcionMovimiento(
                $tipoComp,
                $sucursal,
                $nro,
                (string) ($lineaRef->subd_desc_mov ?? ''),
                $leyendasPago,
            ),
            'cod_mon' => $codMonGrupo,
            'cotizacion' => $cotizGrupo,
            'tipo_asiento' => '',
            'balancea' => 'S',
            'nro_oc' => 0,
            'emisor' => $emisor['codigo'],
            'emisor_entidad' => $emisor['entidad'],
            'emisor_deducido' => $emisor['deducido'],
            'cuit' => '',
            'es_subdiario' => true,
            'importe_ya_convertido' => true,
            'importe_nativo' => $importeNativoTotal,
            'orden_sort' => $pasada === 'cuenta' ? 1 : 2,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function desdeCtamov(object $linea, MayorPlanoCuentaPagoLeyendaIndex $leyendasPago): ?array
    {
        $cuenta = (int) ($linea->ctav_cuenta ?? 0);
        if ($cuenta <= 0) {
            return null;
        }

        $tipo = trim((string) ($linea->ctav_tipo ?? ''));
        $letra = trim((string) ($linea->ctav_letra ?? ' '));
        $dh = strtoupper(trim((string) ($linea->ctav_d_h ?? '')));
        $importe = (float) ($linea->ctav_importe ?? 0);
        $fecha = (int) ($linea->ctav_fecha ?? 0);
        $sucursal = (int) ($linea->ctav_sucursal ?? 0);
        $nro = (int) ($linea->ctav_nro ?? 0);
        // En ERP histórico, subhist precarga el emisor; ctamov lo deduce de la descripción.
        $emisor = MayorPlanoCuentaEmisorSupport::resolver(
            (string) ($linea->ctav_sistema ?? ''),
            $tipo,
            (string) ($linea->erp_emisor_anita ?? ''),
            (string) ($linea->ctav_desc_mov ?? ''),
        );

        return [
            'origen' => 'ctamov',
            'sistema' => strtoupper(trim((string) ($linea->ctav_sistema ?? ''))),
            'empresa_id' => (int) ($linea->ctav_empresa ?? 0),
            'cuenta' => $cuenta,
            'centrocosto_codigo' => $this->normalizarCodigoCentrocosto($linea->ctav_ccosto ?? null),
            'fecha' => $fecha,
            'nro_asiento' => (int) ($linea->ctav_nro_asiento ?? 0),
            'nro_linea' => (int) ($linea->ctav_nro_linea ?? 0),
            'dh' => $dh,
            'importe' => $importe,
            'tipo_comp' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => $nro,
            'descripcion' => MayorPlanoCuentaSupport::resolverDescripcionMovimiento(
                $tipo,
                $sucursal,
                $nro,
                (string) ($linea->ctav_desc_mov ?? ''),
                $leyendasPago,
            ),
            'cod_mon' => (string) ($linea->ctav_cod_mon ?? '1'),
            'cotizacion' => (float) ($linea->ctav_cotizacion ?? 0),
            'tipo_asiento' => trim((string) ($linea->ctav_tipo_asiento ?? '')),
            'balancea' => trim((string) ($linea->ctav_balancea ?? 'S')) !== '' ? trim((string) ($linea->ctav_balancea ?? 'S')) : 'S',
            'nro_oc' => (int) ($linea->ctav_o_compra ?? 0),
            'emisor' => $emisor['codigo'],
            'emisor_entidad' => $emisor['entidad'],
            'emisor_deducido' => $emisor['deducido'],
            'cuit' => '',
            // Solo llegan desde el reader ERP; con Anita los resuelven los enrichers.
            'erp_asiento_id' => (int) ($linea->erp_asiento_id ?? 0),
            'erp_asiento_fks' => is_array($linea->erp_asiento_fks ?? null) ? $linea->erp_asiento_fks : null,
            'origen_erp' => (int) ($linea->erp_asiento_id ?? 0) > 0,
            'es_subdiario' => ! empty($linea->erp_origen_subdiario),
            'importe_nativo' => $importe,
            'orden_sort' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<int>  $cuentas
     * @return list<int>
     */
    private function cuentasEnRango(array $movimientos, int $cuentaDesde, int $cuentaHasta, array $cuentas = []): array
    {
        $encontradas = [];
        foreach ($movimientos as $mov) {
            $c = (int) ($mov['cuenta'] ?? 0);
            if ($c <= 0) {
                continue;
            }
            if (! $this->cuentaEnFiltro($c, $cuentaDesde, $cuentaHasta, $cuentas)) {
                continue;
            }
            $encontradas[$c] = true;
        }

        $lista = array_keys($encontradas);
        sort($lista);

        return $lista;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>|null
     */
    private function procesarCuenta(
        int $cuenta,
        array $movimientos,
        int $fechaDesde,
        int $fechaHasta,
        int $fechaSaldoDesde,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $modoInclusionAsientos,
        ?float $saldoInicialPrecalculado = null,
        ?string $centrocostoCodigoFiltro = null,
    ): ?array {
        $lineasCuenta = array_values(array_filter(
            $movimientos,
            fn (array $m) => (int) ($m['cuenta'] ?? 0) === $cuenta
                && ($centrocostoCodigoFiltro === null
                    || trim((string) ($m['centrocosto_codigo'] ?? '')) === $centrocostoCodigoFiltro),
        ));

        if ($lineasCuenta === [] && $saldoInicialPrecalculado === null) {
            return null;
        }

        $saldoEjercicioInicial = $saldoInicialPrecalculado !== null ? (float) $saldoInicialPrecalculado : 0.0;
        $lineasDetalle = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $mesActual = 0;
        $acumuladoMes = 0.0;
        $saldoEjercicio = 0.0;

        usort($lineasCuenta, function (array $a, array $b) {
            return [$a['fecha'], $a['nro_asiento'], $a['orden_sort'], $a['nro_linea']]
                <=> [$b['fecha'], $b['nro_asiento'], $b['orden_sort'], $b['nro_linea']];
        });

        // Con saldo_mes: solo suma el tramo parcial del mes (si hubo). Sin él: acumula desde fechaSaldoDesde.
        foreach ($lineasCuenta as $mov) {
            $fecha = (int) ($mov['fecha'] ?? 0);
            if ($fecha >= $fechaDesde) {
                break;
            }
            if ($fechaSaldoDesde > 0 && $fecha < $fechaSaldoDesde) {
                continue;
            }
            // Si el SI ya viene de saldos_mes y no hay tramo parcial (fechaSaldoDesde=0), no sumar movs.
            if ($saldoInicialPrecalculado !== null && $fechaSaldoDesde <= 0) {
                continue;
            }

            $delta = $this->calcularDelta($mov, $monedaConverter, $monedaReporteId);
            $saldoEjercicioInicial += $delta;
        }

        $saldoEjercicio = $saldoEjercicioInicial;

        foreach ($lineasCuenta as $mov) {
            $fecha = (int) ($mov['fecha'] ?? 0);
            if ($fecha < $fechaDesde || $fecha > $fechaHasta) {
                continue;
            }

            [$debe, $haber, $delta] = $this->calcularDebeHaberDelta($mov, $monedaConverter, $monedaReporteId);
            $mes = (int) floor($fecha / 100);
            if ($mesActual !== 0 && $mes !== $mesActual) {
                $acumuladoMes = 0.0;
            }
            $mesActual = $mes;
            $acumuladoMes += $delta;
            $saldoEjercicio += $delta;
            $totalDebe += $debe;
            $totalHaber += $haber;

            $codMonMov = trim((string) ($mov['cod_mon'] ?? '1')) !== ''
                ? trim((string) ($mov['cod_mon'] ?? '1'))
                : '1';
            $cotizMov = (float) ($mov['cotizacion'] ?? 0);
            $monedaAsientoId = $monedaConverter->monedaIdDesdeCodigoAnita($codMonMov);
            // Importe nativo del asiento (nunca el ya convertido a moneda del reporte).
            if (array_key_exists('importe_nativo', $mov)) {
                $importeNativo = (float) $mov['importe_nativo'];
            } elseif (! ($mov['importe_ya_convertido'] ?? false)) {
                $importeNativo = (float) ($mov['importe'] ?? 0);
            } else {
                $importeNativo = 0.0;
            }
            // Mon.Referencia = moneda complementaria al reporte (Debe/Haber):
            // mayor en pesos → moneda original del asiento (USD si el asiento es pesos);
            // mayor en extranjera → pesos.
            $dhMov = (string) ($mov['dh'] ?? '');
            $cotizRef = $cotizMov;
            if (MayorPlanoCuentaSupport::monReferenciaNecesitaCotizacion($monedaAsientoId, $monedaReporteId)
                && $cotizRef < 0.01) {
                $importeRefAbs = $monedaConverter->convertirImporte(
                    abs($importeNativo),
                    $codMonMov,
                    0.0,
                    $fecha,
                    MayorPlanoCuentaSupport::monedaReferenciaId($monedaReporteId),
                );
                $monRef = MayorPlanoCuentaSupport::firmarImporteDh($importeRefAbs, $dhMov);
            } else {
                $monRef = MayorPlanoCuentaSupport::importeMonedaReferencia(
                    $importeNativo,
                    $dhMov,
                    $monedaAsientoId,
                    $cotizRef,
                    $monedaReporteId,
                );
            }

            $nroAsiento = (int) ($mov['nro_asiento'] ?? 0);
            $centrocostoCodigo = trim((string) ($mov['centrocosto_codigo'] ?? ''));
            $lineasDetalle[] = [
                'tipo_fila' => 'detalle',
                'empresa_id' => (int) ($mov['empresa_id'] ?? 0),
                'nombreempresa' => $this->nombresEmpresa[(int) ($mov['empresa_id'] ?? 0)] ?? '',
                'cuenta' => $cuenta,
                'cuenta_codigo' => MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
                'cuenta_nombre' => $this->nombresCuenta[$cuenta]['nombre'] ?? MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
                'centrocosto_codigo' => $centrocostoCodigo,
                'centrocosto_nombre' => $this->nombresCentrocosto[$centrocostoCodigo] ?? '',
                'fecha' => $fecha,
                'fecha_fmt' => MayorPlanoCuentaSupport::formatearFecha($fecha),
                'nro_asiento' => $nroAsiento,
                'nro_asiento_fmt' => ($mov['es_subdiario'] ?? false) ? 'S'.$nroAsiento : (string) $nroAsiento,
                'tipo_comp' => $mov['tipo_comp'] ?? '',
                'letra' => trim((string) ($mov['letra'] ?? ' ')),
                'sucursal' => (int) ($mov['sucursal'] ?? 0),
                'nro' => (int) ($mov['nro'] ?? 0),
                'comprobante' => MayorPlanoCuentaSupport::formatearComprobante(
                    (string) ($mov['tipo_comp'] ?? ''),
                    (string) ($mov['letra'] ?? ' '),
                    (int) ($mov['sucursal'] ?? 0),
                    (int) ($mov['nro'] ?? 0),
                ),
                'emisor' => $mov['emisor'] ?? '',
                'emisor_entidad' => (string) ($mov['emisor_entidad'] ?? MayorPlanoCuentaEmisorSupport::ENTIDAD_PROVEEDOR),
                'emisor_deducido' => (bool) ($mov['emisor_deducido'] ?? false),
                'cuit' => $mov['cuit'] ?? '',
                'asiento_id' => (int) ($mov['erp_asiento_id'] ?? 0),
                'asiento_fks' => is_array($mov['erp_asiento_fks'] ?? null) ? $mov['erp_asiento_fks'] : null,
                'descripcion' => $mov['descripcion'] ?? '',
                'nro_oc' => (int) ($mov['nro_oc'] ?? 0),
                'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaAsientoId),
                'cod_mon' => $codMonMov,
                'importe_nativo' => $importeNativo,
                'cotizacion' => $cotizMov,
                'mon_referencia' => round($monRef, 2),
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'saldo_mes' => round($acumuladoMes, 2),
                'saldo_ejercicio' => round($saldoEjercicio, 2),
            ];
        }

        if ($lineasDetalle === [] && abs($saldoEjercicioInicial) < 0.005) {
            return null;
        }

        return [
            'cuenta' => $cuenta,
            'cuenta_codigo' => MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => $this->nombresCuenta[$cuenta]['nombre'] ?? MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
            'saldo_inicial' => round($saldoEjercicioInicial, 2),
            'saldo_ejercicio_inicial' => round($saldoEjercicioInicial, 2),
            'lineas' => $lineasDetalle,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'cantidad_lineas' => count($lineasDetalle),
            'centrocosto_codigo' => $centrocostoCodigoFiltro ?? '',
            'centrocosto_nombre' => $this->nombresCentrocosto[$centrocostoCodigoFiltro ?? ''] ?? '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>|null
     */
    private function procesarCuentaAgrupada(
        int $cuenta,
        array $movimientos,
        int $fechaDesde,
        int $fechaHasta,
        int $fechaSaldoDesde,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $modoInclusionAsientos,
        ?float $saldoInicialPrecalculado = null,
    ): ?array {
        $codigos = [];
        foreach ($movimientos as $mov) {
            if ((int) ($mov['cuenta'] ?? 0) !== $cuenta) {
                continue;
            }
            $codigo = trim((string) ($mov['centrocosto_codigo'] ?? ''));
            $codigos[$codigo] = $codigo;
        }

        $codigos = array_values($codigos);
        usort($codigos, static function (string $a, string $b): int {
            if ($a === '' || $b === '') {
                return $a === $b ? 0 : ($a === '' ? -1 : 1);
            }

            return strnatcasecmp($a, $b);
        });

        $grupos = [];
        $lineas = [];
        $saldoInicial = 0.0;
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $cantidadLineas = 0;

        foreach ($codigos as $codigo) {
            $grupo = $this->procesarCuenta(
                $cuenta,
                $movimientos,
                $fechaDesde,
                $fechaHasta,
                $fechaSaldoDesde,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
                $modoInclusionAsientos,
                null,
                $codigo,
            );
            if ($grupo === null) {
                continue;
            }

            $grupos[] = $grupo;
            $lineas = array_merge($lineas, $grupo['lineas'] ?? []);
            $saldoInicial += (float) ($grupo['saldo_inicial'] ?? 0);
            $totalDebe += (float) ($grupo['total_debe'] ?? 0);
            $totalHaber += (float) ($grupo['total_haber'] ?? 0);
            $cantidadLineas += (int) ($grupo['cantidad_lineas'] ?? 0);
        }

        if ($grupos === []) {
            return null;
        }

        return [
            'cuenta' => $cuenta,
            'cuenta_codigo' => MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => $this->nombresCuenta[$cuenta]['nombre'] ?? MayorPlanoCuentaSupport::formatearCodigoCuenta($cuenta),
            'saldo_inicial' => round($saldoInicial, 2),
            'saldo_ejercicio_inicial' => round($saldoInicial, 2),
            'lineas' => $lineas,
            'grupos_cc' => $grupos,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'cantidad_lineas' => $cantidadLineas,
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private function calcularDelta(array $mov, MayorConceptoMonedaConverter $monedaConverter, int $monedaReporteId): float
    {
        [, , $delta] = $this->calcularDebeHaberDelta($mov, $monedaConverter, $monedaReporteId);

        return $delta;
    }

    private function normalizarCodigoCentrocosto(mixed $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo !== '' && ctype_digit($codigo)) {
            $codigo = (string) ((int) $codigo);
        }

        return $codigo === '0' ? '' : $codigo;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array{0: float, 1: float, 2: float}
     */
    private function calcularDebeHaberDelta(array $mov, MayorConceptoMonedaConverter $monedaConverter, int $monedaReporteId): array
    {
        $fecha = (int) ($mov['fecha'] ?? 0);
        if ($mov['importe_ya_convertido'] ?? false) {
            $importeConv = (float) ($mov['importe'] ?? 0);
        } else {
            $importeConv = $monedaConverter->convertirImporte(
                (float) ($mov['importe'] ?? 0),
                (string) ($mov['cod_mon'] ?? '1'),
                (float) ($mov['cotizacion'] ?? 0),
                $fecha,
                $monedaReporteId,
            );
        }
        $dh = strtoupper(trim((string) ($mov['dh'] ?? '')));
        $debe = $dh === 'D' ? $importeConv : 0.0;
        $haber = $dh === 'H' ? $importeConv : 0.0;

        return [$debe, $haber, $debe - $haber];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoVacio(): array
    {
        return [
            'parametros' => [],
            'secciones' => [],
            'totales' => ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0, 'cuentas' => 0],
            'errores_bridge' => [],
            'stats' => ['ctamov_filas' => 0, 'subdiario_filas' => 0],
        ];
    }
}
