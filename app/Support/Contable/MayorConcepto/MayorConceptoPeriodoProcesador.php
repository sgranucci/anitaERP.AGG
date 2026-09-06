<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Genera el mayor por concepto para un período completo (motor en memoria).
 */
class MayorConceptoPeriodoProcesador
{
    /** @var array<string, list<object>> */
    private array $comSubdiarioCache = [];

    /** @var array<string, list<object>> */
    private array $aplicpedCache = [];

    /** @var array<string, list<object>> aplicped indexado por ref (PEP←COM) + proveedor */
    private array $aplicpedPorRefCache = [];

    /** @var array<string, object|null> */
    private array $promaeCache = [];

    /** @var array<string, array<string, int>> */
    private array $ordenesComPorFactura = [];

    /** @var list<string> */
    private array $erroresBridge = [];

    /** @var array<int, array{debe: float, haber: float, movimientos: int}> */
    private array $planoContrapartidasDesdeDisp = [];

    /** @var array<int, int> */
    private array $conceptoPorOcCache = [];

    /** @var array<string, int> proveedor Anita → cuenta 521060 prepaga (OSDE, Galeno…) */
    private array $cuentaPrepagaPorProveedor = [];

    /** @var array<string, list<object>> auxpag de OPs anuladas (fallback a axphist) */
    private array $auxpagHistoricoCache = [];

    /** @var array<int, list<string>> nro de asiento => por qué no se imputó por concepto */
    private array $motivosPorAsiento = [];

    private int $empresaActiva = 0;

    private readonly MayorConceptoMediopagoSupport $mediopagoSupport;

    private readonly MayorConceptoTCompSupport $tcompSupport;

    private readonly MayorConceptoComRecepcionErpSupport $comRecepcionErpSupport;

    /** @var int COM resueltos vía recepción ERP (subdiario Anita vacío) */
    private int $comSubdiarioErpFallback = 0;

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $motor,
        private readonly MayorConceptoLectorInterface $reader,
        ?MayorConceptoMediopagoSupport $mediopagoSupport = null,
        ?MayorConceptoTCompSupport $tcompSupport = null,
        ?MayorConceptoComRecepcionErpSupport $comRecepcionErpSupport = null,
    ) {
        $this->mediopagoSupport = $mediopagoSupport ?? new MayorConceptoMediopagoSupport;
        $this->tcompSupport = $tcompSupport ?? new MayorConceptoTCompSupport;
        $this->comRecepcionErpSupport = $comRecepcionErpSupport ?? new MayorConceptoComRecepcionErpSupport;
    }

    /**
     * Lectura batch multiempresa (reader). No altera el motor.
     *
     * @param  list<int>  $empresaIds
     */
    public function precargarPeriodoEmpresas(array $empresaIds, int $fechaDesde, int $fechaHasta): void
    {
        $this->reader->precargarPeriodoEmpresas($empresaIds, $fechaDesde, $fechaHasta);
    }

    /**
     * Bridge Anita del período (mes). Reutilizable por EFE / post-procesos sin re-leer.
     * Lookups puntuales (COM/aplicped/axphist) van al bridge on-demand.
     */
    public function bridgeReader(): MayorConceptoLectorInterface
    {
        return $this->reader;
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $this->resetCaches();
        $this->empresaActiva = $empresaId;

        $datos = $this->reader->cargarPeriodo($empresaId, $fechaDesde, $fechaHasta);
        $this->tcompSupport->cargar($this->erroresBridge);
        $this->motor->prepararEmpresa($empresaId, $datos['ctaconc'] ?? []);
        $this->erroresBridge = array_merge($this->erroresBridge, $datos['errores'] ?? []);

        $subdiario = $datos['subdiario'] ?? [];
        $ctamovLista = $datos['ctamov'] ?? [];
        $auxpagLista = $datos['auxpag'] ?? [];

        $auxpagPorOp = [];
        foreach ($auxpagLista as $axp) {
            $clave = $this->claveOperacionPago(
                $empresaId,
                trim((string) ($axp->axp_tipo ?? '')),
                (int) ($axp->axp_rec ?? 0),
                (int) ($axp->axp_fecha ?? 0),
            );
            $auxpagPorOp[$clave][] = $axp;
        }

        // Piernas de anulación (AOP, banco al Debe) indexadas por la referencia
        // del OP anulado (empresa+tipo+nro), para espejar la imputación de la emisión.
        $anulacionesPorOp = [];
        foreach ($subdiario as $lineaAnul) {
            if (strtoupper(trim((string) ($lineaAnul->subd_tipo ?? ''))) !== 'AOP') {
                continue;
            }
            if (strtoupper(trim((string) ($lineaAnul->subd_tipo_mov ?? ''))) !== 'D') {
                continue;
            }
            if (! $this->motor->esDisponibilidad((int) ($lineaAnul->subd_cuenta ?? 0))) {
                continue;
            }
            $claveAnul = $this->claveOperacionPagoRef(
                (int) ($lineaAnul->subd_empresa ?? $empresaId),
                trim((string) ($lineaAnul->subd_ref_tipo ?? '')),
                (int) ($lineaAnul->subd_ref_nro ?? 0),
            );
            $anulacionesPorOp[$claveAnul][] = $lineaAnul;
        }

        $statsPreload = $this->precargarCachesCompras($auxpagLista);
        $this->precargarCuentaPrepagaPorProveedor($auxpagLista);

        $subdiarioPorAsiento = $this->indexarSubdiarioPorAsiento($subdiario);
        $ctamovPorAsiento = $this->indexarCtamovPorAsiento($ctamovLista);

        $lineasReporte = [];
        $opsProcesadas = [];
        $opsEmisionProcesadaEnPeriodo = [];

        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $refTipo = trim((string) ($linea->subd_ref_tipo ?? ''));
            if (! in_array($refTipo, MayorConceptoMemoriaMotor::TIPOS_REF_IMPUTABLE, true)) {
                continue;
            }

            // La pierna de anulación (AOP) comparte claveOp con la emisión (letra
            // en blanco vs letra del OP). No debe disparar el procesamiento: se
            // resuelve por espejo sobre la emisión (espejarAnulacionOp). Dejar que
            // la AOP gane la clave perdería la imputación de gasto de la emisión.
            if (strtoupper(trim((string) ($linea->subd_tipo ?? ''))) === 'AOP') {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
                continue;
            }

            $claveOp = $this->claveOperacionPago(
                $empresaId,
                $refTipo,
                (int) ($linea->subd_ref_nro ?? 0),
                (int) ($linea->subd_fecha ?? 0),
            );

            $lineasOp = $this->filtrarSubdiarioPorRef($subdiario, $linea);
            $nroAsiento = (int) ($linea->subd_nro_operacion ?? 0);
            $fechaAsiento = (int) ($linea->subd_fecha ?? 0);
            if ($nroAsiento > 0) {
                $lineasOp = $this->mergeLineasOpSubdiarioCtamov(
                    $lineasOp,
                    [],
                    $ctamovPorAsiento[$this->claveAsientoIndex($nroAsiento, $fechaAsiento)] ?? [],
                );
                $lineasOp = $this->filtrarLineasOpPorAsiento($lineasOp, $nroAsiento);
            }

            $claveProcesamientoPago = $this->claveProcesamientoPagoOp($claveOp, $nroAsiento);
            if (isset($opsProcesadas[$claveProcesamientoPago])) {
                continue;
            }

            $auxpagOp = $this->auxpagOperacionConFallback(
                $empresaId,
                $refTipo,
                (int) ($linea->subd_ref_nro ?? 0),
                $fechaAsiento,
                $auxpagPorOp,
                $claveOp,
                $lineasOp,
            );

            if ($this->debeProcesarComoPagoProveedor($refTipo, $lineasOp, $auxpagPorOp, $claveOp)) {
                $claveRefOp = $this->claveOperacionPagoRef(
                    $empresaId,
                    $refTipo,
                    (int) ($linea->subd_ref_nro ?? 0),
                );
                $opsEmisionProcesadaEnPeriodo[$claveRefOp] = true;

                $opLineas = $this->procesarPago($empresaId, $lineasOp, $auxpagOp, $monedaConverter, $monedaReporteId);
                $opLineas = $this->espejarAnulacionOp($anulacionesPorOp[$claveRefOp] ?? [], $opLineas);
                $lineasReporte = array_merge($lineasReporte, $opLineas);
            } else {
                $lineasReporte = array_merge(
                    $lineasReporte,
                    $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen),
                );
            }

            $opsProcesadas[$claveProcesamientoPago] = true;
            $opsProcesadas[$claveOp] = true;
            if ($nroAsiento > 0) {
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fechaAsiento)] = true;
            }
        }

        $ctamovPorAsientoAgrupado = $this->agruparCtamovPorAsiento($ctamovLista);
        foreach ($ctamovPorAsientoAgrupado as $lineasAsiento) {
            $lineasReporte = array_merge(
                $lineasReporte,
                $this->procesarAsientoCtamov(
                    $empresaId,
                    $lineasAsiento,
                    $auxpagPorOp,
                    $opsProcesadas,
                    $subdiarioPorAsiento,
                    $monedaConverter,
                    $monedaReporteId,
                    $soloMonedaOrigen,
                ),
            );
        }

        // AOP del período cuya emisión quedó en otro mes: axphist (sin fecha) +
        // procesarPago + espejo solo en el asiento de anulación.
        $lineasReporte = array_merge(
            $lineasReporte,
            $this->procesarAnulacionesOpDesdeAxphist(
                $empresaId,
                $subdiario,
                $anulacionesPorOp,
                $opsEmisionProcesadaEnPeriodo,
                $ctamovPorAsiento,
                $monedaConverter,
                $monedaReporteId,
                $opsProcesadas,
            ),
        );

        $mayorPlano = $this->construirMayorPlanoDisponibilidad(
            $subdiario,
            $ctamovLista,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
        );

        $mayorPlanoAnalitico = $this->construirMayorPlanoAnalitico(
            $subdiario,
            $ctamovLista,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
        );

        $analiticoPorAsiento = $this->construirAnaliticoPorAsientoControl(
            $subdiario,
            $ctamovLista,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
        );

        $lineasReporte = $this->consolidarAnticiposMismaOperacion(
            $this->reimputarCuentasAnticipoCompras($lineasReporte),
        );

        $lineasReporte = array_merge(
            $lineasReporte,
            $this->completarRemanenteMayorPlano(
                $empresaId,
                $lineasReporte,
                $mayorPlano,
                $monedaConverter,
                $monedaReporteId,
            ),
        );

        return [
            'parametros' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'moneda_reporte_id' => $monedaReporteId,
                'moneda_abreviatura' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'solo_moneda_origen' => $soloMonedaOrigen,
            ],
            'secciones' => $this->agruparPorConcepto($lineasReporte),
            'totales' => [
                'lineas' => count($lineasReporte),
                'debe' => round(array_sum(array_column($lineasReporte, 'debe')), 2),
                'haber' => round(array_sum(array_column($lineasReporte, 'haber')), 2),
            ],
            'errores_bridge' => $this->erroresBridge,
            'lectura_incompleta' => $this->reader->fallosLectura() > 0,
            'stats' => array_merge([
                'subdiario_filas' => count($subdiario),
                'ctamov_filas' => count($ctamovLista),
                'auxpag_filas' => count($auxpagLista),
                'operaciones_procesadas' => count($opsProcesadas),
            ], $statsPreload),
            'mayor_plano_disponibilidad' => $mayorPlano,
            'mayor_plano_analitico' => $mayorPlanoAnalitico,
            'analitico_por_asiento' => $analiticoPorAsiento,
            'motivos_por_asiento' => $this->motivosPorAsiento,
            'mayor_plano_contrapartidas_disponibilidad' => $this->finalizarPlanoContrapartidasDesdeDisp(),
        ];
    }

    private function resetCaches(): void
    {
        $this->comSubdiarioCache = [];
        $this->aplicpedCache = [];
        $this->aplicpedPorRefCache = [];
        $this->ordenesComPorFactura = [];
        $this->promaeCache = [];
        $this->erroresBridge = [];
        $this->planoContrapartidasDesdeDisp = [];
        $this->consultasBridgeIndividuales = 0;
        $this->conceptoPorOcCache = [];
        $this->cuentaPrepagaPorProveedor = [];
        $this->auxpagHistoricoCache = [];
        $this->comSubdiarioErpFallback = 0;
        $this->motivosPorAsiento = [];
    }

    /**
     * Deja registrado por qué una operación no generó línea de concepto. Sin esto la
     * conciliación solo puede decir "solo en analítico" y hay que rastrear a mano.
     */
    private function registrarMotivoAsiento(int $nroAsiento, string $motivo): void
    {
        if ($nroAsiento <= 0 || $motivo === '') {
            return;
        }

        $actuales = $this->motivosPorAsiento[$nroAsiento] ?? [];
        if (! in_array($motivo, $actuales, true)) {
            $actuales[] = $motivo;
            $this->motivosPorAsiento[$nroAsiento] = $actuales;
        }
    }

    /** Comprobante aplicado en formato Anita: FIBC-0001-105. */
    private function etiquetaAplicacion(object $aplicacion): string
    {
        $tipo = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letra = trim((string) ($aplicacion->axp_letra_comp ?? ''));

        return sprintf(
            '%s%s-%04d-%d',
            $tipo !== '' ? $tipo : '?',
            $letra !== '' ? $letra : ' ',
            (int) ($aplicacion->axp_sucursal ?? 0),
            (int) ($aplicacion->axp_nro ?? 0),
        );
    }

    /**
     * Aplicaciones de pago del OP: usa `auxpag` vigente y, si no hay ninguna
     * (OP anulado), cae a `axphist`. Solo se aplica a tipos de pago a proveedor
     * (OPP/OPA/OPV); el resultado se cachea por clave de operación.
     *
     * @param  array<string, list<object>>  $auxpagPorOp
     * @return list<object>
     */
    private function auxpagOperacionConFallback(
        int $empresaId,
        string $refTipo,
        int $refNro,
        int $fecha,
        array $auxpagPorOp,
        string $claveOp,
        array $lineasOp = [],
    ): array {
        $vigente = $auxpagPorOp[$claveOp] ?? [];
        if ($vigente !== []) {
            return $vigente;
        }

        if (! in_array($refTipo, ['OPP', 'OPA', 'OPV'], true) || $refNro <= 0) {
            return [];
        }

        $contexto = $this->contextoOpDesdeLineas($lineasOp);
        $cacheKey = $claveOp.'|'.$contexto['proveedor'].'|'.$contexto['sucursal'];

        if (! array_key_exists($cacheKey, $this->auxpagHistoricoCache)) {
            $this->consultasBridgeIndividuales++;
            $this->auxpagHistoricoCache[$cacheKey] = $this->reader->cargarAuxpagHistorico(
                $empresaId,
                $refTipo,
                $refNro,
                $fecha,
                $contexto['proveedor'],
                $contexto['sucursal'],
                $this->erroresBridge,
            );
        }

        return $this->auxpagHistoricoCache[$cacheKey];
    }

    /**
     * axphist del OP anulado sin acotar por fecha (emisión en otro mes/período).
     *
     * @param  list<object>  $lineasOp
     * @return list<object>
     */
    private function auxpagHistoricoOperacion(
        int $empresaId,
        string $refTipo,
        int $refNro,
        array $lineasOp,
    ): array {
        if (! in_array($refTipo, ['OPP', 'OPA', 'OPV'], true) || $refNro <= 0) {
            return [];
        }

        $contexto = $this->contextoOpDesdeLineas($lineasOp);
        $cacheKey = 'HIST|'.$empresaId.'|'.$refTipo.'|'.$refNro
            .'|'.$contexto['proveedor'].'|'.$contexto['sucursal'];

        if (! array_key_exists($cacheKey, $this->auxpagHistoricoCache)) {
            $this->consultasBridgeIndividuales++;
            $this->auxpagHistoricoCache[$cacheKey] = $this->reader->cargarAuxpagHistorico(
                $empresaId,
                $refTipo,
                $refNro,
                0,
                $contexto['proveedor'],
                $contexto['sucursal'],
                $this->erroresBridge,
            );
        }

        return $this->auxpagHistoricoCache[$cacheKey];
    }

    /**
     * Proveedor y sucursal del OP desde el subdiario del asiento (para acotar axphist).
     *
     * @param  list<object>  $lineasOp
     * @return array{proveedor: string, sucursal: int}
     */
    private function contextoOpDesdeLineas(array $lineasOp): array
    {
        $proveedor = '';
        $sucursal = 0;

        foreach ($lineasOp as $linea) {
            $prov = trim((string) ($linea->subd_emisor ?? ''));
            if ($prov !== '') {
                $proveedor = $prov;
            }

            $suc = (int) ($linea->subd_ref_sucursal ?? $linea->subd_sucursal ?? 0);
            if ($suc > 0) {
                $sucursal = $suc;
            }
        }

        return ['proveedor' => $proveedor, 'sucursal' => $sucursal];
    }

    private int $consultasBridgeIndividuales = 0;

    /**
     * Precarga aplicped, promae y subdiario COM vía lecturas masivas del bridge.
     *
     * @param  list<object>  $auxpagLista
     * @return array<string, int>
     */
    private function precargarCachesCompras(array $auxpagLista): array
    {
        $seeds = [];
        $proveedores = [];

        foreach ($auxpagLista as $axp) {
            if (! $this->esFactura($axp)) {
                continue;
            }

            $prov = trim((string) ($axp->axp_pro ?? ''));
            $tipoAp = trim((string) ($axp->axp_tipo_ap ?? ''));
            $letraAp = trim((string) ($axp->axp_letra_comp ?? ' '));
            $sucAp = (int) ($axp->axp_sucursal ?? 0);
            $nroAp = (int) ($axp->axp_nro ?? 0);
            $nroInterno = (int) ($axp->axp_nro_interno ?? 0);

            if ($prov === '' || $tipoAp === '' || ($nroAp <= 0 && $nroInterno <= 0)) {
                continue;
            }

            $clave = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp.'|'.$nroInterno;
            $seeds[$clave] = [$prov, $tipoAp, $letraAp, $sucAp, $nroAp, $nroInterno];
            $proveedores[$prov] = true;
        }

        if ($seeds === []) {
            return [
                'aplicped_precargadas' => 0,
                'promae_precargados' => 0,
                'com_subdiario_precargados' => 0,
                'bridge_consultas_individuales' => 0,
            ];
        }

        $visitados = [];
        $pendientes = array_values($seeds);

        while ($pendientes !== []) {
            $lote = [];
            while ($pendientes !== [] && count($lote) < 30) {
                $doc = array_shift($pendientes);
                $claveDoc = $doc[0].'|'.$doc[1].'|'.$doc[2].'|'.$doc[3].'|'.$doc[4];
                if (isset($visitados[$claveDoc])) {
                    continue;
                }
                $visitados[$claveDoc] = true;
                $lote[] = $doc;
            }

            if ($lote === []) {
                break;
            }

            foreach ($this->reader->cargarAplicpedPorFacturas($lote, $this->erroresBridge) as $apl) {
                $clave = $this->claveDocumentoCompras(
                    trim((string) ($apl->aplp_proveedor ?? '')),
                    trim((string) ($apl->aplp_tipo ?? '')),
                    trim((string) ($apl->aplp_letra ?? ' ')),
                    (int) ($apl->aplp_sucursal ?? 0),
                    (int) ($apl->aplp_nro ?? 0),
                );
                if ($clave === '') {
                    continue;
                }
                $this->aplicpedCache[$clave][] = $apl;

                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);
                if ($refTipo === '' || $refNro <= 0 || $refTipo === 'COM') {
                    continue;
                }

                $prov = trim((string) ($apl->aplp_proveedor ?? ''));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $claveRef = $prov.'|'.$refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro;
                if (! isset($visitados[$claveRef])) {
                    $pendientes[] = [$prov, $refTipo, $refLetra, $refSuc, $refNro, 0];
                }
            }
        }

        foreach ($this->reader->cargarPromaePorProveedores(array_keys($proveedores), $this->erroresBridge) as $prom) {
            $prov = trim((string) ($prom->prom_proveedor ?? ''));
            if ($prov !== '') {
                $this->promaeCache[$prov] = $prom;
            }
        }

        $clavesCom = [];
        foreach ($this->aplicpedCache as $docs) {
            foreach ($docs as $apl) {
                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                if ($refTipo !== 'COM') {
                    continue;
                }
                $claveCom = $refTipo.'|'
                    .trim((string) ($apl->aplp_ref_letra ?? ' ')).'|'
                    .(int) ($apl->aplp_ref_sucursal ?? 0).'|'
                    .(int) ($apl->aplp_ref_nro ?? 0);
                $clavesCom[$claveCom] = $claveCom;
            }
        }

        $faltantes = array_values(array_filter(
            $clavesCom,
            fn ($c) => ! isset($this->comSubdiarioCache[$c]),
        ));

        if ($faltantes !== []) {
            foreach ($this->reader->cargarComSubdiarioLote($this->empresaActiva, $faltantes, $this->erroresBridge) as $clave => $lineas) {
                $this->comSubdiarioCache[$clave] = $lineas;
            }
        }

        $this->completarComSubdiarioDesdeRecepcionErp(array_values($clavesCom));

        foreach ($auxpagLista as $axp) {
            if (! $this->esFactura($axp)) {
                continue;
            }

            $tipoAp = trim((string) ($axp->axp_tipo_ap ?? ''));
            if (strtoupper($tipoAp) !== 'FIS') {
                continue;
            }

            $prov = trim((string) ($axp->axp_pro ?? ''));
            $letraAp = trim((string) ($axp->axp_letra_comp ?? ' '));
            $sucAp = (int) ($axp->axp_sucursal ?? 0);
            $nroAp = (int) ($axp->axp_nro ?? 0);
            $nroInterno = (int) ($axp->axp_nro_interno ?? 0);

            $claveFac = $this->claveDocumentoCompras($prov, $tipoAp, $letraAp, $sucAp, $nroAp);
            $tieneCom = false;
            foreach ($this->aplicpedCache[$claveFac] ?? [] as $apl) {
                if (trim((string) ($apl->aplp_ref_tipo ?? '')) === 'COM') {
                    $tieneCom = true;
                    break;
                }
            }

            if ($tieneCom) {
                continue;
            }

            $cacheKey = $this->claveCacheSubdiarioFactura($tipoAp, $letraAp, $sucAp, $nroAp, $nroInterno);
            if (isset($this->comSubdiarioCache[$cacheKey])) {
                continue;
            }

            $this->consultasBridgeIndividuales++;
            $this->comSubdiarioCache[$cacheKey] = $this->reader->cargarSubdiarioFacturaCompras(
                $this->empresaActiva,
                $tipoAp,
                $letraAp,
                $sucAp,
                $nroAp,
                $nroInterno,
                $prov,
                $this->erroresBridge,
            );
        }

        return [
            'aplicped_precargadas' => array_sum(array_map('count', $this->aplicpedCache)),
            'promae_precargados' => count($this->promaeCache),
            'com_subdiario_precargados' => count($this->comSubdiarioCache),
            'com_subdiario_erp_fallback' => $this->comSubdiarioErpFallback,
            'bridge_consultas_individuales' => $this->consultasBridgeIndividuales,
        ];
    }

    private function claveDocumentoCompras(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): string {
        if ($proveedor === '' || $tipo === '' || $nro <= 0) {
            return '';
        }

        return $proveedor.'|'.$tipo.'|'.$letra.'|'.$sucursal.'|'.$nro;
    }

    /**
     * @param  list<object>  $lineasOp
     * @param  list<object>  $auxpag
     * @return list<array<string, mixed>>
     */
    private function procesarPago(
        int $empresaId,
        array $lineasOp,
        array $auxpag,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $lineas = [];
        $lineaRef = $lineasOp[0] ?? null;
        [$lineaBanco, $totalBancoHaber] = $this->resolverLineasBanco($lineasOp);

        $facturas = $this->filtrarAplicacionesFactura($auxpag, $totalBancoHaber);
        $retenciones = array_values(array_filter($auxpag, fn ($f) => $this->esRetencion($f)));
        $cheques = $this->filtrarAplicacionesCheque($auxpag);
        $mediosBancarios = $this->filtrarAplicacionesMedioPagoBancario($auxpag);
        $totalFacturas = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $facturas));
        $totalCheques = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $cheques));
        $totalMediosBancarios = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $mediosBancarios));

        if ($totalFacturas <= 0 && $totalCheques <= 0 && $totalMediosBancarios <= 0) {
            $lineas = $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, false);

            if ($this->asientoTieneProveedor($lineasOp)) {
                return array_merge(
                    $lineas,
                    $this->imputarTraspasosDisponibilidadOp(
                        $empresaId,
                        $lineasOp,
                        $lineas,
                        $monedaConverter,
                        $monedaReporteId,
                    ),
                );
            }

            return $lineas;
        }

        $cuentaBanco = $this->resolverCuentaDisponibilidad($lineaBanco ?? $lineaRef);
        if ($cuentaBanco <= 0) {
            return $lineas;
        }

        // Solo meta de visualización: nro de CHP de la OP (no axp_banco de la factura).
        $nroChequeOp = $this->numeroChequeDesdeAuxpag($auxpag);

        $imputoGastoDesdeFacturas = false;

        if ($this->debeImputarChequeProveedor($totalMediosBancarios, $totalFacturas, $totalBancoHaber, $auxpag)) {
            foreach ($mediosBancarios as $cheque) {
                $monto = (float) ($cheque->axp_monto_ap ?? 0);
                if ($monto <= 0) {
                    continue;
                }

                $cuentaCheque = $this->cuentaChequeMayorConcepto($cheque, $auxpag, $empresaId, $lineasOp);
                $tipoMedio = strtoupper(trim((string) ($cheque->axp_tipo_ap ?? 'CHP')));
                if ($tipoMedio === '') {
                    $tipoMedio = 'CHP';
                }

                $lineas[] = $this->lineaReporte(
                    $lineaBanco ?? $lineaRef,
                    $cuentaCheque,
                    $this->conceptoImputacionGasto($empresaId, $cuentaCheque, $tipoMedio),
                    $monto,
                    'D',
                    $monedaConverter,
                    $monedaReporteId,
                    'OPP medio '.$tipoMedio,
                    $this->metaPagoProveedor($empresaId, $cheque, null, $cuentaBanco),
                );
            }
        } elseif ($facturas !== []) {
            $imputoGastoDesdeFacturas = true;
            $totalBancoOp = $this->totalBancoEfectivoOp($auxpag, $lineasOp);
            $indicePrimeraLineaFactura = count($lineas);

            $pesosDocumentales = [];
            $totalPesoDocumental = 0.0;
            foreach ($facturas as $aplicacion) {
                $prov = trim((string) ($aplicacion->axp_pro ?? ''));
                $peso = $this->pesoDocumentalFactura($aplicacion, $this->proveedorInscripto($prov));
                $clavePeso = $this->claveAplicacionPago($aplicacion);
                $pesosDocumentales[$clavePeso] = $peso;
                $totalPesoDocumental += $peso;
            }

            foreach ($facturas as $aplicacion) {
                $gastoAgrupadoFactura = [];
                $montoFactura = (float) ($aplicacion->axp_monto_ap ?? 0);
                if ($montoFactura <= 0) {
                    continue;
                }

                $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
                $inscripto = $this->proveedorInscripto(trim((string) ($aplicacion->axp_pro ?? '')));
                $pesoDoc = $pesosDocumentales[$this->claveAplicacionPago($aplicacion)] ?? 0.0;
                $montoBanco = $totalBancoHaber > 0 && $totalPesoDocumental > 0
                    ? $totalBancoHaber * ($pesoDoc / $totalPesoDocumental)
                    : ($totalBancoHaber > 0 ? $totalBancoHaber / max(count($facturas), 1) : $montoFactura);

                $lineasGasto = $this->cargarGastoDesdeAplicacion($aplicacion);
                $tieneComGasto = $this->aplicacionTieneComGasto($aplicacion);
                $anticipo114040 = $this->subTieneAnticipo114040($lineasGasto);
                if ($anticipo114040) {
                    $lineasGasto = $this->filtrarLineasAnticipoProveedor($lineasGasto);
                }

                $totalNeto = $anticipo114040
                    ? array_sum(array_map(
                        fn ($l) => (float) ($l->subd_importe ?? 0),
                        array_values(array_filter(
                            $lineasGasto,
                            fn ($l) => ($c = (int) ($l->subd_cuenta ?? 0)) >= 114040000 && $c < 114050000,
                        )),
                    ))
                    : array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $lineasGasto));
                if ($totalNeto <= 0) {
                    $this->registrarMotivoAsiento(
                        (int) (($lineaBanco ?? $lineaRef)->subd_nro_operacion ?? 0),
                        'Factura '.$this->etiquetaAplicacion($aplicacion).' sin líneas COM de gasto: '
                            .'no hay cuenta de gasto para imputar el pago.',
                    );

                    continue;
                }

                $nroOc = $this->ordenComDesdeAplicacion($aplicacion);
                $fisAdelantado = $tipoAp === 'FIS' && ! $tieneComGasto;
                $gastoAdelantado = ! $tieneComGasto;
                // FIS sin COM con gasto 521/115: imputar subdiario de la factura (no 114020-009).
                // FIS sin COM solo 114xxx: servicios → 114020-009.
                $fisServicios = $fisAdelantado && ! $anticipo114040
                    && ! $this->lineasGastoIncluyenResultadoCompras($lineasGasto);
                $comSubEfectivo = $tieneComGasto
                    ? $this->cargarComDesdeFacturaEfectivo($aplicacion)
                    : [];
                $percepcionesRaw = $tieneComGasto
                    ? $this->percepcionesRawDesdeLineasSubdiario($comSubEfectivo)
                    : $this->percepcionesRawDesdeAplicacion($aplicacion);
                $totalPercepcionesRaw = array_sum($percepcionesRaw);
                $ivaCreditoRaw = $inscripto
                    ? ($tieneComGasto
                        ? $this->ivaCreditoRawDesdeLineasSubdiario($comSubEfectivo)
                        : $this->ivaCreditoRawDesdeAplicacion($aplicacion))
                    : [];
                // COM histórica suele traer solo el neto: el IVA queda en el subdiario de la factura.
                if ($inscripto && $ivaCreditoRaw === []) {
                    $ivaCreditoRaw = $this->ivaCreditoRawDesdeAplicacion($aplicacion);
                }
                $totalIvaCreditoRaw = array_sum($ivaCreditoRaw);
                // Base documental del comprobante (COM + percepciones + IVA): prorratea el
                // pago efectivo (cheque/banco), no el importe aplicado en auxpag.
                $baseComprobante = max($totalNeto + $totalPercepcionesRaw + $totalIvaCreditoRaw, 1.0);
                $montoImputable = $montoBanco;
                if ($pesoDoc > 0 && $totalBancoOp < $pesoDoc * 0.995) {
                    $coeficientePago = $this->coeficientePagoSobreFactura(
                        $aplicacion,
                        $pesoDoc,
                        $montoBanco,
                        $totalBancoOp,
                    );
                    $montoImputable = min($montoBanco, round($pesoDoc * $coeficientePago, 2));
                }
                $percepcionesFactura = [];
                if ($montoImputable > 0 && $baseComprobante > 0) {
                    foreach ($percepcionesRaw as $cuentaPercepcion => $importeRawPercepcion) {
                        if ($importeRawPercepcion <= 0) {
                            continue;
                        }
                        $percepcionesFactura[] = [
                            'cuenta' => (int) $cuentaPercepcion,
                            'importe' => round($montoImputable * ($importeRawPercepcion / $baseComprobante), 2),
                        ];
                    }
                }
                $totalPercepciones = array_sum(array_map(fn ($p) => (float) ($p['importe'] ?? 0), $percepcionesFactura));

                if ($this->comGastoEsSolo117010($lineasGasto)) {
                    // Misma proporción que un COM de gasto: no absorber IVA/resto en 117010.
                    // Concepto: 117010 suele ser 0 → reclasificar con axp_concepto / gasto FGA.
                    $netoCom = array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $lineasGasto));
                    $importe117 = $baseComprobante > 0
                        ? round($montoImputable * ($netoCom / $baseComprobante), 2)
                        : round($montoImputable, 2);
                    $cuentaCheque = 117010001;
                    $concepto117 = $this->conceptoReclasificacionCuentaTransitoria(
                        $empresaId,
                        $aplicacion,
                        $cuentaCheque,
                    );
                    $claveAgrup = $cuentaCheque.'|'.$concepto117;
                    $gastoAgrupadoFactura[$claveAgrup] = [
                        'cuenta' => $cuentaCheque,
                        'concepto' => $concepto117,
                        'importe' => $importe117,
                        'origen_log' => $concepto117 > 0
                            ? 'COM cheque 117010 reclasificado'
                            : 'COM cheque 117010',
                        'aplicacion' => $aplicacion,
                        'linea_gasto' => null,
                    ];
                } else {
                    foreach ($lineasGasto as $lineaGasto) {
                        $netoLinea = (float) ($lineaGasto->subd_importe ?? 0);
                        if ($netoLinea <= 0) {
                            continue;
                        }

                        $cuentaOrigenGasto = (int) ($lineaGasto->subd_cuenta ?? 0);
                        if ($anticipo114040 && $cuentaOrigenGasto >= 114010000 && $cuentaOrigenGasto < 114020000) {
                            continue;
                        }

                        $cuentaGasto = $this->cuentaVisibleDesdeDocumentoGasto(
                            $lineaGasto,
                            $tipoAp,
                            $anticipo114040,
                            $fisServicios,
                        );
                        if ($cuentaGasto <= 0) {
                            continue;
                        }

                        $conceptoGasto = $this->conceptoImputacionGasto(
                            $empresaId,
                            $cuentaGasto,
                            $tipoAp,
                            $nroOc,
                            trim((string) ($aplicacion->axp_pro ?? '')),
                            $aplicacion,
                        );

                        if ($anticipo114040) {
                            $netoImp = $totalNeto > 0
                                ? round($montoImputable * ($netoLinea / $totalNeto), 2)
                                : $montoImputable;
                            $ivaImp = 0.0;
                            if ($tipoAp === 'FNB' && $cuentaGasto >= 114040000 && $cuentaGasto < 114050000) {
                                $netoImp = round(max(0.0, $montoImputable - $totalPercepciones), 2);
                            }
                        } elseif ($tipoAp === 'FNB' && $cuentaGasto >= 114040000 && $cuentaGasto < 114050000) {
                            $netoImp = $montoImputable;
                            $ivaImp = 0.0;
                        } elseif ($fisServicios) {
                            $netoImp = $baseComprobante > 0
                                ? round($montoImputable * ($netoLinea / $baseComprobante), 2)
                                : $montoImputable;
                            $ivaImp = 0.0;
                        } elseif ($gastoAdelantado) {
                            $netoImp = $baseComprobante > 0
                                ? round($montoImputable * ($netoLinea / $baseComprobante), 2)
                                : $montoImputable;
                            $ivaImp = 0.0;
                        } elseif ($tipoAp === 'FIS') {
                            // Prorratear siempre sobre la base documental (neto + IVA + percepciones):
                            // con COM el IVA de la factura sale en su propia línea (114010/521130), así
                            // el gasto no lo absorbe y no queda duplicado. En factura C (sin IVA) la base
                            // es igual al neto, por lo que el gasto se lleva el pago completo sin línea IVA.
                            $netoImp = $baseComprobante > 0
                                ? round($montoImputable * ($netoLinea / $baseComprobante), 2)
                                : ($fisAdelantado ? $netoLinea : $montoImputable);
                            $ivaImp = 0.0;
                        } elseif ($tipoAp === 'FGA') {
                            $netoImp = $baseComprobante > 0
                                ? round($montoImputable * ($netoLinea / $baseComprobante), 2)
                                : $montoImputable;
                            $ivaImp = 0.0;
                        } else {
                            $netoImp = $baseComprobante > 0
                                ? round($montoImputable * ($netoLinea / $baseComprobante), 2)
                                : $montoImputable;
                            $ivaImp = 0.0;
                        }

                        $origenGasto = match (true) {
                            $anticipo114040 => 'Anticipo 114040',
                            $tipoAp === 'FGA' => 'FGA COM neto',
                            $fisServicios => 'FIS gasto adelantado',
                            $tipoAp === 'FIS' && ! $tieneComGasto
                                && $this->lineasGastoIncluyenResultadoCompras([$lineaGasto]) => 'FIS directa',
                            $gastoAdelantado => 'Factura adelantada',
                            $tipoAp === 'FIS' && $tieneComGasto => 'FIS COM neto',
                            $tipoAp === 'FIS' => 'FIS directa',
                            $inscripto => 'COM+IVA',
                            default => 'COM neto',
                        };

                        $cuentaOrigen = (int) ($lineaGasto->subd_cuenta ?? 0);
                        $claveAgrup = $this->claveAgrupacionGastoFactura(
                            $cuentaGasto,
                            $conceptoGasto,
                            $tipoAp,
                            $fisServicios,
                            $gastoAdelantado && ! $anticipo114040,
                            round($netoImp + $ivaImp, 2),
                            $anticipo114040,
                            $cuentaOrigen,
                        );
                        if (! isset($gastoAgrupadoFactura[$claveAgrup])) {
                            $gastoAgrupadoFactura[$claveAgrup] = [
                                'cuenta' => $cuentaGasto,
                                'concepto' => $conceptoGasto,
                                'importe' => 0.0,
                                'origen_log' => $origenGasto,
                                'aplicacion' => $aplicacion,
                                'linea_gasto' => $lineaGasto,
                            ];
                        }

                        $gastoAgrupadoFactura[$claveAgrup]['importe'] += round($netoImp + $ivaImp, 2);
                        if ($anticipo114040) {
                            $gastoAgrupadoFactura[$claveAgrup]['anticipo_prefijo_origen'] = $cuentaOrigen >= 114040000 ? '114040' : '114010';
                        }
                    }
                }

                foreach ($percepcionesFactura as $percepcion) {
                    $cuentaRet = (int) ($percepcion['cuenta'] ?? 0);
                    $importeRet = (float) ($percepcion['importe'] ?? 0);
                    if ($cuentaRet <= 0 || $importeRet <= 0) {
                        continue;
                    }

                    $claveRet = $cuentaRet.'|'.$this->motor->conceptoImputacionCuenta($empresaId, $cuentaRet).'|D';
                    if (! isset($gastoAgrupadoFactura[$claveRet])) {
                        $gastoAgrupadoFactura[$claveRet] = [
                            'cuenta' => $cuentaRet,
                            'concepto' => $this->motor->conceptoImputacionCuenta($empresaId, $cuentaRet),
                            'importe' => 0.0,
                            'origen_log' => 'Percepción factura',
                            'aplicacion' => $aplicacion,
                            'linea_gasto' => null,
                            'dh' => 'D',
                        ];
                    }

                    $gastoAgrupadoFactura[$claveRet]['importe'] += $importeRet;
                }

                // Anticipo 114040: el desglose mayor concepto imputa solo neto anticipo al banco
                // (l-mayorconc); el IVA ya está en la base documental del coeficiente pero no
                // genera líneas separadas en 114040.
                if ($inscripto && ! $anticipo114040) {
                    foreach ($ivaCreditoRaw as $cuentaIva => $importeIvaRaw) {
                        if ($importeIvaRaw <= 0) {
                            continue;
                        }
                        $cuentaIvaVisible = $this->cuentaVisibleDesdeDocumentoGasto(
                            (object) ['subd_cuenta' => $cuentaIva],
                            $tipoAp,
                            $anticipo114040,
                            $fisServicios,
                        );
                        if ($cuentaIvaVisible <= 0) {
                            continue;
                        }
                        $importeIva = round($montoImputable * ($importeIvaRaw / $baseComprobante), 2);
                        if ($importeIva <= 0) {
                            continue;
                        }
                        $conceptoIva = $this->conceptoImputacionGasto(
                            $empresaId,
                            $cuentaIvaVisible,
                            $tipoAp,
                            $nroOc,
                            trim((string) ($aplicacion->axp_pro ?? '')),
                            $aplicacion,
                        );
                        $claveIva = $cuentaIvaVisible.'|'.$conceptoIva.'|iva';
                        if (! isset($gastoAgrupadoFactura[$claveIva])) {
                            $gastoAgrupadoFactura[$claveIva] = [
                                'cuenta' => $cuentaIvaVisible,
                                'concepto' => $conceptoIva,
                                'importe' => 0.0,
                                'origen_log' => 'IVA crédito fiscal',
                                'aplicacion' => $aplicacion,
                                'linea_gasto' => null,
                            ];
                        }
                        $gastoAgrupadoFactura[$claveIva]['importe'] += $importeIva;
                    }

                    // Criterio OP: el IVA queda fusionado en los gastos de SU propia
                    // factura (no en otra aplicación del mismo pago). Se reparte entre
                    // las líneas de gasto en proporción a su neto; contaduría no carga
                    // todo el IVA en la cuenta de mayor importe.
                    foreach ($gastoAgrupadoFactura as $claveIvaFusion => $entradaIva) {
                        if (! str_ends_with((string) $claveIvaFusion, '|iva')) {
                            continue;
                        }

                        $importeIvaFusion = round((float) ($entradaIva['importe'] ?? 0), 2);
                        if ($importeIvaFusion <= 0) {
                            unset($gastoAgrupadoFactura[$claveIvaFusion]);

                            continue;
                        }

                        $clavesGasto = [];
                        $totalNetoGasto = 0.0;
                        foreach ($gastoAgrupadoFactura as $claveGasto => $entradaGasto) {
                            if (str_ends_with((string) $claveGasto, '|iva')
                                || ($entradaGasto['origen_log'] ?? '') === 'Percepción factura') {
                                continue;
                            }
                            $netoGasto = round((float) ($entradaGasto['importe'] ?? 0), 2);
                            if ($netoGasto <= 0) {
                                continue;
                            }
                            $clavesGasto[] = $claveGasto;
                            $totalNetoGasto += $netoGasto;
                        }

                        if ($clavesGasto === [] || $totalNetoGasto <= 0) {
                            continue;
                        }

                        $acumuladoIva = 0.0;
                        $ultimo = count($clavesGasto) - 1;
                        foreach ($clavesGasto as $indiceGasto => $claveGasto) {
                            if ($indiceGasto === $ultimo) {
                                $porcionIva = round($importeIvaFusion - $acumuladoIva, 2);
                            } else {
                                $peso = (float) ($gastoAgrupadoFactura[$claveGasto]['importe'] ?? 0);
                                $porcionIva = round($importeIvaFusion * ($peso / $totalNetoGasto), 2);
                                $acumuladoIva += $porcionIva;
                            }
                            $gastoAgrupadoFactura[$claveGasto]['importe'] = round(
                                (float) ($gastoAgrupadoFactura[$claveGasto]['importe'] ?? 0) + $porcionIva,
                                2,
                            );
                        }
                        unset($gastoAgrupadoFactura[$claveIvaFusion]);
                    }
                }

                foreach ($gastoAgrupadoFactura as $gasto) {
                    $importe = round((float) ($gasto['importe'] ?? 0), 2);
                    if ($importe <= 0) {
                        continue;
                    }

                    $dh = (string) ($gasto['dh'] ?? 'D');
                    $metaLinea = $this->metaPagoProveedor(
                        $empresaId,
                        $gasto['aplicacion'],
                        $gasto['linea_gasto'] ?? null,
                        $cuentaBanco,
                        $nroChequeOp,
                    );
                    if (! empty($gasto['anticipo_prefijo_origen'])) {
                        $metaLinea['anticipo_prefijo_origen'] = (string) $gasto['anticipo_prefijo_origen'];
                    }

                    $lineas[] = $this->lineaReporte(
                        $lineaBanco ?? $lineaRef,
                        (int) $gasto['cuenta'],
                        (int) $gasto['concepto'],
                        $importe,
                        $dh,
                        $monedaConverter,
                        $monedaReporteId,
                        (string) ($gasto['origen_log'] ?? 'COM neto'),
                        $metaLinea,
                    );
                }
            }

            $lineas = $this->ajustarPercepcionesFacturaOp($lineas, $indicePrimeraLineaFactura, $totalBancoHaber);
        }

        // Retenciones (RTP/RGP/…) no se suman al desglose del cheque: el mayor por
        // concepto reparte solo el pago de banco (TMB/CHP) contra COM/gasto. Las
        // retenciones van al Haber del subdiario pero no imputan en este reporte
        // cuando el OP ya se prorrateó por facturas.
        if (! $imputoGastoDesdeFacturas) {
            // Si el gasto/cheque ya imputó el total pagado por banco, las retenciones
            // fueron RETENIDAS en el pago (no salieron por banco). Se siguen mostrando
            // en su concepto (61 RETENCIONES DEPOSITADAS) pero se netean con un
            // movimiento espejo para que el total del asiento en el mayor por concepto
            // siga igual al movimiento de la cuenta de banco.
            $netoGastoOp = 0.0;
            foreach ($lineas as $lineaGasto) {
                $netoGastoOp += (float) ($lineaGasto['debe'] ?? 0) - (float) ($lineaGasto['haber'] ?? 0);
            }
            $bancoCubiertoPorGasto = $totalBancoHaber > 0
                && abs(round($netoGastoOp - $totalBancoHaber, 2)) < 0.05;

            foreach ($retenciones as $retencion) {
                $monto = (float) ($retencion->axp_monto_ap ?? 0);
                if ($monto <= 0) {
                    continue;
                }

                $cuentaRet = $this->cuentaRetencion($lineasOp, $retencion);
                $lineaOrigenRet = $this->buscarLineaRetencionSubdiario($lineasOp, $cuentaRet, $monto)
                    ?? $lineaBanco
                    ?? $lineaRef;
                if ($cuentaRet <= 0 || $lineaOrigenRet === null) {
                    continue;
                }

                $dhRet = $this->dhImputacionRetencion($lineasOp, $cuentaRet, $monto);
                $conceptoRet = $this->motor->conceptoImputacionCuenta($empresaId, $cuentaRet);

                $lineas[] = $this->lineaReporte(
                    $lineaOrigenRet,
                    $cuentaRet,
                    $conceptoRet,
                    $monto,
                    $dhRet,
                    $monedaConverter,
                    $monedaReporteId,
                    'Retención '.($retencion->axp_tipo_ap ?? ''),
                    $this->metaRetencionPago($empresaId, $retencion),
                );

                if ($bancoCubiertoPorGasto) {
                    $lineas[] = $this->lineaReporte(
                        $lineaOrigenRet,
                        $cuentaRet,
                        $conceptoRet,
                        $monto,
                        $dhRet === 'D' ? 'H' : 'D',
                        $monedaConverter,
                        $monedaReporteId,
                        'Retención neteo (retenida, no sale por banco)',
                        $this->metaRetencionPago($empresaId, $retencion),
                    );
                }
            }

            $this->agregarRemanenteContrapartidasOp(
                $empresaId,
                $lineasOp,
                $lineas,
                $lineaBanco,
                $lineaRef,
                $monedaConverter,
                $monedaReporteId,
            );
        }

        return array_merge(
            $lineas,
            $this->imputarTraspasosDisponibilidadOp(
                $empresaId,
                $lineasOp,
                $lineas,
                $monedaConverter,
                $monedaReporteId,
            ),
        );
    }

    /**
     * OPP/OPA/OPV con varias piernas de banco: traspaso disp↔disp (ej. 111050→112040 FCI).
     *
     * @param  list<object>  $lineasOp
     * @param  list<array<string, mixed>>  $lineasExistentes
     * @return list<array<string, mixed>>
     */
    private function imputarTraspasosDisponibilidadOp(
        int $empresaId,
        array $lineasOp,
        array $lineasExistentes,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $refTipo = trim((string) ($lineasOp[0]->subd_ref_tipo ?? $lineasOp[0]->subd_tipo ?? ''));
        if (! in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
            return [];
        }

        $yaImputado = [];
        foreach ($lineasExistentes as $lineaReporte) {
            $clave = (int) ($lineaReporte['cuenta_disponibilidad'] ?? 0).'|'
                .($lineaReporte['origen'] ?? '').'|'
                .number_format((float) ($lineaReporte['disp_debe'] ?? 0) + (float) ($lineaReporte['disp_haber'] ?? 0), 2, '.', '');
            $yaImputado[$clave] = true;
        }

        $extra = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if (! $this->debeTraspasoDoblePierna($cuenta, $contrapartida, $refTipo)) {
                continue;
            }

            foreach ($this->itemsTraspasoDoblePierna($linea, $refTipo) as $item) {
                $cuentaDisp = (int) ($item['cuenta_disponibilidad'] ?? 0);
                $importe = (float) ($item['importe'] ?? 0);
                if ($cuentaDisp <= 0 || $importe <= 0) {
                    continue;
                }

                $dhDisp = (string) ($item['dh_imputacion'] ?? 'D');
                $clave = $cuentaDisp.'|'.($item['origen'] ?? '').'|'.number_format($importe, 2, '.', '');
                if (isset($yaImputado[$clave])) {
                    continue;
                }

                $extra[] = $this->lineaReporte(
                    $item['linea'],
                    (int) ($item['cuenta_contra'] ?? 0),
                    0,
                    $importe,
                    $dhDisp,
                    $monedaConverter,
                    $monedaReporteId,
                    (string) ($item['origen'] ?? $refTipo.' traspaso'),
                    ['cuenta_disponibilidad' => $cuentaDisp],
                );
                $yaImputado[$clave] = true;
            }
        }

        return $extra;
    }

    /**
     * Un OP anulado aparece en el subdiario como dos asientos con la misma
     * referencia OPP pero letra distinta: la emisión (banco al Haber, tipo OPP,
     * letra del OP) y la anulación (banco al Debe, tipo AOP, letra en blanco).
     * `filtrarSubdiarioPorRef` los separa (distinta letra) y `claveOperacionPago`
     * los colisiona (ignora la letra), por lo que solo se procesa la emisión.
     *
     * Esta rutina genera la imputación inversa (Debe↔Haber) de la emisión,
     * tagueada en el asiento de la anulación, de modo que:
     *  - cada asiento (emisión y anulación) concilie contra el mayor analítico, y
     *  - el neto del OP anulado por concepto sea cero (no infla el gasto).
     *
     * @param  list<object>  $lineasAnulacion  piernas de banco AOP (mov D) del OP
     * @param  list<array<string, mixed>>  $lineas  imputaciones de la emisión
     * @return list<array<string, mixed>>
     */
    private function espejarAnulacionOp(array $lineasAnulacion, array $lineas): array
    {
        $espejo = $this->construirEspejoAnulacionOp($lineasAnulacion, $lineas);
        if ($espejo === []) {
            return $lineas;
        }

        return array_merge($lineas, $espejo);
    }

    /**
     * Imputación inversa (solo piernas espejo) para el asiento AOP.
     *
     * @param  list<object>  $lineasAnulacion
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function construirEspejoAnulacionOp(array $lineasAnulacion, array $lineas): array
    {
        if ($lineasAnulacion === []) {
            return [];
        }

        $lineaAnulacion = $lineasAnulacion[0];

        // La imputación del AOP es el espejo de la emisión: si la emisión no imputó,
        // se pierden las dos y hay que decir por qué.
        if ($lineas === []) {
            $this->registrarMotivoAsiento(
                (int) ($lineaAnulacion->subd_nro_operacion ?? 0),
                'Anulación de '.trim((string) ($lineaAnulacion->subd_ref_tipo ?? 'OP'))
                    .' '.(int) ($lineaAnulacion->subd_ref_nro ?? 0)
                    .': la emisión no generó imputación, no hay nada que revertir por concepto.',
            );

            return [];
        }

        $totalAnulacionDebe = array_sum(array_map(
            fn ($l) => (float) ($l->subd_importe ?? 0),
            $lineasAnulacion,
        ));

        $totalEmisionNeto = 0.0;
        foreach ($lineas as $ln) {
            $totalEmisionNeto += (float) ($ln['debe'] ?? 0) - (float) ($ln['haber'] ?? 0);
        }

        if ($totalAnulacionDebe <= 0 || abs($totalEmisionNeto) <= 0.005) {
            return [];
        }

        $factor = $totalAnulacionDebe / $totalEmisionNeto;
        $nroAnulacion = (int) ($lineaAnulacion->subd_nro_operacion ?? 0);
        $fechaAnulacion = (int) ($lineaAnulacion->subd_fecha ?? 0);
        $descAnulacion = trim((string) ($lineaAnulacion->subd_desc_mov ?? ''));

        $espejo = [];
        foreach ($lineas as $linea) {
            $debe = (float) ($linea['debe'] ?? 0);
            $haber = (float) ($linea['haber'] ?? 0);
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $clon = $linea;
            $clon['nro_asiento'] = $nroAnulacion;
            $clon['fecha'] = $fechaAnulacion;
            $clon['fecha_fmt'] = $this->fmtFecha($fechaAnulacion);
            $clon['descripcion'] = $descAnulacion;
            $nuevoDebe = round($haber * $factor, 2);
            $nuevoHaber = round($debe * $factor, 2);
            $clon['debe'] = $nuevoDebe;
            $clon['haber'] = $nuevoHaber;
            $clon['disp_debe'] = round(((float) ($linea['disp_haber'] ?? 0)) * $factor, 2);
            $clon['disp_haber'] = round(((float) ($linea['disp_debe'] ?? 0)) * $factor, 2);
            $clon['origen'] = trim((string) ($linea['origen'] ?? '')).' (anulación)';

            if (($clon['desde_operacion_disponibilidad'] ?? false) === true) {
                $this->acumularPlanoContrapartidaDesdeDisp((int) ($clon['cuenta'] ?? 0), $nuevoDebe, $nuevoHaber);
            }

            $espejo[] = $clon;
        }

        return $espejo;
    }

    /**
     * AOP en el período sin emisión en el mismo rango: axphist + pago + espejo en AOP.
     *
     * @param  array<string, list<object>>  $anulacionesPorOp
     * @param  array<string, true>  $opsEmisionProcesadaEnPeriodo
     * @param  array<string, list<object>>  $ctamovPorAsiento
     * @param  array<string, true>  $opsProcesadas
     * @return list<array<string, mixed>>
     */
    private function procesarAnulacionesOpDesdeAxphist(
        int $empresaId,
        array $subdiario,
        array $anulacionesPorOp,
        array $opsEmisionProcesadaEnPeriodo,
        array $ctamovPorAsiento,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        array &$opsProcesadas,
    ): array {
        $lineas = [];

        foreach ($anulacionesPorOp as $claveRef => $piernasAnulBanco) {
            if (($opsEmisionProcesadaEnPeriodo[$claveRef] ?? false) === true) {
                continue;
            }

            $porAsiento = [];
            foreach ($piernasAnulBanco as $pierna) {
                $nroAsiento = (int) ($pierna->subd_nro_operacion ?? 0);
                if ($nroAsiento <= 0) {
                    continue;
                }
                $porAsiento[$nroAsiento][] = $pierna;
            }

            foreach ($porAsiento as $nroAsiento => $piernasBanco) {
                $fechaAsiento = (int) ($piernasBanco[0]->subd_fecha ?? 0);
                $claveAsi = $this->claveAsientoContable($nroAsiento, $fechaAsiento);
                if (isset($opsProcesadas[$claveAsi])) {
                    continue;
                }

                $lineasOp = array_values(array_filter(
                    $subdiario,
                    fn ($l) => (int) ($l->subd_nro_operacion ?? 0) === $nroAsiento,
                ));
                if ($lineasOp === []) {
                    continue;
                }

                $refTipo = trim((string) ($lineasOp[0]->subd_ref_tipo ?? ''));
                $refNro = (int) ($lineasOp[0]->subd_ref_nro ?? 0);
                if ($refTipo === '' || $refNro <= 0 || ! in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
                    continue;
                }

                $lineasOp = $this->mergeLineasOpSubdiarioCtamov(
                    $lineasOp,
                    [],
                    $ctamovPorAsiento[$this->claveAsientoIndex($nroAsiento, $fechaAsiento)] ?? [],
                );

                $auxpag = $this->auxpagHistoricoOperacion($empresaId, $refTipo, $refNro, $lineasOp);
                if ($auxpag === []) {
                    continue;
                }

                $plantilla = $this->procesarPago(
                    $empresaId,
                    $this->lineasOpConBancoComoHaber($lineasOp),
                    $auxpag,
                    $monedaConverter,
                    $monedaReporteId,
                );

                $espejo = $this->construirEspejoAnulacionOp($piernasBanco, $plantilla);
                if ($espejo === []) {
                    continue;
                }

                $lineas = array_merge($lineas, $espejo);
                $opsProcesadas[$claveAsi] = true;
            }
        }

        return $lineas;
    }

    /**
     * Para procesar un AOP como emisión: el banco al Debe se trata como Haber.
     *
     * @param  list<object>  $lineasOp
     * @return list<object>
     */
    private function lineasOpConBancoComoHaber(array $lineasOp): array
    {
        $adaptadas = [];

        foreach ($lineasOp as $linea) {
            $clone = clone $linea;
            $cuenta = (int) ($clone->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($clone->subd_tipo_mov ?? '')));
            if ($this->motor->esDisponibilidad($cuenta) && $mov === 'D') {
                $clone->subd_tipo_mov = 'H';
            }
            $adaptadas[] = $clone;
        }

        return $adaptadas;
    }

    /**
     * Imputa todas las contrapartidas del asiento (subdiario) sobre cuentas de disponibilidad.
     *
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarDirectoAsiento(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        ?string $refTipoForzado = null,
    ): array {
        $lineas = [];
        $refTipo = $refTipoForzado ?? trim((string) ($lineasOp[0]->subd_ref_tipo ?? $lineasOp[0]->subd_tipo ?? ''));
        $bancoReferenciaAsiento = $this->resolverBancoReferenciaAsiento($lineasOp);

        if ($this->esAsientoTraspasoInternoDisponibilidad($lineasOp)) {
            $lineaReferencia = $this->lineaReferenciaTraspasoInterno($lineasOp);
            $itemsTraspaso = $this->itemsTraspasoDoblePierna($lineaReferencia, $refTipo);
        } else {
            $itemsTraspaso = null;
        }

        foreach ($itemsTraspaso ?? $this->contrapartidasImputablesAsiento($lineasOp, $refTipo) as $item) {
            if (! $this->lineaVisible($item['linea'], $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuentaDispItem = (int) ($item['cuenta_disponibilidad'] ?? 0);
            $cuentaBanco = $cuentaDispItem;
            if ($cuentaBanco <= 0) {
                $cuentaBanco = $this->resolverCuentaDisponibilidad($item['linea']);
            }
            if ($cuentaBanco <= 0) {
                continue;
            }
            if ($this->motor->esCuentaCreditoComercialDisp($cuentaBanco) && $cuentaDispItem <= 0) {
                $cuentaBanco = $bancoReferenciaAsiento > 0 ? $bancoReferenciaAsiento : $cuentaBanco;
            }
            if ($cuentaBanco <= 0) {
                continue;
            }

            $conceptoId = array_key_exists('concepto_id', $item)
                ? (int) $item['concepto_id']
                : $this->motor->conceptoImputacionCuenta($empresaId, (int) $item['cuenta_contra']);

            $lineas[] = $this->lineaReporte(
                $item['linea'],
                (int) $item['cuenta_contra'],
                $conceptoId,
                $item['importe'],
                $item['dh_imputacion'],
                $monedaConverter,
                $monedaReporteId,
                $item['origen'],
                [
                    'cuenta_disponibilidad' => $cuentaBanco,
                    'emisor' => trim((string) ($item['linea']->subd_emisor ?? '')),
                ],
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $lineasAsiento
     * @param  array<string, list<object>>  $auxpagPorOp
     * @param  array<string, true>  $opsProcesadas
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoCtamov(
        int $empresaId,
        array $lineasAsiento,
        array $auxpagPorOp,
        array &$opsProcesadas,
        array $subdiarioPorAsiento,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
        $nroAsiento = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
        $fecha = (int) ($lineasAsiento[0]->ctav_fecha ?? 0);
        if ($nroAsiento > 0) {
            $lineasOp = $this->mergeLineasOpSubdiarioCtamov(
                $lineasOp,
                $subdiarioPorAsiento[$this->claveAsientoIndex($nroAsiento, $fecha)] ?? [],
            );
            $lineasOp = $this->filtrarLineasOpPorAsiento($lineasOp, $nroAsiento);
        }

        $lineasOp = array_values(array_filter(
            $lineasOp,
            fn ($l) => $this->lineaVisible($l, $monedaConverter, $monedaReporteId, $soloMonedaOrigen),
        ));

        if ($lineasOp === []) {
            return [];
        }

        // Venta máquinas / gastro / estacionamiento (Anita): cada cuenta > límite va a su
        // concepto nativo; no se toman cuentas ≤ límite ni se ancla a caja/banco.
        if ($this->debeProcesarAsientoCtamovVentaMaquinas($lineasAsiento, $lineasOp)) {
            $lineas = $this->procesarAsientoCtamovVentaMaquinasPorConcepto(
                $empresaId,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
            );
            if ($lineas !== []) {
                $opsProcesadas[$this->claveOperacionCtamov($nroAsiento, $fecha)] = true;
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
            }

            return $lineas;
        }

        if ($this->debeProcesarAsientoCtamovVentaMultibanco($lineasAsiento, $lineasOp)) {
            $lineas = $this->procesarAsientoCtamovVentaMultibanco(
                $empresaId,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
            );
            if ($lineas !== []) {
                $opsProcesadas[$this->claveOperacionCtamov($nroAsiento, $fecha)] = true;
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
            }

            return $lineas;
        }

        if (! $this->asientoOpTieneCuentaDentroLimiteMayorConcepto($lineasOp)) {
            return [];
        }

        if ($this->debeProcesarAsientoComRecepcionCtamov($lineasAsiento)) {
            $lineas = $this->procesarAsientoComRecepcionCtamov(
                $empresaId,
                $lineasAsiento,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
            );
            if ($lineas !== []) {
                $opsProcesadas[$this->claveOperacionCtamov($nroAsiento, $fecha)] = true;
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
            }

            return $lineas;
        }

        if ($this->debeProcesarAsientoCtamovSistemaB($lineasAsiento, $lineasOp)) {
            $lineas = $this->procesarAsientoCtamovSistemaB(
                $empresaId,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
            );
            if ($lineas !== []) {
                $opsProcesadas[$this->claveOperacionCtamov($nroAsiento, $fecha)] = true;
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
            }

            return $lineas;
        }

        if ($this->esAsientoCtamovDirectoCobranzaCreditoComercial($lineasOp)) {
            // Tipo Anita "0" dispara traspaso doble (banco+113) y deja neto concepto 0
            // contra analítico del banco. Tratarlo como VTA: solo contrapartida 113.
            $refTipoDirecto = strtoupper(trim((string) ($lineasOp[0]->subd_ref_tipo ?? $lineasOp[0]->subd_tipo ?? '')));
            if ($refTipoDirecto === '' || $refTipoDirecto === '0') {
                $refTipoDirecto = 'VTA';
            }

            $lineas = $this->procesarDirectoAsiento(
                $empresaId,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
                $refTipoDirecto,
            );
            if ($lineas !== []) {
                $opsProcesadas[$this->claveOperacionCtamov($nroAsiento, $fecha)] = true;
                $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
            }

            return $lineas;
        }

        $tieneDisponibilidad = false;
        foreach ($lineasOp as $linea) {
            if ($this->resolverCuentaDisponibilidad($linea) > 0) {
                $tieneDisponibilidad = true;
                break;
            }
        }

        if (! $tieneDisponibilidad) {
            return [];
        }

        $ref = $lineasOp[0];

        if ($nroAsiento <= 0) {
            return [];
        }

        $claveCtamov = $this->claveOperacionCtamov($nroAsiento, $fecha);
        if (isset($opsProcesadas[$claveCtamov])) {
            return [];
        }

        $refTipo = trim((string) ($ref->subd_ref_tipo ?? ''));
        if ($refTipo === '') {
            $refTipo = '0';
        }
        if (! in_array($refTipo, MayorConceptoMemoriaMotor::TIPOS_REF_IMPUTABLE, true)) {
            $refTipo = '0';
        }

        $claveOp = $this->claveOperacionPago(
            $empresaId,
            $refTipo,
            (int) ($ref->subd_ref_nro ?? 0),
            $fecha,
        );

        $claveProcesamientoPago = $this->claveProcesamientoPagoOp($claveOp, $nroAsiento);
        if (isset($opsProcesadas[$claveProcesamientoPago])) {
            return [];
        }

        if ($this->debeProcesarComoPagoProveedor($refTipo, $lineasOp, $auxpagPorOp, $claveOp)) {
            $auxpagOp = $this->auxpagOperacionConFallback(
                $empresaId,
                $refTipo,
                (int) ($ref->subd_ref_nro ?? 0),
                $fecha,
                $auxpagPorOp,
                $claveOp,
                $lineasOp,
            );
            $lineas = $this->procesarPago($empresaId, $lineasOp, $auxpagOp, $monedaConverter, $monedaReporteId);
        } elseif ($this->debeProcesarAsientoMultilinea($lineasOp, $refTipo)) {
            $lineas = $this->procesarAsientoMultilinea($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $refTipo);
        } else {
            $lineas = $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $refTipo);
        }

        if ($lineas !== []) {
            $opsProcesadas[$claveCtamov] = true;
            $opsProcesadas[$claveProcesamientoPago] = true;
            $opsProcesadas[$claveOp] = true;
            $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
        }

        return $lineas;
    }

    /**
     * Asientos ctamov con ctav_sistema=B (reclasificaciones, cheques vencidos, etc.):
     * sin OP de proveedor; imputa cada contrapartida del asiento contra la disponibilidad.
     *
     * @param  list<object>  $lineasAsiento
     * @param  list<object>  $lineasOp
     */
    private function debeProcesarAsientoCtamovSistemaB(array $lineasAsiento, array $lineasOp): bool
    {
        if ($lineasAsiento === [] || $lineasOp === []) {
            return false;
        }

        foreach ($lineasAsiento as $linea) {
            if (! in_array(strtoupper(trim((string) ($linea->ctav_sistema ?? ''))), ['B', 'V'], true)) {
                return false;
            }
        }

        if ($this->debeProcesarAsientoComRecepcionCtamov($lineasAsiento)) {
            return false;
        }

        if ($this->esAsientoCtamovVentaMaquinas($lineasOp)) {
            return false;
        }

        if ($this->esAsientoCtamovDirectoCobranzaCreditoComercial($lineasOp)) {
            return false;
        }

        if ($this->debeProcesarAsientoCtamovVentaMultibanco($lineasAsiento, $lineasOp)) {
            return false;
        }

        $tieneDisponibilidad = false;
        $soloDisponibilidad = true;
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($this->motor->esDisponibilidad($cuenta)) {
                $tieneDisponibilidad = true;
            } else {
                $soloDisponibilidad = false;
            }
        }

        if (! $tieneDisponibilidad || $soloDisponibilidad) {
            return false;
        }

        return true;
    }

    /**
     * Cobranza QR / gastro: un solo movimiento contable (banco Debe + crédito comercial 113 Haber).
     * Va por procesarDirectoAsiento (legacy "Movimiento directo"), no por anclas/residual sistema B.
     *
     * @param  list<object>  $lineasOp
     */
    private function esAsientoCtamovDirectoCobranzaCreditoComercial(array $lineasOp): bool
    {
        if (count($lineasOp) !== 2) {
            return false;
        }

        $anclas = $this->anclasDisponibilidadMayorAnaliticoCtamovSistemaB($lineasOp);
        $totalAnclaDebe = 0.0;
        foreach ($anclas as $movimientos) {
            $totalAnclaDebe += (float) ($movimientos['D'] ?? 0.0);
        }

        $importeCreditoHaber = null;
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                return false;
            }

            if ($this->esPiernaDirectaCreditoComercialCtamovSistemaB($cuenta, $mov) && $mov === 'H') {
                if ($importeCreditoHaber !== null) {
                    return false;
                }
                $importeCreditoHaber = $importe;
            }
        }

        if ($importeCreditoHaber === null || $totalAnclaDebe <= 0) {
            return false;
        }

        return abs($importeCreditoHaber - $totalAnclaDebe) < 0.01;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoCtamovSistemaB(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        $anclas = $this->anclasDisponibilidadMayorAnaliticoCtamovSistemaB($lineasOp);
        if ($anclas === []) {
            return [];
        }

        // Con 113/medios fuera del límite el prorrateo parcial dejaba el crédito en analítico
        // y solo una fracción en concepto (ej. Dif caja tc 222347). Misma regla que máquinas/
        // gastro: piernas literales; ≤ límite solo analítico.
        if ($this->esAsientoCtamovSistemaBLiteralPorConcepto($lineasOp)) {
            return $this->procesarAsientoCtamovPiernasLiteralPorConcepto(
                $empresaId,
                $lineasOp,
                $monedaConverter,
                $monedaReporteId,
                $soloMonedaOrigen,
                'Ctamov sistema B',
            );
        }

        $lineas = [];
        $consumo = [];
        foreach ($anclas as $cuenta => $movimientos) {
            foreach ($movimientos as $mov => $importe) {
                if ($importe > 0) {
                    $consumo[$cuenta][$mov] = 0.0;
                }
            }
        }

        $asientoMultimedio = count($this->mediosCobranzaDebeAsiento($lineasOp)) >= 2;
        $factorProrrateo = $this->factorProrrateoAnaliticoControlContrapartidas($lineasOp, false);
        $aplicaProrrateoParcial = $factorProrrateo < 1.0 - 1e-9;
        $bancoReferencia = $this->resolverBancoReferenciaAsiento($lineasOp);
        if ($bancoReferencia <= 0) {
            $bancoReferencia = $this->resolverCuentaDisponibilidadBancoAsiento($lineasOp);
        }
        $anclaPrincipal = $bancoReferencia > 0 ? $bancoReferencia : (int) array_key_first($anclas);

        foreach ($lineasOp as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if (! $this->esPiernaDirectaCreditoComercialCtamovSistemaB($cuenta, $mov)) {
                continue;
            }

            // Con prorrateo parcial (113 / disp fuera del analítico), el 113 no se emite nativo:
            // las contrapartidas ya se escalan al neto de anclas ≤ límite (igual que multilinea).
            if ($aplicaProrrateoParcial) {
                continue;
            }

            $cuentaDispCredito = $anclaPrincipal > 0 ? $anclaPrincipal : $cuenta;

            $lineas[] = $this->lineaReporte(
                $linea,
                $cuenta,
                $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                $importe,
                $mov,
                $monedaConverter,
                $monedaReporteId,
                'Ctamov sistema B medio',
                [
                    'cuenta_disponibilidad' => $cuentaDispCredito,
                    'emisor' => trim((string) ($linea->subd_emisor ?? '')),
                ],
            );
        }

        $contrapartidas = [];
        foreach ($lineasOp as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            if (! $this->esContrapartidaImputableCtamovSistemaB($linea, $asientoMultimedio, $aplicaProrrateoParcial)) {
                continue;
            }

            if ($this->esPiernaInternaCompensadaCtamovSistemaB($linea, $lineasOp)) {
                continue;
            }

            $contrapartidas[] = $linea;
        }

        usort(
            $contrapartidas,
            fn (object $a, object $b): int => ((float) ($b->subd_importe ?? 0)) <=> ((float) ($a->subd_importe ?? 0)),
        );

        foreach ($contrapartidas as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            $importeImputable = $this->aplicarFactorProrrateoAnaliticoControl($importe, $factorProrrateo);
            if ($importeImputable <= 0) {
                continue;
            }

            foreach ($this->resolverImputacionesContrapartidaAnclasMayorAnalitico(
                $linea,
                $lineasOp,
                $anclas,
                $consumo,
                $importeImputable,
                $bancoReferencia,
            ) as $imputacion) {
                if ($imputacion['importe'] <= 0 || $imputacion['cuenta_disponibilidad'] <= 0) {
                    continue;
                }

                $lineas[] = $this->lineaReporte(
                    $linea,
                    $cuenta,
                    $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                    $imputacion['importe'],
                    $mov,
                    $monedaConverter,
                    $monedaReporteId,
                    'Ctamov sistema B',
                    [
                        'cuenta_disponibilidad' => $imputacion['cuenta_disponibilidad'],
                        'emisor' => trim((string) ($linea->subd_emisor ?? '')),
                    ],
                );
            }
        }

        foreach ($anclas as $cuenta => $movimientos) {
            foreach ($movimientos as $mov => $total) {
                $residual = round($total - ($consumo[$cuenta][$mov] ?? 0.0), 2);
                if ($residual <= 0) {
                    continue;
                }

                $lineaRef = $this->buscarLineaOpCuentaMov($lineasOp, $cuenta, $mov);
                if ($lineaRef === null) {
                    continue;
                }

                if ($this->esPiernaInternaCompensadaCtamovSistemaB($lineaRef, $lineasOp)) {
                    continue;
                }

                $lineas[] = $this->lineaReporte(
                    $lineaRef,
                    $cuenta,
                    $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                    $residual,
                    $mov,
                    $monedaConverter,
                    $monedaReporteId,
                    'Ctamov sistema B medio',
                    [
                        'cuenta_disponibilidad' => $cuenta,
                        'emisor' => trim((string) ($lineaRef->subd_emisor ?? '')),
                    ],
                );
            }
        }

        if ($aplicaProrrateoParcial && $lineas !== []) {
            $lineas = $this->reconciliarRedondeoNetoVentaMaquinasMultilinea($lineasOp, $lineas);
        }

        return $lineas;
    }

    /**
     * Totales D/H por cuenta de disponibilidad dentro del mayor analítico (≤ límite control).
     *
     * @param  list<object>  $lineasOp
     * @return array<int, array{D: float, H: float}>
     */
    private function anclasDisponibilidadMayorAnaliticoCtamovSistemaB(array $lineasOp): array
    {
        $anclas = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($cuenta <= 0 || $importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $anclas[$cuenta][$mov] = round(($anclas[$cuenta][$mov] ?? 0.0) + $importe, 2);
        }

        return $anclas;
    }

    /**
     * Cuentas del asiento que no son ancla del mayor analítico y deben imputarse contra ellas.
     */
    private function esContrapartidaImputableCtamovSistemaB(
        object $linea,
        bool $asientoMultimedio,
        bool $aplicaProrrateoParcial,
    ): bool {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        if ($cuenta <= 0 || $importe <= 0) {
            return false;
        }

        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
        if (! in_array($mov, ['D', 'H'], true)) {
            return false;
        }

        if ($this->motor->esDisponibilidad($cuenta) && $this->motor->esCuentaAnaliticoControl($cuenta)) {
            return false;
        }

        if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
            return false;
        }

        if ($this->esMedioCobranzaCtamovVenta($cuenta, $mov)
            && ! $this->esCuentaPasivaPublicoCtamovSistemaB($cuenta)) {
            return false;
        }

        if ($this->esPiernaDirectaNativaCtamovSistemaB($cuenta, $mov, $asientoMultimedio)) {
            return false;
        }

        if ($aplicaProrrateoParcial && $this->motor->esCuentaCreditoComercialDisp($cuenta)) {
            return false;
        }

        return true;
    }

    /**
     * Pareo por importe contra ancla opuesta; si no alcanza, prorrateo por capacidad residual de cada ancla.
     *
     * @param  array<int, array{D: float, H: float}>  $anclas
     * @param  array<int, array<string, float>>  $consumo
     * @return list<array{cuenta_disponibilidad: int, importe: float}>
     */
    private function resolverImputacionesContrapartidaAnclasMayorAnalitico(
        object $linea,
        array $lineasOp,
        array $anclas,
        array &$consumo,
        float $importeImputable,
        int $bancoReferencia,
    ): array {
        if ($importeImputable <= 0) {
            return [];
        }

        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
        if (! in_array($mov, ['D', 'H'], true)) {
            return [];
        }

        $movAncla = $mov === 'D' ? 'H' : 'D';

        $cuentaPareo = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
        if ($cuentaPareo > 0 && isset($anclas[$cuentaPareo])) {
            $disponible = round(($anclas[$cuentaPareo][$movAncla] ?? 0.0) - ($consumo[$cuentaPareo][$movAncla] ?? 0.0), 2);
            if ($disponible + 0.01 >= $importeImputable) {
                $consumo[$cuentaPareo][$movAncla] = round(($consumo[$cuentaPareo][$movAncla] ?? 0.0) + $importeImputable, 2);

                return [[
                    'cuenta_disponibilidad' => $cuentaPareo,
                    'importe' => $importeImputable,
                ]];
            }
        }

        foreach ($anclas as $cuenta => $movimientos) {
            $disponible = round(($movimientos[$movAncla] ?? 0.0) - ($consumo[$cuenta][$movAncla] ?? 0.0), 2);
            if ($disponible + 0.01 >= $importeImputable && abs($disponible - $importeImputable) < 0.01) {
                $consumo[$cuenta][$movAncla] = round(($consumo[$cuenta][$movAncla] ?? 0.0) + $importeImputable, 2);

                return [[
                    'cuenta_disponibilidad' => (int) $cuenta,
                    'importe' => $importeImputable,
                ]];
            }
        }

        $pesos = [];
        foreach ($anclas as $cuenta => $movimientos) {
            $disponible = round(($movimientos[$movAncla] ?? 0.0) - ($consumo[$cuenta][$movAncla] ?? 0.0), 2);
            if ($disponible > 0.01) {
                $pesos[(int) $cuenta] = $disponible;
            }
        }

        if ($pesos === []) {
            $pesos = $this->pesosAnclasProrrateoResidualCtamovSistemaB($anclas, $consumo, $mov);
        }

        if ($pesos === [] && $bancoReferencia > 0 && isset($anclas[$bancoReferencia])) {
            $this->consumirCapacidadAnclaCtamovSistemaB($consumo, $bancoReferencia, $anclas, $mov, $importeImputable);

            return [[
                'cuenta_disponibilidad' => $bancoReferencia,
                'importe' => $importeImputable,
            ]];
        }

        if ($pesos === []) {
            return [];
        }

        $imputaciones = [];
        foreach ($this->prorratearImportePorMediosCobranzaDestinoConBaseTotal(
            $importeImputable,
            $pesos,
            $pesos,
        ) as $porcion) {
            if ($porcion['importe'] <= 0) {
                continue;
            }

            $cuenta = (int) $porcion['cuenta'];
            $this->consumirCapacidadAnclaCtamovSistemaB($consumo, $cuenta, $anclas, $mov, $porcion['importe']);
            $imputaciones[] = [
                'cuenta_disponibilidad' => $cuenta,
                'importe' => $porcion['importe'],
            ];
        }

        return $imputaciones;
    }

    /**
     * @param  array<int, array{D: float, H: float}>  $anclas
     * @param  array<int, array<string, float>>  $consumo
     * @return array<int, float>
     */
    private function pesosAnclasProrrateoResidualCtamovSistemaB(array $anclas, array $consumo, string $movContra): array
    {
        $pesos = [];
        $movPreferido = strtoupper(trim($movContra)) === 'H' ? 'D' : 'H';

        foreach ($anclas as $cuenta => $movimientos) {
            $preferido = round(($movimientos[$movPreferido] ?? 0.0) - ($consumo[$cuenta][$movPreferido] ?? 0.0), 2);
            $debe = round(($movimientos['D'] ?? 0.0) - ($consumo[$cuenta]['D'] ?? 0.0), 2);
            $haber = round(($movimientos['H'] ?? 0.0) - ($consumo[$cuenta]['H'] ?? 0.0), 2);
            $peso = $preferido > 0.01 ? $preferido : max($debe, $haber, abs($debe - $haber));

            if ($peso > 0.01) {
                $pesos[(int) $cuenta] = $peso;
            }
        }

        return $pesos;
    }

    /**
     * @param  array<int, array{D: float, H: float}>  $anclas
     * @param  array<int, array<string, float>>  $consumo
     */
    private function consumirCapacidadAnclaCtamovSistemaB(
        array &$consumo,
        int $cuenta,
        array $anclas,
        string $movContra,
        float $importe,
    ): void {
        if ($importe <= 0 || ! isset($anclas[$cuenta])) {
            return;
        }

        $movContra = strtoupper(trim($movContra));
        $movAncla = $movContra === 'D' ? 'H' : 'D';
        $movimientos = $anclas[$cuenta];
        $restante = $importe;

        foreach ([$movAncla, $movContra === 'H' ? 'D' : 'H'] as $mov) {
            $capacidad = round(($movimientos[$mov] ?? 0.0) - ($consumo[$cuenta][$mov] ?? 0.0), 2);
            if ($capacidad <= 0.01) {
                continue;
            }

            $porcion = round(min($capacidad, $restante), 2);
            if ($porcion <= 0) {
                continue;
            }

            $consumo[$cuenta][$mov] = round(($consumo[$cuenta][$mov] ?? 0.0) + $porcion, 2);
            $restante = round($restante - $porcion, 2);
            if ($restante <= 0.01) {
                break;
            }
        }
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function buscarLineaOpCuentaMov(array $lineasOp, int $cuenta, string $mov): ?object
    {
        $mov = strtoupper(trim($mov));

        foreach ($lineasOp as $linea) {
            if ((int) ($linea->subd_cuenta ?? 0) !== $cuenta) {
                continue;
            }

            if (strtoupper(trim((string) ($linea->subd_tipo_mov ?? ''))) !== $mov) {
                continue;
            }

            return $linea;
        }

        return null;
    }

    /**
     * 211xxx (moneda en poder del público / partidas pendientes): contrapartida en ctamov B, no medio de cobranza.
     */
    private function esCuentaPasivaPublicoCtamovSistemaB(int $cuenta): bool
    {
        return $cuenta >= 211000000 && $cuenta < 212000000;
    }

    /**
     * Reclasificación ctamov B: disponibilidad del mayor (≤ límite) frente a contrapartidas fuera del rango.
     *
     * @param  list<object>  $lineasOp
     */
    private function esReclasificacionDispContrapartidasCtamovSistemaB(array $lineasOp): bool
    {
        $dispSalidaAnalitico = 0;
        $contrapartidas = 0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov === 'D'
                && ($this->motor->esCuentaBancoCaja($cuenta) || $this->motor->esCuentaCreditoComercialDisp($cuenta))) {
                return false;
            }

            if ($mov === 'H' && $this->esCuentaImputableVentaCtamov($cuenta)) {
                return false;
            }

            if ($mov === 'H'
                && $this->motor->esDisponibilidad($cuenta)
                && $this->motor->esCuentaAnaliticoControl($cuenta)) {
                $dispSalidaAnalitico++;
            }

            if ($this->motor->esDisponibilidad($cuenta)
                || $this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                continue;
            }

            if (in_array($mov, ['D', 'H'], true)) {
                $contrapartidas++;
            }
        }

        return $dispSalidaAnalitico >= 1 && $contrapartidas >= 1;
    }

    /**
     * Imputa contrapartidas de reclasificación anclando la disponibilidad del mayor analítico.
     * Prioriza pareo por importe; si no hay match, prorratea por cantidad de cuentas en cada pata.
     *
     * @param  list<object>  $lineasOp
     * @return list<array{cuenta_disponibilidad: int, importe: float}>
     */
    private function imputacionesReclasificacionCtamovSistemaB(
        object $linea,
        array $lineasOp,
        float $importeImputable,
        string $mov,
        int $cuentaContra,
        int $conceptoId,
        string $emisor,
        int $bancoReferencia,
    ): array {
        if ($importeImputable <= 0 || ! in_array($mov, ['D', 'H'], true)) {
            return [];
        }

        if ($this->esPiernaInternaCompensadaCtamovSistemaB($linea, $lineasOp)) {
            return [];
        }

        $cuentaDisp = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
        if ($cuentaDisp <= 0) {
            $cuentaDisp = $this->resolverDisponibilidadSalidaCtamovSistemaB($lineasOp);
        }
        if ($cuentaDisp <= 0) {
            $cuentaDisp = $this->resolverDisponibilidadEntradaCtamovSistemaB($lineasOp);
        }
        if ($cuentaDisp <= 0) {
            $cuentaDisp = $bancoReferencia;
        }

        if ($cuentaDisp > 0) {
            return [[
                'cuenta_disponibilidad' => $cuentaDisp,
                'importe' => $importeImputable,
            ]];
        }

        $dispSalida = $this->cuentasDisponibilidadSalidaAnaliticoCtamovSistemaB($lineasOp);
        $contrapartidas = $this->cuentasContrapartidaReclasificacionCtamovSistemaB($lineasOp);
        if ($dispSalida === [] || $contrapartidas === []) {
            return [];
        }

        $indiceContra = array_search($cuentaContra, $contrapartidas, true);
        if ($indiceContra === false) {
            return [];
        }

        $porciones = $this->prorratearImportePorCantidadCuentas(
            $importeImputable,
            count($dispSalida),
            $indiceContra,
            count($contrapartidas),
        );

        $imputaciones = [];
        foreach ($porciones as $indiceDisp => $porcion) {
            if ($porcion <= 0) {
                continue;
            }

            $imputaciones[] = [
                'cuenta_disponibilidad' => $dispSalida[$indiceDisp],
                'importe' => $porcion,
            ];
        }

        return $imputaciones;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<int>
     */
    private function cuentasDisponibilidadSalidaAnaliticoCtamovSistemaB(array $lineasOp): array
    {
        $cuentas = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || $mov !== 'H') {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $cuentas[$cuenta] = true;
        }

        $lista = array_keys($cuentas);
        sort($lista, SORT_NUMERIC);

        return $lista;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<int>
     */
    private function cuentasContrapartidaReclasificacionCtamovSistemaB(array $lineasOp): array
    {
        $cuentas = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            if ($this->motor->esDisponibilidad($cuenta) || $this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                continue;
            }

            $cuentas[$cuenta] = true;
        }

        $lista = array_keys($cuentas);
        sort($lista, SORT_NUMERIC);

        return $lista;
    }

    /**
     * Reparte importe entre cuentas destino según posición relativa (cantidad contrapartidas / cantidad disp).
     *
     * @return array<int, float>
     */
    private function prorratearImportePorCantidadCuentas(
        float $importe,
        int $cantidadDestino,
        int $indiceOrigen,
        int $cantidadOrigen,
    ): array {
        if ($importe <= 0 || $cantidadDestino <= 0 || $cantidadOrigen <= 0) {
            return [];
        }

        if ($cantidadDestino === 1) {
            return [0 => round($importe, 2)];
        }

        if ($cantidadOrigen === 1) {
            $porCuenta = round($importe / $cantidadDestino, 2);
            $porciones = array_fill(0, $cantidadDestino, $porCuenta);
            $porciones[$cantidadDestino - 1] = round($importe - ($porCuenta * ($cantidadDestino - 1)), 2);

            return $porciones;
        }

        $destinoInicio = (int) floor($indiceOrigen * $cantidadDestino / $cantidadOrigen);
        $destinoFin = (int) floor(($indiceOrigen + 1) * $cantidadDestino / $cantidadOrigen) - 1;
        if ($destinoFin < $destinoInicio) {
            $destinoFin = $destinoInicio;
        }

        $cantidadAsignada = $destinoFin - $destinoInicio + 1;
        $porCuenta = round($importe / $cantidadAsignada, 2);
        $porciones = array_fill(0, $cantidadDestino, 0.0);

        for ($i = $destinoInicio; $i <= $destinoFin; $i++) {
            if ($i === $destinoFin) {
                $porciones[$i] = round($importe - ($porCuenta * ($cantidadAsignada - 1)), 2);
            } else {
                $porciones[$i] = $porCuenta;
            }
        }

        return $porciones;
    }

    /**
     * Disponibilidad al Haber (salida caja/banco) dentro del mayor analítico de control.
     *
     * @param  list<object>  $lineasOp
     */
    private function resolverDisponibilidadSalidaCtamovSistemaB(array $lineasOp): int
    {
        $mejorCuenta = 0;
        $mejorImporte = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || $mov !== 'H') {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            if ($importe > $mejorImporte) {
                $mejorImporte = $importe;
                $mejorCuenta = $cuenta;
            }
        }

        return $mejorCuenta;
    }

    /**
     * Disponibilidad al Debe (entrada FCI/banco) para anclar gasto en asientos sistema B sin medio cobranza.
     *
     * @param  list<object>  $lineasOp
     */
    private function resolverDisponibilidadEntradaCtamovSistemaB(array $lineasOp): int
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'D' && $this->motor->esDisponibilidad($cuenta)) {
                return $cuenta;
            }
        }

        return 0;
    }

    /**
     * Par D/H mismo importe en la misma cuenta (wash interno del asiento sistema B).
     *
     * @param  list<object>  $lineasOp
     */
    private function esPiernaInternaCompensadaCtamovSistemaB(object $linea, array $lineasOp): bool
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        if ($cuenta <= 0 || $importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
            return false;
        }

        $opuesto = $mov === 'D' ? 'H' : 'D';

        foreach ($lineasOp as $otra) {
            if ((int) ($otra->subd_cuenta ?? 0) !== $cuenta) {
                continue;
            }

            if (strtoupper(trim((string) ($otra->subd_tipo_mov ?? ''))) !== $opuesto) {
                continue;
            }

            if (abs((float) ($otra->subd_importe ?? 0) - $importe) < 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ventas ctamov gastro/estacionamiento con medios de cobro al Debe (111, 113, 211…).
     *
     * @param  list<object>  $lineasAsiento
     * @param  list<object>  $lineasOp
     */
    private function debeProcesarAsientoCtamovVentaMultibanco(array $lineasAsiento, array $lineasOp): bool
    {
        if ($lineasAsiento === [] || $lineasOp === []) {
            return false;
        }

        $sistemaEsperado = null;
        foreach ($lineasAsiento as $linea) {
            $sistema = strtoupper(trim((string) ($linea->ctav_sistema ?? '')));
            if (! in_array($sistema, ['B', 'V'], true)) {
                return false;
            }
            $sistemaEsperado ??= $sistema;
            if ($sistemaEsperado !== $sistema) {
                return false;
            }
        }

        if ($this->debeProcesarAsientoComRecepcionCtamov($lineasAsiento)) {
            return false;
        }

        $mediosCobranza = $this->mediosCobranzaDebeAsiento($lineasOp);
        $lineasVenta = 0;
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'H' && $this->esCuentaVentaCtamovMultibanco($cuenta)) {
                $lineasVenta++;
            }
        }

        return count($mediosCobranza) >= 1 && $lineasVenta >= 1
            && $this->esCtamovVentaGastronomiaEstacionamiento($lineasAsiento, $lineasOp);
    }

    /**
     * Cobranzas ctamov de gastronomía / estacionamiento (no venta de máquinas 412xxx).
     *
     * @param  list<object>  $lineasAsiento
     * @param  list<object>  $lineasOp
     */
    private function esCtamovVentaGastronomiaEstacionamiento(array $lineasAsiento, array $lineasOp): bool
    {
        foreach ($lineasAsiento as $linea) {
            if (strtoupper(trim((string) ($linea->ctav_sistema ?? ''))) === 'V') {
                return true;
            }

            $desc = strtolower(trim((string) ($linea->ctav_desc_mov ?? '')));
            if (str_contains($desc, 'gastronom') || str_contains($desc, 'estacionamiento')) {
                return true;
            }
        }

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 413010000 && $cuenta < 416000000) {
                return true;
            }

            if ($cuenta === 214010009) {
                return true;
            }
        }

        return false;
    }

    /**
     * Venta/IVA imputable al Haber en cierres ctamov multimedio (estilo COM).
     */
    private function esCuentaVentaCtamovMultibanco(int $cuenta): bool
    {
        if ($cuenta <= 0) {
            return false;
        }

        if ($cuenta >= 413010000 && $cuenta < 416000000) {
            return true;
        }

        if ($cuenta >= 214010000 && $cuenta < 215000000) {
            return true;
        }

        if ($cuenta >= 114010000 && $cuenta < 114020000) {
            return true;
        }

        return false;
    }

    /**
     * Piernas Debe del asiento que representan cobranza (ancla de imputación, como COM→117010).
     */
    private function esMedioCobranzaCtamovVenta(int $cuenta, string $mov): bool
    {
        if ($cuenta <= 0 || strtoupper(trim($mov)) !== 'D') {
            return false;
        }

        if ($this->esCuentaPasivaPublicoCtamovSistemaB($cuenta)) {
            return false;
        }

        return $this->motor->esCuentaBancoCaja($cuenta)
            || $this->motor->esCuentaCreditoComercialDisp($cuenta)
            || $this->motor->esProveedor($cuenta);
    }

    /**
     * Venta/ingreso imputable al Haber en cierres ctamov (gastro 413xxx, máquinas 412xxx, IVA, percepciones).
     */
    private function esCuentaImputableVentaCtamov(int $cuenta): bool
    {
        if ($cuenta <= 0) {
            return false;
        }

        if ($cuenta >= 412010000 && $cuenta < 413000000) {
            return true;
        }

        return $this->esCuentaVentaCtamovMultibanco($cuenta);
    }

    /**
     * Piernas nativas ctamov que el mayor plano acumula tal cual (disp = cuenta).
     * Solo en asientos multimedio: evita duplicar disp con el prorrateo de concepto.
     */
    private function esPiernaDirectaNativaCtamovSistemaB(int $cuenta, string $mov, bool $asientoMultimedio): bool
    {
        if ($cuenta <= 0) {
            return false;
        }

        $mov = strtoupper(trim($mov));

        if ($mov === 'H' && ($this->motor->esDisponibilidad($cuenta) || $this->motor->esCuentaInversionDisp($cuenta))) {
            return true;
        }

        if (! $asientoMultimedio) {
            return $this->esPiernaDirectaCreditoComercialCtamovSistemaB($cuenta, $mov);
        }

        if ($mov === 'D' && $this->esMedioCobranzaCtamovVenta($cuenta, $mov)) {
            return true;
        }

        return $this->esPiernaDirectaCreditoComercialCtamovSistemaB($cuenta, $mov);
    }

    /**
     * Crédito comercial 113xxx en ctamov sistema B: imputación directa 100% en la propia cuenta (plano = disp).
     * Debe: medio de cobranza; Haber: piernas nativas ctamov (ej. ajustes venta máquinas).
     */
    private function esPiernaDirectaCreditoComercialCtamovSistemaB(int $cuenta, string $mov): bool
    {
        if ($cuenta <= 0 || ! $this->motor->esCuentaCreditoComercialDisp($cuenta)) {
            return false;
        }

        $mov = strtoupper(trim($mov));

        if ($mov === 'H') {
            return true;
        }

        return $mov === 'D' && $this->esMedioCobranzaCtamovVenta($cuenta, $mov);
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    /**
     * Gastro/estacionamiento: misma regla que venta máquinas (piernas literales; ≤ límite fuera).
     *
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoCtamovVentaMultibanco(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        return $this->procesarAsientoCtamovPiernasLiteralPorConcepto(
            $empresaId,
            $lineasOp,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
            'Ctamov venta cobranza',
        );
    }

    /**
     * Una sola pierna disparadora por par (evita duplicar al recorrer las dos líneas ctamov/subdiario).
     */
    private function esLineaDisparadoraTraspasoDoblePierna(object $linea, int $cuenta, int $contrapartida): bool
    {
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        if ($this->motor->esDisponibilidad($cuenta) && $mov === 'D') {
            return true;
        }

        if ($this->motor->esDisponibilidad($cuenta) && $mov === 'H'
            && $this->motor->esDisponibilidadPlano($contrapartida)
            && ! $this->motor->esDisponibilidad($contrapartida)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return array<int, float>
     */
    private function mediosCobranzaDebeAsiento(array $lineasOp): array
    {
        $porCuenta = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0 || ! $this->esMedioCobranzaCtamovVenta($cuenta, $mov)) {
                continue;
            }

            $porCuenta[$cuenta] = ($porCuenta[$cuenta] ?? 0.0) + $importe;
        }

        return $porCuenta;
    }

    /**
     * Reparte importe solo entre cuentas destino usando pesos del total de medios del asiento.
     *
     * @param  array<int, float>  $mediosDestino
     * @param  array<int, float>  $mediosBaseTotal
     * @return list<array{cuenta: int, importe: float}>
     */
    private function prorratearImportePorMediosCobranzaDestinoConBaseTotal(
        float $importe,
        array $mediosDestino,
        array $mediosBaseTotal,
    ): array {
        $totalBase = array_sum($mediosBaseTotal);
        if ($importe <= 0 || $totalBase <= 0 || $mediosDestino === []) {
            return [];
        }

        $cuentasMedio = array_keys($mediosDestino);
        $ultimoIndice = count($cuentasMedio) - 1;
        $acumulado = 0.0;
        $porciones = [];

        foreach ($cuentasMedio as $indice => $cuentaMedio) {
            $peso = (float) ($mediosBaseTotal[$cuentaMedio] ?? 0);
            if ($peso <= 0) {
                continue;
            }

            if ($indice === $ultimoIndice) {
                $porcion = round($importe - $acumulado, 2);
            } else {
                $porcion = round($importe * ($peso / $totalBase), 2);
                $acumulado += $porcion;
            }

            if ($porcion <= 0) {
                continue;
            }

            $porciones[] = [
                'cuenta' => (int) $cuentaMedio,
                'importe' => $porcion,
            ];
        }

        return $porciones;
    }

    /**
     * Banco/caja del asiento (cualquier D/H) para anclar imputaciones sistema B.
     *
     * @param  list<object>  $lineasOp
     */
    private function resolverCuentaDisponibilidadBancoAsiento(array $lineasOp): int
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($this->motor->esCuentaBancoCaja($cuenta)) {
                return $cuenta;
            }
        }

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($this->motor->esDisponibilidad($cuenta)) {
                return $cuenta;
            }
        }

        return 0;
    }

    private function ctamovComoSubdiario(object $lineaCtamov): object
    {
        $tipo = trim((string) ($lineaCtamov->ctav_tipo ?? ''));
        $letra = trim((string) ($lineaCtamov->ctav_letra ?? ' '));
        $tipoAsiento = trim((string) ($lineaCtamov->ctav_tipo_asiento ?? ''));

        return (object) [
            'subd_fecha' => (int) ($lineaCtamov->ctav_fecha ?? 0),
            'subd_cuenta' => (int) ($lineaCtamov->ctav_cuenta ?? 0),
            'subd_contrapartida' => 0,
            'subd_nro_operacion' => (int) ($lineaCtamov->ctav_nro_asiento ?? 0),
            'subd_ref_tipo' => $tipo !== '' ? $tipo : $tipoAsiento,
            'subd_ref_letra' => $letra !== '' ? $letra : ' ',
            'subd_ref_sucursal' => (int) ($lineaCtamov->ctav_sucursal ?? 0),
            'subd_ref_nro' => (int) ($lineaCtamov->ctav_nro ?? 0),
            'subd_tipo' => $tipo,
            'subd_letra' => $letra !== '' ? $letra : ' ',
            'subd_sucursal' => (int) ($lineaCtamov->ctav_sucursal ?? 0),
            'subd_nro' => (int) ($lineaCtamov->ctav_nro ?? 0),
            'subd_desc_mov' => trim((string) ($lineaCtamov->ctav_desc_mov ?? '')),
            'subd_cod_mon' => (string) ($lineaCtamov->ctav_cod_mon ?? '1'),
            'subd_cotizacion' => (float) ($lineaCtamov->ctav_cotizacion ?? 0),
            'subd_importe' => (float) ($lineaCtamov->ctav_importe ?? 0),
            'subd_tipo_mov' => strtoupper(trim((string) ($lineaCtamov->ctav_d_h ?? 'D'))),
            'subd_o_compra' => (int) ($lineaCtamov->ctav_o_compra ?? 0),
        ];
    }

    /**
     * @param  list<object>  $lineasAsiento
     */
    private function debeProcesarAsientoComRecepcionCtamov(array $lineasAsiento): bool
    {
        if ($lineasAsiento === []) {
            return false;
        }

        $ref = $lineasAsiento[0];
        if (trim((string) ($ref->ctav_tipo ?? '')) !== 'COM'
            || (int) ($ref->ctav_o_compra ?? 0) <= 0) {
            return false;
        }

        // Solo recepción clásica con ancla 117010. COM ERP (114040, 115010, 521xxx
        // sin banco) queda fuera del mayor analítico de control (111–112).
        foreach ($lineasAsiento as $linea) {
            $cuenta = (int) ($linea->ctav_cuenta ?? 0);
            if ($cuenta >= 117010000 && $cuenta < 118000000) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recepción COM en ctamov (Anita clásico): ancla 117010 + gasto 521xxx vía PEP.
     * COM ERP (114040 anticipo, 115010 materia prima) no se mayoriza aquí.
     *
     * @param  list<object>  $lineasAsiento
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoComRecepcionCtamov(
        int $empresaId,
        array $lineasAsiento,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $nroOc = (int) ($lineasAsiento[0]->ctav_o_compra ?? $lineasOp[0]->subd_o_compra ?? 0);
        $cuentaAncla = $this->resolverCuentaAnclaComRecepcion($lineasOp);
        if ($cuentaAncla <= 0) {
            return [];
        }

        $lineasGasto = $this->filtrarComGastoRecepcion($lineasOp);
        if ($lineasGasto === []) {
            return [];
        }

        $lineas = [];

        foreach ($lineasGasto as $lineaGasto) {
            $cuenta = (int) ($lineaGasto->subd_cuenta ?? 0);
            $importe = (float) ($lineaGasto->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            $dh = strtoupper(trim((string) ($lineaGasto->subd_tipo_mov ?? 'D')));
            if (! in_array($dh, ['D', 'H'], true)) {
                $dh = 'D';
            }

            $concepto = $this->motor->conceptoImputacionCuenta($empresaId, $cuenta);
            if ($concepto <= 0 && $nroOc > 0) {
                $concepto = $this->resolverConceptoDesdeOrdenCompra($empresaId, $nroOc);
            }

            $lineas[] = $this->lineaReporte(
                $lineaGasto,
                $cuenta,
                $concepto,
                $importe,
                $dh,
                $monedaConverter,
                $monedaReporteId,
                'COM recepción ctamov',
                [
                    'cuenta_disponibilidad' => $cuentaAncla,
                    'nro_oc' => $nroOc,
                ],
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function resolverCuentaAnclaComRecepcion(array $lineasOp): int
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 117010000 && $cuenta < 118000000) {
                return $cuenta;
            }
        }

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($this->motor->esProveedor($cuenta)) {
                return $cuenta;
            }
        }

        return 0;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<object>
     */
    private function filtrarComGastoRecepcion(array $lineasOp): array
    {
        return array_values(array_filter($lineasOp, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($cuenta <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                return false;
            }

            if ($cuenta >= 117010000 && $cuenta < 118000000) {
                return false;
            }

            // Materia prima / stock (115xxx) y anticipos (114xxx): COM ERP fuera del
            // analítico de control (límite caja/banco 112010-008 → cuentas 111–112).
            if ($cuenta >= 114000000 && $cuenta < 116000000) {
                return false;
            }

            if ($this->motor->esProveedor($cuenta) || $this->motor->esDisponibilidad($cuenta)) {
                return false;
            }

            return $cuenta !== 521130001;
        }));
    }

    /**
     * @param  list<object>  $ctamovLista
     * @return list<list<object>>
     */
    private function agruparCtamovPorAsiento(array $ctamovLista): array
    {
        $porAsiento = [];

        foreach ($ctamovLista as $linea) {
            $clave = (int) ($linea->ctav_empresa ?? 0)
                .'|'.(int) ($linea->ctav_nro_asiento ?? 0)
                .'|'.(int) ($linea->ctav_fecha ?? 0);
            $porAsiento[$clave][] = $linea;
        }

        return array_values($porAsiento);
    }

    /**
     * @param  list<object>  $lineasAsiento
     * @return list<object>
     */
    private function ctamovAsientoComoLineasOp(array $lineasAsiento): array
    {
        $lineasOp = [];

        foreach ($lineasAsiento as $lineaCtamov) {
            $adaptada = $this->ctamovComoSubdiario($lineaCtamov);
            $adaptada->subd_contrapartida = $this->inferirContrapartidaCtamov(
                (int) ($lineaCtamov->ctav_cuenta ?? 0),
                $lineasAsiento,
                $lineaCtamov,
            );
            $lineasOp[] = $adaptada;
        }

        return $lineasOp;
    }

    /**
     * @param  list<object>  $lineasAsiento
     */
    private function inferirContrapartidaCtamov(int $cuenta, array $lineasAsiento, object $lineaActual): int
    {
        $mov = strtoupper(trim((string) ($lineaActual->ctav_d_h ?? '')));
        $importe = (float) ($lineaActual->ctav_importe ?? 0);

        foreach ($lineasAsiento as $linea) {
            $c = (int) ($linea->ctav_cuenta ?? 0);
            if ($c <= 0 || $c === $cuenta) {
                continue;
            }

            $dh = strtoupper(trim((string) ($linea->ctav_d_h ?? '')));
            if ($dh === $mov) {
                continue;
            }

            if (abs((float) ($linea->ctav_importe ?? 0) - $importe) < 0.01) {
                return $c;
            }
        }

        $otras = [];

        foreach ($lineasAsiento as $linea) {
            $c = (int) ($linea->ctav_cuenta ?? 0);
            if ($c > 0 && $c !== $cuenta) {
                $otras[] = $c;
            }
        }

        if ($this->motor->esDisponibilidad($cuenta)) {
            foreach ($otras as $c) {
                if ($this->motor->esProveedor($c)) {
                    return $c;
                }
            }
        }

        foreach ($otras as $c) {
            if (! $this->motor->esDisponibilidad($c)) {
                return $c;
            }
        }

        return $otras[0] ?? 0;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return array{0: ?object, 1: float}
     */
    private function resolverLineasBanco(array $lineasOp): array
    {
        $lineaBanco = null;
        $totalBancoHaber = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($this->motor->esDisponibilidad($cuenta) && $mov === 'H') {
                $lineaBanco ??= $linea;
                $totalBancoHaber += (float) ($linea->subd_importe ?? 0);
            }
        }

        return [$lineaBanco, $totalBancoHaber];
    }

    /**
     * @param  list<object>  $lineasOp
     * @param  array<string, list<object>>  $auxpagPorOp
     */
    private function debeProcesarComoPagoProveedor(
        string $refTipo,
        array $lineasOp,
        array $auxpagPorOp,
        string $claveOp,
    ): bool {
        if (in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
            return ! $this->asientoEsOperacionPuenteTransferencia($lineasOp);
        }

        return $this->asientoTieneProveedor($lineasOp) && ! empty($auxpagPorOp[$claveOp]);
    }

    /**
     * OPV/OPP/OPA banco ↔ 150000-xxx: transferencia con concepto de la puente, no pago proveedor.
     *
     * @param  list<object>  $lineasOp
     */
    private function asientoEsOperacionPuenteTransferencia(array $lineasOp): bool
    {
        $tienePuente = false;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);

            if ($this->motor->esProveedor($cuenta) || $this->motor->esProveedor($contrapartida)) {
                return false;
            }

            if ($this->motor->esCuentaPuenteTransferencia($cuenta)
                || $this->motor->esCuentaPuenteTransferencia($contrapartida)) {
                $tienePuente = true;
            }
        }

        return $tienePuente;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function asientoTieneProveedor(array $lineasOp): bool
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if ($this->motor->esProveedor($cuenta) || $this->motor->esProveedor($contrapartida)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<array{
     *   cuenta_contra: int,
     *   importe: float,
     *   dh_imputacion: string,
     *   linea: object,
     *   origen: string,
     *   cuenta_disponibilidad?: int,
     *   concepto_id?: int
     * }>
     */
    private function contrapartidasImputablesAsiento(array $lineasOp, string $refTipo = ''): array
    {
        $sumadas = $this->agruparContrapartidasPorCuenta($lineasOp, $refTipo, false);
        $deduplicadas = $this->agruparContrapartidasPorCuenta($lineasOp, $refTipo, true);
        [, $totalBanco] = $this->resolverLineasBanco($lineasOp);
        $totalSumado = array_sum(array_map(fn ($item) => (float) ($item['importe'] ?? 0), $sumadas));
        $totalDedupe = array_sum(array_map(fn ($item) => (float) ($item['importe'] ?? 0), $deduplicadas));

        if ($totalDedupe > 0 && abs($totalSumado - (2 * $totalDedupe)) <= 0.05) {
            return $deduplicadas;
        }

        if ($totalBanco > 0 && $totalSumado > $totalBanco + 0.05) {
            return $deduplicadas;
        }

        return $sumadas;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<array{
     *   cuenta_contra: int,
     *   importe: float,
     *   dh_imputacion: string,
     *   linea: object,
     *   origen: string,
     *   cuenta_disponibilidad?: int,
     *   concepto_id?: int
     * }>
     */
    private function agruparContrapartidasPorCuenta(array $lineasOp, string $refTipo, bool $dedupePorImporte): array
    {
        $porClave = [];
        $refTipo = strtoupper(trim($refTipo));

        foreach ($lineasOp as $linea) {
            foreach ($this->itemsImputacionDesdeLinea($linea, $refTipo) as $item) {
                $clave = $item['cuenta_contra'].'|'.$item['dh_imputacion']
                    .'|'.($item['cuenta_disponibilidad'] ?? 0);
                if ($dedupePorImporte) {
                    $clave .= '|'.number_format((float) ($item['importe'] ?? 0), 2, '.', '');
                }

                if (! isset($porClave[$clave])) {
                    $porClave[$clave] = $item;

                    continue;
                }

                // Con dedupePorImporte la clave ya incluye el monto: colisión = misma
                // imputación vista desde las dos piernas ctamov (banco↔113), no sumar.
                if ($dedupePorImporte) {
                    continue;
                }

                $porClave[$clave]['importe'] += $item['importe'];
            }
        }

        return array_values($porClave);
    }

    /**
     * Si el neto prorrateado ya cubre el banco del OP, quita percepciones duplicadas.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function ajustarPercepcionesFacturaOp(array $lineas, int $indicePrimeraLineaFactura, float $totalBancoHaber): array
    {
        if ($totalBancoHaber <= 0 || $indicePrimeraLineaFactura >= count($lineas)) {
            return $lineas;
        }

        $neto = 0.0;
        $percepciones = 0.0;
        for ($i = $indicePrimeraLineaFactura; $i < count($lineas); $i++) {
            $origen = (string) ($lineas[$i]['origen'] ?? '');
            $importe = (float) ($lineas[$i]['debe'] ?? 0);
            if ($origen === 'Percepción factura') {
                $percepciones += $importe;

                continue;
            }

            $neto += $importe;
        }

        if ($percepciones <= 0 || $neto + $percepciones <= $totalBancoHaber + 0.05) {
            return $lineas;
        }

        $filtradas = array_slice($lineas, 0, $indicePrimeraLineaFactura);
        for ($i = $indicePrimeraLineaFactura; $i < count($lineas); $i++) {
            if (($lineas[$i]['origen'] ?? '') === 'Percepción factura') {
                continue;
            }

            $filtradas[] = $lineas[$i];
        }

        return $filtradas;
    }

    /**
     * @return list<array{
     *   cuenta_contra: int,
     *   importe: float,
     *   dh_imputacion: string,
     *   linea: object,
     *   origen: string,
     *   cuenta_disponibilidad?: int,
     *   concepto_id?: int
     * }>
     */
    private function itemsImputacionDesdeLinea(object $linea, string $refTipo): array
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        if ($importe <= 0) {
            return [];
        }

        if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
            return [];
        }

        if (! in_array($refTipo, ['ING', 'EGR', 'IEV'], true)
            && $this->debeTraspasoDoblePierna($cuenta, $contrapartida, $refTipo)) {
            if ($this->esLineaDisparadoraTraspasoDoblePierna($linea, $cuenta, $contrapartida)) {
                $items = $this->itemsTraspasoDoblePierna($linea, $refTipo);
                if ($items !== []) {
                    return $items;
                }
            }

            return [];
        }

        if (in_array($refTipo, ['ING', 'EGR', 'IEV'], true)) {
            if ($this->debeIngresoEgresoDobleDisponibilidad($cuenta, $contrapartida)) {
                if ($this->esLineaDisparadoraIngresoEgresoDobleDisponibilidad($linea, $cuenta, $contrapartida)) {
                    return $this->itemsTraspasoDoblePierna($linea, $refTipo);
                }

                return [];
            }

            $imputacion = $this->imputacionIngresoEgreso($linea, $refTipo);
            if ($imputacion !== null) {
                return [$imputacion];
            }
        }

        if ($this->debeImputarContrapartidaOperacionDirecta($linea, $refTipo)) {
            $imputacion = $this->imputacionIngresoEgreso($linea, $refTipo);
            if ($imputacion !== null) {
                return [$imputacion];
            }
        }

        return $this->imputacionMovimientoDirectoLegacy($linea);
    }

    /**
     * ING/EGR/IEV con banco y contrapartida dentro del límite: dos piernas al 100% (como TRF).
     */
    private function debeIngresoEgresoDobleDisponibilidad(int $cuenta, int $contrapartida): bool
    {
        return $cuenta > 0
            && $contrapartida > 0
            && $this->motor->esDisponibilidad($cuenta)
            && $this->motor->esDisponibilidad($contrapartida);
    }

    /**
     * Una sola disparadora por línea subdiario (cuenta ≤ límite caja/banco).
     */
    private function esLineaDisparadoraIngresoEgresoDobleDisponibilidad(object $linea, int $cuenta, int $contrapartida): bool
    {
        return $this->motor->esDisponibilidad($cuenta);
    }

    /**
     * ING/EGR/IEV y OPV/OPP/OPA con cuenta puente 150000: imputar contrapartida + concepto como operación directa.
     */
    private function debeImputarContrapartidaOperacionDirecta(object $linea, string $refTipo): bool
    {
        $refTipo = strtoupper(trim($refTipo));

        if (! in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
            return false;
        }

        return $this->lineaOperacionPuenteTransferencia($linea);
    }

    /**
     * Pierna banco (≤ límite) contra cuenta puente 150000-xxx, sin proveedor 211.
     */
    private function lineaOperacionPuenteTransferencia(object $linea): bool
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);

        if ($this->motor->esProveedor($cuenta) || $this->motor->esProveedor($contrapartida)) {
            return false;
        }

        $tieneBanco = $this->motor->esDisponibilidad($cuenta) || $this->motor->esDisponibilidad($contrapartida);
        $tienePuente = $this->motor->esCuentaPuenteTransferencia($cuenta)
            || $this->motor->esCuentaPuenteTransferencia($contrapartida);

        return $tieneBanco && $tienePuente;
    }

    /**
     * Traspaso entre cuentas del mayor analítico (111–112): ctamov trae D y H por separado;
     * no imputar gasto/concepto externo, solo reflejar el par origen→destino una vez.
     *
     * @param  list<object>  $lineasOp
     */
    private function esAsientoTraspasoInternoDisponibilidad(array $lineasOp): bool
    {
        if (count($lineasOp) < 2) {
            return false;
        }

        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta <= 0 || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                return false;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0) {
                return false;
            }

            if ($mov === 'D') {
                $totalDebe += $importe;
            } elseif ($mov === 'H') {
                $totalHaber += $importe;
            } else {
                return false;
            }
        }

        return $totalDebe > 0 && abs($totalDebe - $totalHaber) <= 0.01;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function lineaReferenciaTraspasoInterno(array $lineasOp): object
    {
        foreach ($lineasOp as $linea) {
            if (strtoupper(trim((string) ($linea->subd_tipo_mov ?? ''))) === 'H') {
                return $linea;
            }
        }

        return $lineasOp[0];
    }

    private function debeTraspasoDoblePierna(int $cuenta, int $contrapartida, string $refTipo): bool
    {
        if ($cuenta <= 0 || $contrapartida <= 0) {
            return false;
        }

        if ($this->motor->esProveedor($cuenta) || $this->motor->esProveedor($contrapartida)) {
            return false;
        }

        $refTipo = strtoupper(trim($refTipo));

        if (! in_array($refTipo, ['0', ''], true)
            && ($this->motor->esCuentaCreditoComercialDisp($cuenta)
                || $this->motor->esCuentaCreditoComercialDisp($contrapartida))) {
            return false;
        }

        if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
            return false;
        }

        if ($refTipo === 'TRF') {
            return $this->motor->esDisponibilidadPlano($cuenta)
                && $this->motor->esDisponibilidadPlano($contrapartida);
        }

        if (in_array($refTipo, ['OPP', 'OPA', 'OPV'], true)) {
            return $this->motor->esCuentaAnaliticoControl($cuenta)
                && $this->motor->esCuentaAnaliticoControl($contrapartida);
        }

        if (in_array($refTipo, ['0', ''], true)) {
            if ($this->motor->esDisponibilidad($cuenta) && $this->motor->esDisponibilidad($contrapartida)) {
                return ! $this->motor->esCuentaCreditoComercialDisp($cuenta)
                    && ! $this->motor->esCuentaCreditoComercialDisp($contrapartida);
            }

            if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
                return false;
            }

            $tieneBancoLimite = $this->motor->esDisponibilidad($cuenta) || $this->motor->esDisponibilidad($contrapartida);
            $tieneBancoOInversion = $this->motor->esCuentaBancoCaja($cuenta)
                || $this->motor->esCuentaInversionDisp($cuenta)
                || $this->motor->esCuentaBancoCaja($contrapartida)
                || $this->motor->esCuentaInversionDisp($contrapartida);
            $tieneCreditoComercial = $this->motor->esCuentaCreditoComercialDisp($cuenta)
                || $this->motor->esCuentaCreditoComercialDisp($contrapartida);

            return $tieneBancoLimite && $tieneBancoOInversion && $tieneCreditoComercial;
        }

        if (! in_array($refTipo, ['ING', 'EGR', 'IEV'], true)) {
            return false;
        }

        return $this->motor->esCuentaBancoCaja($cuenta)
            || $this->motor->esCuentaBancoCaja($contrapartida)
            || $this->motor->esCuentaInversionDisp($cuenta)
            || $this->motor->esCuentaInversionDisp($contrapartida);
    }

    /**
     * Traspaso disponibilidad ↔ disponibilidad (TRF, FCI, fondo fijo…): dos piernas, concepto 0.
     *
     * @return list<array<string, mixed>>
     */
    private function itemsTraspasoDoblePierna(object $linea, string $refTipo): array
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        $origenCuenta = $mov === 'H' ? $cuenta : $contrapartida;
        $destinoCuenta = $mov === 'H' ? $contrapartida : $cuenta;

        return [
            [
                'cuenta_contra' => $origenCuenta,
                'importe' => $importe,
                'dh_imputacion' => 'H',
                'linea' => $linea,
                'origen' => $refTipo.' origen',
                'cuenta_disponibilidad' => $origenCuenta,
            ],
            [
                'cuenta_contra' => $destinoCuenta,
                'importe' => $importe,
                'dh_imputacion' => 'D',
                'linea' => $linea,
                'origen' => $refTipo.' destino',
                'cuenta_disponibilidad' => $destinoCuenta,
            ],
        ];
    }

    /**
     * Imputa la contrapartida (gasto, puente 150000, crédito 113…) anclada al banco del asiento.
     * Usado en ING/EGR/IEV y en OPV/OPP/OPA con cuenta puente (no proveedor 211).
     *
     * @return array<string, mixed>|null
     */
    private function imputacionIngresoEgreso(object $linea, string $refTipo): ?array
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        $cuentaBanco = $this->resolverCuentaBancoEnLinea($linea);
        if ($cuentaBanco <= 0) {
            return null;
        }

        $cuentaContra = $cuenta === $cuentaBanco ? $contrapartida : $cuenta;
        if ($cuentaContra <= 0 || $this->motor->esProveedor($cuentaContra)) {
            return null;
        }

        if ($this->debeIngresoEgresoDobleDisponibilidad($cuenta, $contrapartida)) {
            return null;
        }

        if ($this->motor->esDisponibilidad($cuentaContra)) {
            return null;
        }

        $dhImputacion = $this->dhImputacionAnitaSubdiario($mov, $cuentaContra, $cuenta);

        return [
            'cuenta_contra' => $cuentaContra,
            'importe' => $importe,
            'dh_imputacion' => $dhImputacion,
            'linea' => $linea,
            'origen' => $refTipo.' contrapartida',
            'cuenta_disponibilidad' => $cuentaBanco,
        ];
    }

    /**
     * D/H de imputación según convención Anita: subd_tipo_mov es el lado de subd_cuenta;
     * subd_contrapartida va al lado opuesto.
     */
    private function dhImputacionAnitaSubdiario(string $mov, int $cuentaImputada, int $subdCuenta): string
    {
        $mov = strtoupper(trim($mov));
        if (! in_array($mov, ['D', 'H'], true)) {
            return 'D';
        }

        if ($cuentaImputada === $subdCuenta) {
            return $mov;
        }

        return $mov === 'D' ? 'H' : 'D';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function imputacionMovimientoDirectoLegacy(object $linea): array
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        if ($this->motor->esDisponibilidad($cuenta)) {
            if ($contrapartida > 0 && ! $this->motor->esDisponibilidad($contrapartida)) {
                $cuentaContra = $contrapartida;
                $dhImputacion = $this->dhImputacionAnitaSubdiario($mov, $cuentaContra, $cuenta);
            } elseif ($this->motor->esCuentaBancoCaja($cuenta)
                || $this->motor->esCuentaInversionDisp($cuenta)) {
                return [];
            } else {
                $cuentaContra = $cuenta;
                $dhImputacion = $this->dhImputacionAnitaSubdiario($mov, $cuentaContra, $cuenta);
            }
        } elseif ($this->motor->esDisponibilidad($contrapartida)) {
            // Crédito comercial (113xxx) con contrapartida banco: imputar solo desde la pierna caja.
            if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                return [];
            }
            $cuentaContra = $cuenta;
            $dhImputacion = $this->dhImputacionAnitaSubdiario($mov, $cuentaContra, $cuenta);
        } else {
            return [];
        }

        if ($this->motor->esProveedor($cuentaContra)) {
            return [];
        }

        $cuentaDisp = 0;
        if ($this->motor->esDisponibilidad($cuenta)) {
            $cuentaDisp = $cuenta;
        } elseif ($this->motor->esDisponibilidad($contrapartida)) {
            $cuentaDisp = $contrapartida;
        }

        $item = [
            'cuenta_contra' => $cuentaContra,
            'importe' => $importe,
            'dh_imputacion' => $dhImputacion,
            'linea' => $linea,
            'origen' => 'Movimiento directo',
        ];
        if ($cuentaDisp > 0) {
            $item['cuenta_disponibilidad'] = $cuentaDisp;
        }

        return [$item];
    }

    /**
     * Banco/caja 111xxx con mayor movimiento Debe del asiento (referencia para imputar 113xxx).
     *
     * @param  list<object>  $lineasOp
     */
    private function resolverBancoReferenciaAsiento(array $lineasOp): int
    {
        $mejorCuenta = 0;
        $mejorImporte = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || $mov !== 'D' || ! $this->motor->esCuentaBancoCaja($cuenta)) {
                continue;
            }

            if ($importe > $mejorImporte) {
                $mejorImporte = $importe;
                $mejorCuenta = $cuenta;
            }
        }

        return $mejorCuenta;
    }

    /**
     * Cuenta 111xxx (caja/banco) involucrada en la línea del subdiario.
     */
    private function resolverCuentaBancoEnLinea(object $linea): int
    {
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);

        if ($this->motor->esCuentaBancoCaja($cuenta)) {
            return $cuenta;
        }

        if ($this->motor->esCuentaBancoCaja($contrapartida)) {
            return $contrapartida;
        }

        return $this->resolverCuentaDisponibilidad($linea);
    }

    /**
     * @param  list<object>  $lineasOp
     * @param  list<array<string, mixed>>  $lineas
     */
    private function agregarRemanenteContrapartidasOp(
        int $empresaId,
        array $lineasOp,
        array &$lineas,
        ?object $lineaBanco,
        ?object $lineaRef,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): void {
        $cuentaBanco = $this->resolverCuentaDisponibilidad($lineaBanco ?? $lineaRef);
        if ($cuentaBanco <= 0) {
            return;
        }

        $yaImputado = [];
        foreach ($lineas as $lineaReporte) {
            $clave = ((int) ($lineaReporte['concepto_id'] ?? 0)).'|'
                .number_format((float) (($lineaReporte['debe'] ?? 0) + ($lineaReporte['haber'] ?? 0)), 2, '.', '');
            $yaImputado[$clave] = true;
        }

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'H' || $importe <= 0) {
                continue;
            }

            if ($this->motor->esDisponibilidad($cuenta) || $this->motor->esProveedor($cuenta)) {
                continue;
            }

            if ($cuenta < 214010000 || $cuenta >= 215000000) {
                continue;
            }

            $conceptoId = $this->motor->conceptoDeCuenta($empresaId, $cuenta);
            $clave = $conceptoId.'|'.number_format($importe, 2, '.', '');
            if (isset($yaImputado[$clave])) {
                continue;
            }

            $lineas[] = $this->lineaReporte(
                $lineaBanco ?? $lineaRef ?? $linea,
                $cuenta,
                $conceptoId,
                $importe,
                'D',
                $monedaConverter,
                $monedaReporteId,
                'Remanente impuesto/retención OP',
                ['cuenta_disponibilidad' => $cuentaBanco],
            );
            $yaImputado[$clave] = true;
        }
    }

    private function resolverCuentaDisponibilidad(?object $linea): int
    {
        if ($linea === null) {
            return 0;
        }

        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $contrapartida = (int) ($linea->subd_contrapartida ?? 0);

        if ($this->motor->esDisponibilidad($cuenta)) {
            return $cuenta;
        }

        if ($this->motor->esDisponibilidad($contrapartida)) {
            return $contrapartida;
        }

        return 0;
    }

    /**
     * Asientos ctamov complejos (ej. cierre "Venta maquinas"): cada línea imputable del asiento
     * con su D/H nativo; la disponibilidad se prorratea si hay varias contrapartidas.
     *
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoMultilinea(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $refTipo,
    ): array {
        $lineas = [];
        $bancoReferencia = $this->resolverBancoReferenciaAsiento($lineasOp);
        $esVentaMaquinas = $this->esAsientoCtamovVentaMaquinas($lineasOp);
        $ventaMaquinasConSalidaDisp = $esVentaMaquinas
            && $this->tieneSalidaNetaDisponibilidadAnaliticoVentaMaquinas($lineasOp);
        $mediosAnaliticoDebe = $esVentaMaquinas
            ? $this->mediosDisponibilidadPesoAnaliticoControlVentaMaquinas($lineasOp)
            : $this->mediosDisponibilidadDebeDentroAnaliticoControl($lineasOp);

        $factorProrrateo = $ventaMaquinasConSalidaDisp
            ? 1.0
            : $this->factorProrrateoAnaliticoControlContrapartidas($lineasOp, true);
        $aplicaProrrateoParcial = $factorProrrateo < 1.0 - 1e-9;
        $prorratearDispEntreMedios = ! $ventaMaquinasConSalidaDisp
            && (count($mediosAnaliticoDebe) >= 2 || $aplicaProrrateoParcial);
        $medioPrincipalVentaMaquinas = $ventaMaquinasConSalidaDisp
            ? $this->resolverMedioPrincipalDebeVentaMaquinas($lineasOp)
            : 0;

        foreach ($lineasOp as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0 || $cuenta <= 0) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                if ($aplicaProrrateoParcial) {
                    continue;
                }

                $cuentaDisp = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
                if ($cuentaDisp <= 0) {
                    $cuentaDisp = $bancoReferencia;
                }

                $lineas[] = $this->lineaReporte(
                    $linea,
                    $cuenta,
                    $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                    $importe,
                    $mov,
                    $monedaConverter,
                    $monedaReporteId,
                    $refTipo.' crédito comercial',
                    [
                        'cuenta_disponibilidad' => $cuentaDisp,
                        'emisor' => trim((string) ($linea->subd_emisor ?? '')),
                    ],
                );

                continue;
            }

            if ($this->motor->esDisponibilidad($cuenta)) {
                continue;
            }

            if (! $this->esCuentaVisibleAsientoMultilinea($cuenta)) {
                continue;
            }

            $importeImputable = $this->aplicarFactorProrrateoAnaliticoControl($importe, $factorProrrateo);
            if ($importeImputable <= 0) {
                continue;
            }

            $conceptoId = $this->motor->conceptoImputacionCuenta($empresaId, $cuenta);
            $emisor = trim((string) ($linea->subd_emisor ?? ''));

            if ($prorratearDispEntreMedios && $mediosAnaliticoDebe !== []) {
                $cuentaPareo = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
                if ($cuentaPareo > 0
                    && isset($mediosAnaliticoDebe[$cuentaPareo])
                    && count($mediosAnaliticoDebe) === 1) {
                    $lineas[] = $this->lineaReporte(
                        $linea,
                        $cuenta,
                        $conceptoId,
                        $importeImputable,
                        $mov,
                        $monedaConverter,
                        $monedaReporteId,
                        $refTipo.' contrapartida asiento',
                        [
                            'cuenta_disponibilidad' => $cuentaPareo,
                            'emisor' => $emisor,
                        ],
                    );

                    continue;
                }

                foreach ($this->prorratearImportePorMediosCobranzaDestinoConBaseTotal(
                    $importeImputable,
                    $mediosAnaliticoDebe,
                    $mediosAnaliticoDebe,
                ) as $porcion) {
                    if ($porcion['importe'] <= 0) {
                        continue;
                    }

                    $lineas[] = $this->lineaReporte(
                        $linea,
                        $cuenta,
                        $conceptoId,
                        $porcion['importe'],
                        $mov,
                        $monedaConverter,
                        $monedaReporteId,
                        $refTipo.' contrapartida asiento',
                        [
                            'cuenta_disponibilidad' => $porcion['cuenta'],
                            'emisor' => $emisor,
                        ],
                    );
                }

                continue;
            }

            $cuentaDisp = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
            if ($cuentaDisp <= 0 && $ventaMaquinasConSalidaDisp) {
                $cuentaDisp = $medioPrincipalVentaMaquinas;
            }
            if ($cuentaDisp <= 0) {
                $cuentaDisp = $bancoReferencia > 0
                    ? $bancoReferencia
                    : (int) array_key_first($mediosAnaliticoDebe);
            }
            if ($cuentaDisp <= 0) {
                continue;
            }

            $lineas[] = $this->lineaReporte(
                $linea,
                $cuenta,
                $conceptoId,
                $importeImputable,
                $mov,
                $monedaConverter,
                $monedaReporteId,
                $refTipo.' contrapartida asiento',
                [
                    'cuenta_disponibilidad' => $cuentaDisp,
                    'emisor' => $emisor,
                ],
            );
        }

        if ($esVentaMaquinas && $aplicaProrrateoParcial && $lineas !== []) {
            $lineas = $this->reconciliarRedondeoNetoVentaMaquinasMultilinea($lineasOp, $lineas);
        }

        return $lineas;
    }

    /**
     * Cierra diferencias de centavos por round(importe × factor) línea a línea en venta máquinas.
     *
     * @param  list<object>  $lineasOp
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function reconciliarRedondeoNetoVentaMaquinasMultilinea(array $lineasOp, array $lineas): array
    {
        $netAnalitico = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'D') {
                $netAnalitico += $importe;
            } elseif ($mov === 'H') {
                $netAnalitico -= $importe;
            }
        }

        $netAnalitico = round($netAnalitico, 2);
        $netConcepto = round(array_sum(array_map(
            fn (array $ln): float => (float) ($ln['debe'] ?? 0) - (float) ($ln['haber'] ?? 0),
            $lineas,
        )), 2);

        $delta = round(-$netAnalitico - $netConcepto, 2);
        if (abs($delta) < 0.005 || abs($delta) > 2.0) {
            return $lineas;
        }

        $indice = null;
        $mejor = 0.0;
        foreach ($lineas as $i => $ln) {
            $importe = max((float) ($ln['debe'] ?? 0), (float) ($ln['haber'] ?? 0));
            if ($importe > $mejor) {
                $mejor = $importe;
                $indice = $i;
            }
        }

        if ($indice === null) {
            return $lineas;
        }

        if ((float) ($lineas[$indice]['haber'] ?? 0) > 0) {
            $lineas[$indice]['haber'] = round((float) $lineas[$indice]['haber'] - $delta, 2);
            $lineas[$indice]['disp_haber'] = round((float) ($lineas[$indice]['disp_haber'] ?? 0) - $delta, 2);
        } elseif ((float) ($lineas[$indice]['debe'] ?? 0) > 0) {
            $lineas[$indice]['debe'] = round((float) $lineas[$indice]['debe'] + $delta, 2);
            $lineas[$indice]['disp_debe'] = round((float) ($lineas[$indice]['disp_debe'] ?? 0) + $delta, 2);
        }

        return $lineas;
    }

    /**
     * Venta máquinas con salida neta en alguna disponibilidad ≤ límite (ej. wash 111010-004 Haber).
     *
     * @param  list<object>  $lineasOp
     */
    private function tieneSalidaNetaDisponibilidadAnaliticoVentaMaquinas(array $lineasOp): bool
    {
        $neto = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $neto[$cuenta] = ($neto[$cuenta] ?? 0.0) + ($mov === 'D' ? $importe : -$importe);
        }

        foreach ($neto as $saldo) {
            if ($saldo < -0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * Medio con mayor Debe neto dentro del límite analítico (ancla por defecto venta máquinas con wash).
     *
     * @param  list<object>  $lineasOp
     */
    private function resolverMedioPrincipalDebeVentaMaquinas(array $lineasOp): int
    {
        $neto = [];
        $debeBruto = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $neto[$cuenta] = ($neto[$cuenta] ?? 0.0) + ($mov === 'D' ? $importe : -$importe);
            if ($mov === 'D') {
                $debeBruto[$cuenta] = ($debeBruto[$cuenta] ?? 0.0) + $importe;
            }
        }

        $mejorCuenta = 0;
        $mejorNeto = 0.0;
        foreach ($neto as $cuenta => $saldo) {
            if ($saldo > $mejorNeto + 0.01) {
                $mejorNeto = $saldo;
                $mejorCuenta = (int) $cuenta;
            }
        }

        if ($mejorCuenta > 0) {
            return $mejorCuenta;
        }

        $mejorBruto = 0.0;
        foreach ($debeBruto as $cuenta => $importe) {
            if ($importe > $mejorBruto) {
                $mejorBruto = $importe;
                $mejorCuenta = (int) $cuenta;
            }
        }

        return $mejorCuenta;
    }

    /**
     * Venta máquinas ctamov sistema B/V (412xxx): imputación Anita por concepto de cada cuenta.
     *
     * @param  list<object>  $lineasAsiento
     * @param  list<object>  $lineasOp
     */
    private function debeProcesarAsientoCtamovVentaMaquinas(array $lineasAsiento, array $lineasOp): bool
    {
        if ($lineasAsiento === [] || $lineasOp === []) {
            return false;
        }

        foreach ($lineasAsiento as $linea) {
            if (! in_array(strtoupper(trim((string) ($linea->ctav_sistema ?? ''))), ['B', 'V'], true)) {
                return false;
            }
        }

        if ($this->debeProcesarAsientoComRecepcionCtamov($lineasAsiento)) {
            return false;
        }

        return $this->esAsientoCtamovVentaMaquinas($lineasOp);
    }

    /**
     * Venta máquinas como Anita: cada pierna con cuenta > límite caja/banco va a su concepto
     * (APLICACIONES/ORIGENES nativos). No se toman cuentas ≤ límite ni se ancla a banco narrow.
     *
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoCtamovVentaMaquinasPorConcepto(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        return $this->procesarAsientoCtamovPiernasLiteralPorConcepto(
            $empresaId,
            $lineasOp,
            $monedaConverter,
            $monedaReporteId,
            $soloMonedaOrigen,
            'Ctamov venta maquinas',
        );
    }

    /**
     * Ctamov venta (máquinas / gastro / estacionamiento): piernas nativas al concepto de cada
     * cuenta; omitir solo disponibilidad ≤ límite mayor concepto (caja/banco ancla).
     *
     * @param  list<object>  $lineasOp
     * @return list<array<string, mixed>>
     */
    private function procesarAsientoCtamovPiernasLiteralPorConcepto(
        int $empresaId,
        array $lineasOp,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        string $origen,
    ): array {
        $lineas = [];

        foreach ($lineasOp as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            // Sin cuentas menores/iguales al límite (caja/banco ancla mayor por concepto).
            if ($this->motor->esDisponibilidad($cuenta)) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if (! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            // Plano = disp: misma cuenta (no espeja contra banco ≤ límite).
            $lineas[] = $this->lineaReporte(
                $linea,
                $cuenta,
                $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                $importe,
                $mov,
                $monedaConverter,
                $monedaReporteId,
                $origen,
                [
                    'cuenta_disponibilidad' => $cuenta,
                    'emisor' => trim((string) ($linea->subd_emisor ?? '')),
                ],
            );
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $lineasAsiento
     */
    private function esCtamovAsientoSistemaB(array $lineasAsiento): bool
    {
        if ($lineasAsiento === []) {
            return false;
        }

        foreach ($lineasAsiento as $linea) {
            if (! in_array(strtoupper(trim((string) ($linea->ctav_sistema ?? ''))), ['B', 'V'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function esAsientoCtamovVentaMaquinas(array $lineasOp): bool
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 412010000 && $cuenta < 413000000) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pesos de prorrateo por |Debe − Haber| en cada disponibilidad ≤ límite analítico (venta máquinas).
     *
     * @param  list<object>  $lineasOp
     * @return array<int, float>
     */
    private function mediosDisponibilidadPesoAnaliticoControlVentaMaquinas(array $lineasOp): array
    {
        $neto = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || ! in_array($mov, ['D', 'H'], true)) {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $neto[$cuenta] = ($neto[$cuenta] ?? 0.0) + ($mov === 'D' ? $importe : -$importe);
        }

        $pesos = [];
        foreach ($neto as $cuenta => $saldo) {
            $peso = abs(round($saldo, 2));
            if ($peso > 0) {
                $pesos[(int) $cuenta] = $peso;
            }
        }

        return $pesos;
    }

    /**
     * Medios de cobranza al Debe dentro del mayor analítico (111–112 ≤ límite).
     *
     * @param  list<object>  $lineasOp
     * @return array<int, float>
     */
    private function mediosDisponibilidadDebeDentroAnaliticoControl(array $lineasOp): array
    {
        $porCuenta = [];

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($importe <= 0 || $mov !== 'D') {
                continue;
            }

            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $porCuenta[$cuenta] = round(($porCuenta[$cuenta] ?? 0.0) + $importe, 2);
        }

        return $porCuenta;
    }

    /**
     * Neto |Debe − Haber| en disponibilidades dentro del rango analítico de control.
     *
     * @param  list<object>  $lineasOp
     */
    private function totalNetoDisponibilidadAnaliticoControlAsiento(array $lineasOp): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                continue;
            }

            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'D') {
                $debe += $importe;
            } elseif ($mov === 'H') {
                $haber += $importe;
            }
        }

        return abs(round($debe - $haber, 2));
    }

    /**
     * Neto |Debe − Haber| en crédito comercial / disponibilidad fuera del rango analítico de control.
     *
     * @param  list<object>  $lineasOp
     */
    private function totalNetoDisponibilidadExcluidaAnaliticoControlAsiento(array $lineasOp): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($importe <= 0 || $cuenta <= 0) {
                continue;
            }

            $esCreditoComercial = $this->motor->esCuentaCreditoComercialDisp($cuenta);
            $esDispFueraAnalitico = $this->motor->esDisponibilidad($cuenta)
                && ! $this->motor->esCuentaAnaliticoControl($cuenta);
            if (! $esCreditoComercial && ! $esDispFueraAnalitico) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'D') {
                $debe += $importe;
            } elseif ($mov === 'H') {
                $haber += $importe;
            }
        }

        return abs(round($debe - $haber, 2));
    }

    /**
     * Suma importes de contrapartidas imputables antes de prorrateo.
     *
     * @param  list<object>  $lineasOp
     */
    private function totalImporteContrapartidasProrrateables(array $lineasOp, bool $soloVisibleMultilinea): float
    {
        $total = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            if ($this->motor->esDisponibilidad($cuenta)) {
                continue;
            }

            if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                continue;
            }

            if ($soloVisibleMultilinea && ! $this->esCuentaVisibleAsientoMultilinea($cuenta)) {
                continue;
            }

            $total += $importe;
        }

        return round($total, 2);
    }

    /**
     * Neto Debe − Haber de contrapartidas imputables (sin disp ni 113xxx).
     *
     * @param  list<object>  $lineasOp
     */
    private function totalNetoContrapartidasProrrateables(array $lineasOp, bool $soloVisibleMultilinea): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $importe = (float) ($linea->subd_importe ?? 0);
            if ($cuenta <= 0 || $importe <= 0) {
                continue;
            }

            if ($this->motor->esDisponibilidad($cuenta)) {
                continue;
            }

            if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
                continue;
            }

            if ($soloVisibleMultilinea && ! $this->esCuentaVisibleAsientoMultilinea($cuenta)) {
                continue;
            }

            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov === 'D') {
                $debe += $importe;
            } elseif ($mov === 'H') {
                $haber += $importe;
            }
        }

        return round($debe - $haber, 2);
    }

    /**
     * Prorratea contrapartidas al neto de caja/banco dentro del rango analítico cuando hay
     * movimiento en cuentas de disponibilidad excluidas del export (113xxx, disp > límite).
     *
     * @param  list<object>  $lineasOp
     */
    private function factorProrrateoAnaliticoControlContrapartidas(array $lineasOp, bool $soloVisibleMultilinea): float
    {
        $dispAnalitico = $this->totalNetoDisponibilidadAnaliticoControlAsiento($lineasOp);
        if ($dispAnalitico <= 0) {
            return 1.0;
        }

        $dispExcluido = $this->totalNetoDisponibilidadExcluidaAnaliticoControlAsiento($lineasOp);
        if ($dispExcluido <= 0) {
            return 1.0;
        }

        $dispCompleto = round($dispAnalitico + $dispExcluido, 2);
        $totalContra = $this->totalImporteContrapartidasProrrateables($lineasOp, $soloVisibleMultilinea);
        if ($totalContra <= 0 || $dispCompleto <= $dispAnalitico + 0.01) {
            return 1.0;
        }

        // Solo imputaciones tipo pago: contrapartida ≈ caja analítica + crédito comercial excluido.
        if (abs($totalContra - $dispCompleto) > 0.05) {
            // Venta máquinas con piernas fuera del límite (113/114…): el factor se ancla
            // solo al neto ≤ límite vs neto de contrapartidas visibles, sin mezclar el 113
            // (sobre el límite) en el denominador.
            if ($dispExcluido > 0 && $this->esAsientoCtamovVentaMaquinas($lineasOp)) {
                $netoContra = abs($this->totalNetoContrapartidasProrrateables($lineasOp, $soloVisibleMultilinea));
                if ($netoContra > 0.05) {
                    return round($dispAnalitico / $netoContra, 8);
                }

                return 1.0;
            }

            return 1.0;
        }

        return round($dispAnalitico / $dispCompleto, 8);
    }

    private function aplicarFactorProrrateoAnaliticoControl(float $importe, float $factor): float
    {
        if ($factor >= 1.0 - 1e-9) {
            return round($importe, 2);
        }

        return round($importe * $factor, 2);
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function debeProcesarAsientoMultilinea(array $lineasOp, string $refTipo): bool
    {
        if (! in_array(strtoupper(trim($refTipo)), ['0', ''], true)) {
            return false;
        }

        if (count($lineasOp) <= 2) {
            return false;
        }

        $lineasDisp = 0;
        $lineasContra = 0;
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta <= 0) {
                continue;
            }
            if ($this->motor->esDisponibilidad($cuenta)) {
                $lineasDisp++;
            } elseif ($this->esCuentaVisibleAsientoMultilinea($cuenta)) {
                $lineasContra++;
            }
        }

        return $lineasDisp >= 1 && ($lineasDisp + $lineasContra) >= 2;
    }

    private function esCuentaVisibleAsientoMultilinea(int $cuenta): bool
    {
        if ($cuenta <= 0) {
            return false;
        }

        if ($cuenta === 211010001) {
            return false;
        }

        if ($this->motor->esProveedor($cuenta)) {
            return $cuenta !== 211010001;
        }

        if ($cuenta >= 400000000 && $cuenta < 500000000) {
            return true;
        }

        if ($cuenta >= 500000000 && $cuenta < 600000000 && $cuenta !== 521130001) {
            return true;
        }

        if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
            return true;
        }

        if ($cuenta >= 211010000 && $cuenta < 212000000) {
            return true;
        }

        // Anita imputa_ctav: líneas ctamov con cuenta > límite caja/banco (IVA débito gastro/estac., retenciones…).
        if ($cuenta >= 214010000 && $cuenta < 215000000) {
            return true;
        }

        // IVA crédito fiscal (114010-114019) en ventas ctamov (estacionamiento, etc.).
        if ($cuenta >= 114010000 && $cuenta < 114020000) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<object>  $auxpag
     * @return list<object>
     */
    private function filtrarAplicacionesFactura(array $auxpag, float $totalBancoHaber = 0.0): array
    {
        $facturas = array_values(array_filter($auxpag, fn ($f) => $this->esFactura($f)));
        $cheques = $this->filtrarAplicacionesCheque($auxpag);
        $totalCheques = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $cheques));

        if ($totalCheques > 0 && $totalBancoHaber > 0 && abs($totalCheques - $totalBancoHaber) < 0.05 && $facturas === []) {
            return [];
        }

        $tieneFis = false;
        foreach ($facturas as $fila) {
            if (strtoupper(trim((string) ($fila->axp_tipo_ap ?? ''))) === 'FIS') {
                $tieneFis = true;
                break;
            }
        }

        if ($tieneFis) {
            $facturas = array_values(array_filter(
                $facturas,
                fn ($f) => strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))) !== 'IBP',
            ));
        }

        if ($this->auxpagTieneFga($auxpag)) {
            $facturas = array_values(array_filter(
                $facturas,
                fn ($f) => ! in_array(
                    strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))),
                    ['FIS', 'IBP'],
                    true,
                ),
            ));
        }

        return $facturas;
    }

    /**
     * @param  list<object>  $auxpag
     * @return list<object>
     */
    private function filtrarAplicacionesCheque(array $auxpag): array
    {
        if ($this->auxpagTieneFga($auxpag)) {
            return [];
        }

        return array_values(array_filter(
            $auxpag,
            fn ($f) => strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))) === 'CHP',
        ));
    }

    /**
     * Medios de pago bancarios en auxpag: CHP + transferencias TMB/TMK/TMR.
     * Usar para decidir imputación cuando no hay gasto de factura recuperable.
     * No usar en totalBancoEfectivoOp (ahí solo CHP, como Anita).
     *
     * @param  list<object>  $auxpag
     * @return list<object>
     */
    private function filtrarAplicacionesMedioPagoBancario(array $auxpag): array
    {
        if ($this->auxpagTieneFga($auxpag)) {
            return [];
        }

        return array_values(array_filter(
            $auxpag,
            fn ($f) => in_array(
                strtoupper(trim((string) ($f->axp_tipo_ap ?? ''))),
                ['CHP', 'TMB', 'TMK', 'TMR'],
                true,
            ),
        ));
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function auxpagTieneFga(array $auxpag): bool
    {
        foreach ($auxpag as $fila) {
            if (strtoupper(trim((string) ($fila->axp_tipo_ap ?? ''))) === 'FGA') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function debeImputarChequeProveedor(
        float $totalCheques,
        float $totalFacturas,
        float $totalBancoHaber,
        array $auxpag,
    ): bool {
        if ($totalCheques <= 0 || $totalBancoHaber <= 0) {
            return false;
        }

        if ($this->auxpagTieneFga($auxpag)) {
            return false;
        }

        if ($this->auxpagTieneGastoImputable($auxpag)) {
            return false;
        }

        if ($totalFacturas <= 0) {
            return true;
        }

        if (abs($totalCheques - $totalBancoHaber) >= 0.05) {
            return false;
        }

        $facturas = array_values(array_filter($auxpag, fn ($f) => $this->esFactura($f)));

        return $this->facturasImputanChequeProveedor($facturas);
    }

    /**
     * CHP→117010 cuando el COM asociado no tiene gasto 521xxx (solo 117010 u adelantado).
     *
     * @param  list<object>  $facturas
     */
    private function facturasImputanChequeProveedor(array $facturas): bool
    {
        if ($facturas === []) {
            return true;
        }

        foreach ($facturas as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if ($tipoAp === 'FGA') {
                return false;
            }

            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto === []) {
                if ($this->cargarGastoDesdeAplicacion($aplicacion) !== []) {
                    return false;
                }

                continue;
            }

            foreach ($comGasto as $linea) {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                if ($cuenta >= 115000000 && $cuenta < 117000000) {
                    return false;
                }

                // Puente bienes de uso / activo (ej. 123010030): no tratar como CHP gaming.
                if ($cuenta >= 123000000 && $cuenta < 124000000) {
                    return false;
                }

                if ($cuenta >= 500000000 && $cuenta < 600000000 && $cuenta !== 521130001) {
                    if ($cuenta < 117000000 || $cuenta >= 118000000) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function conceptoImputacionGasto(
        int $empresaId,
        int $cuenta,
        string $tipoAp,
        int $nroOc = 0,
        string $proveedor = '',
        ?object $aplicacion = null,
    ): int {
        $tipoAp = strtoupper(trim($tipoAp));

        // 114020-009 / 114040: cuentas puente sin concepto → axp_concepto u OC.
        if (($tipoAp === 'FIS' && $cuenta === 114020009)
            || ($cuenta >= 114040000 && $cuenta < 114050000)) {
            if ($cuenta >= 114040000 && $cuenta < 114050000) {
                $concepto = $this->motor->conceptoDeCuenta($empresaId, $cuenta);
                if ($concepto > 0) {
                    return $concepto;
                }
            }

            if ($nroOc > 0) {
                $desdeOc = $this->resolverConceptoDesdeOrdenCompra($empresaId, $nroOc, $proveedor);
                if ($desdeOc > 0) {
                    return $desdeOc;
                }
            }

            return $this->conceptoReclasificacionCuentaTransitoria($empresaId, $aplicacion, $cuenta);
        }

        $concepto = $this->motor->conceptoImputacionCuenta($empresaId, $cuenta);
        if ($concepto > 0) {
            return $concepto;
        }

        // 117010 u otras sin concepto residuales (si no entraron al branch solo-117010).
        if ($cuenta >= 117010000 && $cuenta < 118000000) {
            return $this->conceptoReclasificacionCuentaTransitoria($empresaId, $aplicacion, $cuenta);
        }

        return 0;
    }

    /**
     * Concepto para cuentas puente (114020 / 114040 / 117010) sin conceptogasto:
     * 1) axp_concepto del renglón de pago; 2) concepto de cuentas del comprobante aplicado.
     */
    private function conceptoReclasificacionCuentaTransitoria(
        int $empresaId,
        ?object $aplicacion,
        int $cuentaTransitoria,
    ): int {
        if ($aplicacion === null) {
            return 0;
        }

        $axp = (int) ($aplicacion->axp_concepto ?? 0);
        if ($axp > 0) {
            return $axp;
        }

        $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);
        foreach ($sub as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov !== 'D' || $cuenta <= 0 || $cuenta === $cuentaTransitoria) {
                continue;
            }
            if ($this->motor->esProveedor($cuenta) || $this->motor->esDisponibilidad($cuenta)) {
                continue;
            }
            // Preferir gasto de resultado; si no, 114010-xxx con concepto (ej. gastronomía).
            if (! $this->lineasGastoIncluyenResultadoCompras([$linea])
                && ! ($cuenta >= 114010000 && $cuenta < 114040000)) {
                continue;
            }
            $concepto = $this->motor->conceptoDeCuenta($empresaId, $cuenta);
            if ($concepto > 0) {
                return $concepto;
            }
        }

        return 0;
    }

    /**
     * FIS/FIB anticipada: concepto desde imputación contable de la COM de la OC (PEP).
     */
    private function resolverConceptoDesdeOrdenCompra(int $empresaId, int $nroOc, string $proveedor = ''): int
    {
        if ($nroOc <= 0) {
            return 0;
        }

        $cacheKey = $proveedor.'|'.$nroOc;
        if (isset($this->conceptoPorOcCache[$cacheKey])) {
            return $this->conceptoPorOcCache[$cacheKey];
        }

        $this->consultasBridgeIndividuales++;
        $aplicaciones = $this->reader->cargarAplicpedPorReferencia(
            'PEP',
            'X',
            0,
            $nroOc,
            $proveedor,
            $this->erroresBridge,
        );

        foreach ($aplicaciones as $apl) {
            if (strtoupper(trim((string) ($apl->aplp_tipo ?? ''))) !== 'COM') {
                continue;
            }

            $comSub = $this->reader->cargarComSubdiario(
                $empresaId,
                'COM',
                trim((string) ($apl->aplp_letra ?? 'X')),
                (int) ($apl->aplp_sucursal ?? 0),
                (int) ($apl->aplp_nro ?? 0),
                $this->erroresBridge,
            );

            foreach ($this->filtrarComGasto($comSub) as $linea) {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                if ($cuenta <= 0) {
                    continue;
                }

                $concepto = $this->motor->conceptoImputacionCuenta($empresaId, $cuenta);
                if ($concepto > 0) {
                    $this->conceptoPorOcCache[$cacheKey] = $concepto;

                    return $concepto;
                }
            }
        }

        $this->conceptoPorOcCache[$cacheKey] = $this->reader->conceptoDesdeOrdenCompra(
            $empresaId,
            $nroOc,
            $this->erroresBridge,
        );

        return $this->conceptoPorOcCache[$cacheKey];
    }

    /**
     * CHP directo: cuenta de la pierna no-caja del asiento (subdiario).
     * Si hay gaming/prepaga asociados, prevalecen; si no, 117010 solo como último fallback.
     *
     * @param  list<object>  $auxpag
     * @param  list<object>  $lineasOp
     */
    private function cuentaChequeMayorConcepto(
        ?object $cheque,
        array $auxpag = [],
        int $empresaId = 0,
        array $lineasOp = [],
    ): int {
        $cuentaGaming = $this->resolverCuentaGastoGamingDesdeOp($auxpag);
        if ($cuentaGaming > 0) {
            return $cuentaGaming;
        }

        // FIU/bienes de uso sin subdiario recuperable: no caer en 211 proveedores.
        $cuentaBienesUso = $this->resolverCuentaBienesUsoOpSinGasto($auxpag);
        if ($cuentaBienesUso > 0) {
            return $cuentaBienesUso;
        }

        if ($cheque !== null) {
            $prov = trim((string) ($cheque->axp_pro ?? ''));
            if ($prov !== '' && isset($this->cuentaPrepagaPorProveedor[$prov])) {
                return $this->cuentaPrepagaPorProveedor[$prov];
            }
        }

        $cuentaAsiento = $this->cuentaContrapartidaNoDisponibilidadAsiento($lineasOp);
        if ($cuentaAsiento > 0 && ! $this->motor->esProveedor($cuentaAsiento)) {
            return $cuentaAsiento;
        }

        return 117010001;
    }

    /**
     * OP con aplicación FIU (u otra fac. ind.) sin gasto en Anita: puente bienes de uso.
     *
     * @param  list<object>  $auxpag
     */
    private function resolverCuentaBienesUsoOpSinGasto(array $auxpag): int
    {
        foreach ($auxpag as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, ['FIU', 'FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA'], true)) {
                continue;
            }

            if ($this->cargarGastoDesdeAplicacion($aplicacion) !== []) {
                continue;
            }

            return 123010001;
        }

        return 0;
    }

    /**
     * Pierna del asiento que no es caja/banco ≤ límite (ej. CHP: 111 H / 113 Debe).
     *
     * @param  list<object>  $lineasOp
     */
    private function cuentaContrapartidaNoDisponibilidadAsiento(array $lineasOp): int
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);

            if ($this->motor->esDisponibilidad($cuenta)
                && $contrapartida > 0
                && ! $this->motor->esDisponibilidad($contrapartida)) {
                return $contrapartida;
            }

            if ($this->motor->esDisponibilidad($contrapartida)
                && $cuenta > 0
                && ! $this->motor->esDisponibilidad($cuenta)) {
                return $cuenta;
            }
        }

        return 0;
    }

    /**
     * Cache proveedor → cuenta 521060 prepaga desde FNS/DIS/CIS del período (CHP OSDE/Galeno).
     *
     * @param  list<object>  $auxpagLista
     */
    private function precargarCuentaPrepagaPorProveedor(array $auxpagLista): void
    {
        foreach ($auxpagLista as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, ['FNS', 'DIS', 'CIS'], true)) {
                continue;
            }

            $prov = trim((string) ($aplicacion->axp_pro ?? ''));
            if ($prov === '') {
                continue;
            }

            $cuenta = $this->resolverCuentaGastoDesdeAplicacionPrepaga($aplicacion);
            if ($cuenta > 0) {
                $this->cuentaPrepagaPorProveedor[$prov] = $cuenta;
            }
        }
    }

    private function resolverCuentaGastoDesdeAplicacionPrepaga(object $aplicacion): int
    {
        foreach ($this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion)) as $lineaGasto) {
            $cuenta = (int) ($lineaGasto->subd_cuenta ?? 0);
            if ($this->esCuentaGastoPrepagaSueldos($cuenta)) {
                return $cuenta;
            }
        }

        foreach ($this->filtrarComGasto($this->cargarSubdiarioComprobanteAplicacion($aplicacion)) as $lineaGasto) {
            $cuenta = (int) ($lineaGasto->subd_cuenta ?? 0);
            if ($this->esCuentaGastoPrepagaSueldos($cuenta)) {
                return $cuenta;
            }
        }

        return 0;
    }

    private function esCuentaGastoPrepagaSueldos(int $cuenta): bool
    {
        return $cuenta >= 521060000 && $cuenta < 521070000;
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function auxpagTieneGastoImputable(array $auxpag): bool
    {
        foreach ($auxpag as $aplicacion) {
            if (! $this->esComprobanteAplicadoPago($aplicacion)) {
                continue;
            }

            if ($this->cargarGastoDesdeAplicacion($aplicacion) !== []) {
                return true;
            }
        }

        return false;
    }

    private function esComprobanteAplicadoPago(object $aplicacion): bool
    {
        return $this->esFactura($aplicacion);
    }

    /**
     * @param  list<object>  $auxpag
     */
    private function resolverCuentaGastoGamingDesdeOp(array $auxpag): int
    {
        foreach ($auxpag as $aplicacion) {
            if (! $this->esComprobanteAplicadoPago($aplicacion)) {
                continue;
            }

            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            foreach ($this->cargarGastoDesdeAplicacion($aplicacion) as $lineaGasto) {
                $cuenta = $this->cuentaVisibleDesdeDocumentoGasto($lineaGasto, $tipoAp);
                if ($cuenta >= 114040000 && $cuenta < 114050000) {
                    return $cuenta;
                }

                if ($cuenta >= 521000000 && $cuenta < 522000000 && $cuenta !== 521130001) {
                    return $cuenta;
                }
            }
        }

        // FNB con OC pero sin 521/114040 en gasto: usar la cuenta real de la COM
        // (ej. 123010030 bienes de uso). Solo si no hay COM útil → 521240002 (gaming legacy).
        foreach ($auxpag as $aplicacion) {
            if (strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? ''))) !== 'FNB') {
                continue;
            }

            if ($this->ordenComDesdeAplicacion($aplicacion) <= 0) {
                continue;
            }

            foreach ($this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion)) as $lineaCom) {
                $cuentaCom = (int) ($lineaCom->subd_cuenta ?? 0);
                if ($cuentaCom > 0 && $cuentaCom !== 521130001) {
                    return $cuentaCom;
                }
            }

            return 521240002;
        }

        return 0;
    }

    private function claveAgrupacionGastoFactura(
        int $cuentaGasto,
        int $conceptoGasto,
        string $tipoAp,
        bool $fisAdelantado,
        bool $gastoAdelantado,
        float $importe,
        bool $anticipo114040 = false,
        int $cuentaOrigen = 0,
    ): string {
        if ($anticipo114040 && $cuentaOrigen >= 114010000 && $cuentaOrigen < 114050000) {
            $prefijoOrigen = $cuentaOrigen >= 114040000 ? '114040' : '114010';

            return $cuentaGasto.'|'.$conceptoGasto.'|orig'.$prefijoOrigen;
        }

        if ($fisAdelantado || ($tipoAp === 'FIS' && $gastoAdelantado && $cuentaGasto === 114020009)) {
            return $cuentaGasto.'|'.$conceptoGasto.'|'.number_format($importe, 2, '.', '');
        }

        return $cuentaGasto.'|'.$conceptoGasto;
    }

    /**
     * Cierra diferencias cuenta a cuenta contra el mayor plano (subdiario + ctamov).
     *
     * @param  list<array<string, mixed>>  $lineasReporte
     * @param  array<int, array<string, mixed>>  $mayorPlano
     * @return list<array<string, mixed>>
     */
    private function completarRemanenteMayorPlano(
        int $empresaId,
        array $lineasReporte,
        array $mayorPlano,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $imputado = [];

        foreach ($lineasReporte as $ln) {
            $disp = (int) ($ln['cuenta_disponibilidad'] ?? 0);
            // Solo disponibilidad ≤ tope mayor concepto. Venta máquinas ancla disp=cuenta
            // concepto (>tope): no entra al cuadre del plano caja/banco.
            if ($disp <= 0 || ! $this->motor->esDisponibilidad($disp)) {
                continue;
            }

            if (! isset($imputado[$disp])) {
                $imputado[$disp] = ['debe' => 0.0, 'haber' => 0.0];
            }

            $imputado[$disp]['debe'] += (float) ($ln['disp_debe'] ?? 0);
            $imputado[$disp]['haber'] += (float) ($ln['disp_haber'] ?? 0);
        }

        $remanente = [];

        foreach ($mayorPlano as $cuenta => $plano) {
            if ((int) $cuenta <= 0) {
                continue;
            }

            $imp = $imputado[$cuenta] ?? ['debe' => 0.0, 'haber' => 0.0];
            $dDebe = round((float) ($plano['debe'] ?? 0) - $imp['debe'], 2);
            $dHaber = round((float) ($plano['haber'] ?? 0) - $imp['haber'], 2);

            // Solo completar faltante (plano > imputado). El exceso imputado (inflación
            // narrow / sistema B) no genera línea "Ajuste mayor plano" de signo negativo.
            if ($dDebe >= 0.05) {
                $remanente[] = $this->lineaRemanenteMayorPlano(
                    $empresaId,
                    $cuenta,
                    $dDebe,
                    'D',
                    $lineasReporte,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if ($dHaber >= 0.05) {
                $remanente[] = $this->lineaRemanenteMayorPlano(
                    $empresaId,
                    $cuenta,
                    $dHaber,
                    'H',
                    $lineasReporte,
                    $monedaConverter,
                    $monedaReporteId,
                );
            }
        }

        return $remanente;
    }

    private function lineaRemanenteMayorPlano(
        int $empresaId,
        int $cuenta,
        float $importe,
        string $dh,
        array $lineasReporte,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): array {
        $origen = (object) [
            'subd_fecha' => 0,
            'subd_cod_mon' => '1',
            'subd_cotizacion' => 0.0,
            'subd_nro_operacion' => 0,
            'subd_ref_tipo' => '',
            'subd_ref_letra' => ' ',
            'subd_ref_sucursal' => 0,
            'subd_ref_nro' => 0,
            'subd_desc_mov' => 'Ajuste mayor plano',
        ];

        return $this->lineaReporte(
            $origen,
            $cuenta,
            $this->conceptoRemanenteMayorPlano($empresaId, $cuenta, $lineasReporte),
            $importe,
            $dh,
            $monedaConverter,
            $monedaReporteId,
            'Remanente mayor plano',
            ['cuenta_disponibilidad' => $cuenta],
        );
    }

    /**
     * Caja/banco sin concepto propio: usar el concepto dominante ya imputado en esa
     * disponibilidad (p. ej. cobranzas MP → VENTA 47).
     *
     * @param  list<array<string, mixed>>  $lineasReporte
     */
    private function conceptoRemanenteMayorPlano(int $empresaId, int $cuentaDisp, array $lineasReporte): int
    {
        $conceptoCuenta = $this->motor->conceptoImputacionCuenta($empresaId, $cuentaDisp);
        if ($conceptoCuenta > 0) {
            return $conceptoCuenta;
        }

        $porConcepto = [];

        foreach ($lineasReporte as $ln) {
            if ((int) ($ln['cuenta_disponibilidad'] ?? 0) !== $cuentaDisp) {
                continue;
            }

            if (($ln['origen'] ?? '') === 'Remanente mayor plano') {
                continue;
            }

            $conceptoId = (int) ($ln['concepto_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }

            $neto = abs((float) ($ln['disp_debe'] ?? 0)) + abs((float) ($ln['disp_haber'] ?? 0));
            $porConcepto[$conceptoId] = ($porConcepto[$conceptoId] ?? 0.0) + $neto;
        }

        if ($porConcepto === []) {
            return 0;
        }

        arsort($porConcepto);

        return (int) array_key_first($porConcepto);
    }

    /**
     * FIS con nro_interno en auxpag: comprobante de compra (t_comp). Puede tener COM
     * vía aplicped (honorarios con OC) o imputarse directo desde su subdiario/ctamov.
     */
    private function aplicacionEsFisComprobanteCompras(object $aplicacion): bool
    {
        return strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? ''))) === 'FIS'
            && (int) ($aplicacion->axp_nro_interno ?? 0) > 0;
    }

    /**
     * Indica si la factura FIS resuelve gasto vía cadena COM (aplicped).
     */
    private function aplicacionTieneComGasto(object $aplicacion): bool
    {
        return $this->filtrarComGasto($this->cargarComDesdeFacturaEfectivo($aplicacion)) !== [];
    }

    private function claveProcesamientoPagoOp(string $claveOp, int $nroAsiento): string
    {
        if ($nroAsiento > 0) {
            return $claveOp.'|ASI|'.$nroAsiento;
        }

        return $claveOp;
    }

    /**
     * @param  list<object>  $lineasOp
     * @return list<object>
     */
    private function filtrarLineasOpPorAsiento(array $lineasOp, int $nroAsiento): array
    {
        if ($nroAsiento <= 0) {
            return $lineasOp;
        }

        $filtradas = array_values(array_filter(
            $lineasOp,
            fn ($linea) => (int) ($linea->subd_nro_operacion ?? 0) === $nroAsiento,
        ));

        return $filtradas !== [] ? $filtradas : $lineasOp;
    }

    /**
     * @param  list<object>  $auxpag
     * @param  list<object>  $lineasOp
     */
    private function totalBancoEfectivoOp(array $auxpag, array $lineasOp): float
    {
        $cheques = $this->filtrarAplicacionesCheque($auxpag);
        if ($cheques !== []) {
            return array_sum(array_map(fn ($c) => (float) ($c->axp_monto_ap ?? 0), $cheques));
        }

        [, $totalBanco] = $this->resolverLineasBanco($lineasOp);

        return $totalBanco;
    }

    /**
     * Coeficiente pago/factura para prorratear la COM en el desglose del cheque.
     * Prioriza el banco efectivo (pago a cuenta) sobre axp_monto_ap cuando este
     * último es residual y el cheque cubre más.
     */
    private function coeficientePagoSobreFactura(
        object $aplicacion,
        float $pesoDocumental,
        float $montoBancoFactura,
        float $totalBancoOp,
    ): float {
        if ($pesoDocumental <= 0) {
            return 1.0;
        }

        $coefBanco = min(1.0, max($montoBancoFactura, $totalBancoOp) / $pesoDocumental);
        $pagoAplicado = (float) ($aplicacion->axp_monto_ap ?? 0);

        if ($pagoAplicado <= 0.01) {
            return $coefBanco;
        }

        if ($pagoAplicado >= $pesoDocumental * 0.995) {
            return 1.0;
        }

        $coefAplicado = min(1.0, $pagoAplicado / $pesoDocumental);

        return max($coefAplicado, $coefBanco);
    }

    /**
     * @param  list<object>  $sub
     * @return array<int, float>
     */
    private function percepcionesRawDesdeLineasSubdiario(array $sub): array
    {
        $porCuenta = [];

        foreach ($sub as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta < 214010000 || $cuenta >= 215000000) {
                continue;
            }

            if ($this->motor->esCuentaVariacionCapital($cuenta)) {
                continue;
            }

            $porCuenta[$cuenta] = ($porCuenta[$cuenta] ?? 0.0) + (float) ($linea->subd_importe ?? 0);
        }

        return $porCuenta;
    }

    /**
     * @param  list<object>  $sub
     * @return array<int, float>
     */
    private function ivaCreditoRawDesdeLineasSubdiario(array $sub): array
    {
        $porCuenta = [];

        foreach ($sub as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || ! $this->esCuentaIvaCreditoFiscalCompras($cuenta)) {
                continue;
            }

            $porCuenta[$cuenta] = ($porCuenta[$cuenta] ?? 0.0) + (float) ($linea->subd_importe ?? 0);
        }

        return $porCuenta;
    }

    /**
     * Percepciones/retenciones 214xxx del subdiario del comprobante, SIN prorratear
     * (importe crudo por cuenta). El prorrateo al pago real se aplica en procesarPago
     * usando la base del comprobante (neto COM + percepciones), para no depender del
     * importe aplicado (axp_monto_ap), que en un pago a cuenta es una fracción mínima.
     *
     * @return array<int, float> cuenta => importe crudo del subdiario
     */
    private function percepcionesRawDesdeAplicacion(object $aplicacion): array
    {
        $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);
        $porCuenta = [];

        foreach ($sub as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta < 214010000 || $cuenta >= 215000000) {
                continue;
            }

            if ($this->motor->esCuentaVariacionCapital($cuenta)) {
                continue;
            }

            $porCuenta[$cuenta] = ($porCuenta[$cuenta] ?? 0.0) + (float) ($linea->subd_importe ?? 0);
        }

        return $porCuenta;
    }

    private function cargarGastoDesdeAplicacion(object $aplicacion): array
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));

        if ($tipoAp === 'FIS') {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== []) {
                return $comGasto;
            }

            $sub = $this->enriquecerSubdiarioFacturaConCtamov(
                $aplicacion,
                $this->cargarSubdiarioComprobanteAplicacion($aplicacion),
            );

            $desdeSub = $this->resolverGastoFisSubdiario($sub);
            // Preferir COM vía PEP con gasto 521/115 sobre adelantado 114020/114040 en la FIS.
            $comViaPepResultado = $this->filtrarLineasResultadoDesdeCom(
                $this->cargarComDesdeFacturaViaPepHermano($aplicacion),
            );
            if ($comViaPepResultado !== []
                && ($desdeSub === [] || ! $this->lineasGastoIncluyenResultadoCompras($desdeSub))) {
                return $comViaPepResultado;
            }

            if ($desdeSub !== []) {
                return $desdeSub;
            }

            // FC a recibir (solo 211010-004): FIS→PEP←COM en aplicped (mismo proveedor).
            return $this->filtrarComGasto($this->cargarComDesdeFacturaViaPepHermano($aplicacion));
        }

        if ($tipoAp === 'FGA') {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== []) {
                return $comGasto;
            }

            $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);
            $lineas = $this->filtrarLineasFgaMayorConcepto($sub);
            if ($lineas !== []) {
                return $lineas;
            }

            return $this->filtrarComGasto($this->cargarComDesdeFacturaViaPepHermano($aplicacion));
        }

        // --- FIX-FIB-COM-VIA-PEP (2026-08-06) — ver docs/mayor-concepto-fix-fib-com-via-pep.md ---
        // FIU = Fac. Ind. Bienes de Uso (misma familia FIB…; no estaba en la lista).
        if (in_array($tipoAp, ['FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA', 'FIU'], true)) {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== [] && $this->lineasGastoIncluyenResultadoCompras($comGasto)) {
                return $comGasto;
            }

            $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);
            $adelantada = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);

            // FIB con solo IVA 114010 (concepto 63): preferir gasto COM vía PEP (ej. monitor→521xxx).
            $comViaPepResultado = $this->filtrarLineasResultadoDesdeCom(
                $this->cargarComDesdeFacturaViaPepHermano($aplicacion),
            );
            if ($comViaPepResultado !== []
                && ($adelantada === [] || ! $this->lineasGastoIncluyenResultadoCompras($adelantada))) {
                return $this->escalarComResultadoANetoFacturaAdelantada($comViaPepResultado, $sub);
            }

            if ($adelantada !== []) {
                return $adelantada;
            }

            if ($comGasto !== []) {
                return $comGasto;
            }

            return $this->filtrarComGasto($this->cargarComDesdeFacturaViaPepHermano($aplicacion));
        }
        // --- /FIX-FIB-COM-VIA-PEP ---

        $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
        if ($comGasto !== []) {
            return $comGasto;
        }

        $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);

        // FNB/FNC/etc. sin COM: primera factura del legajo anticipado (114040 en subdiario).
        $adelantada = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
        if ($this->subTieneAnticipo114040($adelantada)) {
            return $this->filtrarLineasAnticipoProveedor($adelantada);
        }

        $neto = $this->filtrarLineasGastoNetoComprobanteCompras($sub);
        if ($neto !== []) {
            return $neto;
        }

        return $this->filtrarComGasto($this->cargarComDesdeFacturaViaPepHermano($aplicacion));
    }

    /**
     * Gasto neto imputable desde subdiario del comprobante (sin COM): cuentas de
     * resultado 115+/521+. Excluye 114010-114039 (IVA crédito fiscal, p. ej. NC
     * descontada en FDT) y percepciones 214xxx (se prorratean aparte).
     *
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarLineasGastoNetoComprobanteCompras(array $sub): array
    {
        return array_values(array_filter($sub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta <= 0) {
                return false;
            }

            if ($this->motor->esProveedor($cuenta) || $this->motor->esDisponibilidad($cuenta)) {
                return false;
            }

            if ($cuenta >= 114010000 && $cuenta < 114040000) {
                return false;
            }

            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return ($cuenta >= 115000000 && $cuenta < 600000000 && $cuenta !== 521130001)
                || ($cuenta >= 123000000 && $cuenta < 124000000);
        }));
    }

    private function claveAplicacionPago(object $aplicacion): string
    {
        return trim((string) ($aplicacion->axp_pro ?? '')).'|'
            .trim((string) ($aplicacion->axp_tipo_ap ?? '')).'|'
            .trim((string) ($aplicacion->axp_letra_comp ?? ' ')).'|'
            .(int) ($aplicacion->axp_sucursal ?? 0).'|'
            .(int) ($aplicacion->axp_nro ?? 0);
    }

    /**
     * Peso documental del comprobante (COM/subdiario) para repartir el cheque.
     * No usa axp_monto_ap (importe aplicado).
     */
    private function pesoDocumentalFactura(object $aplicacion, bool $inscripto): float
    {
        $lineasGasto = $this->cargarGastoDesdeAplicacion($aplicacion);
        if ($this->subTieneAnticipo114040($lineasGasto)) {
            $lineasGasto = $this->filtrarLineasAnticipoProveedor($lineasGasto);
        }

        $peso = array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $lineasGasto));
        $peso += array_sum($this->percepcionesRawDesdeAplicacion($aplicacion));
        if ($inscripto) {
            $peso += array_sum($this->ivaCreditoRawDesdeAplicacion($aplicacion));
        }

        return max(0.0, $peso);
    }

    /**
     * IVA crédito fiscal (114010-114019) del subdiario del comprobante, sin prorratear.
     *
     * @return array<int, float>
     */
    private function ivaCreditoRawDesdeAplicacion(object $aplicacion): array
    {
        $porCuenta = [];

        foreach ($this->cargarSubdiarioComprobanteAplicacion($aplicacion) as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || ! $this->esCuentaIvaCreditoFiscalCompras($cuenta)) {
                continue;
            }

            $porCuenta[$cuenta] = ($porCuenta[$cuenta] ?? 0.0) + (float) ($linea->subd_importe ?? 0);
        }

        return $porCuenta;
    }

    /** IVA crédito en factura/COM: 114010-114019 o 521130-001 (FNB/rebisco). */
    private function esCuentaIvaCreditoFiscalCompras(int $cuenta): bool
    {
        if ($cuenta >= 114010000 && $cuenta < 114020000) {
            return true;
        }

        return $cuenta === 521130001;
    }

    /**
     * FIS sin COM: anticipo 114040, gasto de factura (521/115) tal cual, o servicios 114010→114020-009.
     *
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function resolverGastoFisSubdiario(array $sub): array
    {
        $todas = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
        if ($todas === []) {
            return [];
        }

        if ($this->subTieneAnticipo114040($todas)) {
            return $this->filtrarLineasAnticipoProveedor($todas);
        }

        // Con gasto en la factura: no reimputar IVA/114010 al 521 (eso duplicaba el IVA en el peso
        // y forzaba 114020-009). El IVA y las percepciones salen en sus cuentas del subdiario.
        $gastoNeto = $this->filtrarLineasGastoNetoComprobanteCompras($sub);
        if ($gastoNeto !== []) {
            return $gastoNeto;
        }

        return $this->filtrarLineasFisMayorConcepto($sub);
    }

    /**
     * @param  list<object>  $lineasGasto
     */
    private function lineasGastoIncluyenResultadoCompras(array $lineasGasto): bool
    {
        foreach ($lineasGasto as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if (($cuenta >= 115000000 && $cuenta < 600000000 && $cuenta !== 521130001)
                || ($cuenta >= 123000000 && $cuenta < 124000000)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Líneas de COM con gasto de resultado (521/115…), excluye 117010 y puentes 114.
     *
     * @param  list<object>  $comSub
     * @return list<object>
     */
    private function filtrarLineasResultadoDesdeCom(array $comSub): array
    {
        return array_values(array_filter(
            $this->filtrarComGasto($comSub),
            fn ($linea) => $this->lineasGastoIncluyenResultadoCompras([$linea]),
        ));
    }

    /**
     * FIX-FIB-COM-VIA-PEP: COM ERP a veces guarda el neto en moneda extranjera (ej. 396.5 USD)
     * mientras el IVA de la FIB ya está en pesos. Escala el gasto COM al Debe 211010-004.
     * Rollback: docs/mayor-concepto-fix-fib-com-via-pep.md
     *
     * @param  list<object>  $comResultado
     * @param  list<object>  $subFactura
     * @return list<object>
     */
    private function escalarComResultadoANetoFacturaAdelantada(array $comResultado, array $subFactura): array
    {
        $netoCom = array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $comResultado));
        if ($netoCom <= 0) {
            return $comResultado;
        }

        $netoFactura = 0.0;
        $ivaFactura = 0.0;
        foreach ($subFactura as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($mov !== 'D') {
                continue;
            }
            $imp = (float) ($linea->subd_importe ?? 0);
            if ($cuenta >= 211010000 && $cuenta < 212000000) {
                $netoFactura += $imp;
            } elseif ($cuenta >= 114010000 && $cuenta < 114040000) {
                $ivaFactura += $imp;
            }
        }

        if ($netoFactura <= 0) {
            return $comResultado;
        }

        // Desfasaje típico moneda: COM <<< IVA en pesos de la FIB.
        if ($ivaFactura > 0 && $netoCom >= $ivaFactura * 0.05) {
            return $comResultado;
        }
        if ($ivaFactura <= 0 && $netoCom >= $netoFactura * 0.5) {
            return $comResultado;
        }

        $factor = $netoFactura / $netoCom;
        $escaladas = [];
        foreach ($comResultado as $linea) {
            $copia = clone $linea;
            $copia->subd_importe = round((float) ($linea->subd_importe ?? 0) * $factor, 2);
            $escaladas[] = $copia;
        }

        return $escaladas;
    }

    /**
     * @param  list<object>  $lineasGasto
     */
    private function comGastoEsSolo117010(array $lineasGasto): bool
    {
        if ($lineasGasto === []) {
            return false;
        }

        foreach ($lineasGasto as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta < 117010000 || $cuenta >= 118000000) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function resolverBancoParaLineaAsiento(object $linea, array $lineasOp): int
    {
        $importe = (float) ($linea->subd_importe ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

        foreach ($lineasOp as $otra) {
            $cuenta = (int) ($otra->subd_cuenta ?? 0);
            if (! $this->motor->esCuentaBancoCaja($cuenta)
                && ! ($this->motor->esDisponibilidad($cuenta) && $this->motor->esCuentaAnaliticoControl($cuenta))) {
                continue;
            }

            $dh = strtoupper(trim((string) ($otra->subd_tipo_mov ?? '')));
            if ($dh === $mov) {
                continue;
            }

            if (abs((float) ($otra->subd_importe ?? 0) - $importe) < 0.01) {
                return $cuenta;
            }
        }

        $bancoLinea = $this->resolverCuentaBancoEnLinea($linea);
        if ($bancoLinea > 0) {
            return $bancoLinea;
        }

        return $this->resolverBancoReferenciaAsiento($lineasOp);
    }

    /**
     * Gasto adelantado / anticipo en subdiario del comprobante (114xxx o 521xxx sin COM).
     *
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function filtrarLineasFacturaAdelantadaMayorConcepto(array $sub): array
    {
        return array_values(array_filter($sub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta <= 0) {
                return false;
            }

            if ($this->motor->esProveedor($cuenta)) {
                return false;
            }

            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return ($cuenta >= 114000000 && $cuenta < 115000000)
                || ($cuenta >= 500000000 && $cuenta < 600000000 && $cuenta !== 521130001);
        }));
    }

    /**
     * @return list<object>
     */
    private function cargarSubdiarioComprobanteAplicacion(object $aplicacion): array
    {
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $nroInterno = (int) ($aplicacion->axp_nro_interno ?? 0);
        $proveedor = trim((string) ($aplicacion->axp_pro ?? ''));

        $clave = $this->claveCacheSubdiarioFactura($tipoAp, $letraAp, $sucAp, $nroAp, $nroInterno);
        if (! isset($this->comSubdiarioCache[$clave])) {
            $this->consultasBridgeIndividuales++;
            $this->comSubdiarioCache[$clave] = $this->reader->cargarSubdiarioFacturaCompras(
                $this->empresaActiva,
                $tipoAp,
                $letraAp,
                $sucAp,
                $nroAp,
                $nroInterno,
                $proveedor,
                $this->erroresBridge,
            );
        }

        return $this->comSubdiarioCache[$clave];
    }

    private function claveCacheSubdiarioFactura(
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $nroInterno,
    ): string {
        $clave = $tipo.'|'.$letra.'|'.$sucursal.'|'.$nro;
        if ($nroInterno > 0) {
            $clave .= '|'.$nroInterno;
        }

        return $clave;
    }

    /**
     * Si el subdiario del comprobante no trae gasto 521xxx, completa con ctamov del asiento
     * (FIS puede estar en otro mes que el pago).
     *
     * @param  list<object>  $sub
     * @return list<object>
     */
    private function enriquecerSubdiarioFacturaConCtamov(object $aplicacion, array $sub): array
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
        if ($tipoAp !== 'FIS' || $sub === []) {
            return $sub;
        }

        $gasto = $this->resolverGastoFisSubdiario($sub);
        if ($gasto !== []) {
            return $sub;
        }

        $gastoNeto = $this->filtrarLineasGastoNetoComprobanteCompras($sub);
        if ($gastoNeto !== []) {
            return $sub;
        }

        $asientos = [];
        foreach ($sub as $linea) {
            $asi = (int) ($linea->subd_nro_operacion ?? 0);
            if ($asi > 0) {
                $asientos[$asi] = true;
            }
        }

        $ampliado = $sub;
        foreach (array_keys($asientos) as $nroAsiento) {
            $this->consultasBridgeIndividuales++;
            foreach ($this->reader->cargarCtamovPorAsiento($this->empresaActiva, $nroAsiento, $this->erroresBridge) as $lineaCtamov) {
                $ampliado[] = $this->ctamovComoSubdiario($lineaCtamov);
            }
        }

        return $ampliado;
    }

    /**
     * @param  list<object>  $fgaSub
     * @return list<object>
     */
    private function filtrarLineasFgaMayorConcepto(array $fgaSub): array
    {
        return array_values(array_filter($fgaSub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta <= 0) {
                return false;
            }

            if ($this->motor->esProveedor($cuenta)) {
                return false;
            }

            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return ($cuenta >= 114000000 && $cuenta < 115000000)
                || ($cuenta >= 500000000 && $cuenta < 600000000 && $cuenta !== 521130001);
        }));
    }

    /**
     * @param  list<object>  $fisSub
     * @return list<object>
     */
    private function filtrarLineasFisMayorConcepto(array $fisSub): array
    {
        return array_values(array_filter($fisSub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            if ($mov !== 'D' || $cuenta <= 0) {
                return false;
            }

            if ($this->motor->esProveedor($cuenta)) {
                return false;
            }

            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                return false;
            }

            return $cuenta >= 114000000 && $cuenta < 115000000;
        }));
    }

    private function cuentaVisibleDesdeDocumentoGasto(
        object $lineaGasto,
        string $tipoAp,
        bool $anticipo114040 = false,
        bool $fisServicios114020 = false,
    ): int {
        $cuenta = (int) ($lineaGasto->subd_cuenta ?? 0);
        if ($cuenta <= 0) {
            return 0;
        }

        if ($anticipo114040 && $cuenta >= 114010000 && $cuenta < 114020000) {
            return 114040001;
        }

        // Solo FIS "servicios" (sin COM ni 521/115): Anita muestra 114010 como 114020-009.
        if ($fisServicios114020 && strtoupper($tipoAp) === 'FIS'
            && $cuenta >= 114010000 && $cuenta < 114020000) {
            return 114020009;
        }

        return $cuenta;
    }

    /**
     * @param  list<object>  $lineasGasto
     */
    private function subTieneAnticipo114040(array $lineasGasto): bool
    {
        foreach ($lineasGasto as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 114040000 && $cuenta < 114050000) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anticipos a proveedor: solo 114010 (IVA) y 114040 del subdiario (Anita lee_subd + reimputa_cuentas).
     *
     * @param  list<object>  $lineasGasto
     * @return list<object>
     */
    private function filtrarLineasAnticipoProveedor(array $lineasGasto): array
    {
        return array_values(array_filter($lineasGasto, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);

            return ($cuenta >= 114010000 && $cuenta < 114020000)
                || ($cuenta >= 114040000 && $cuenta < 114050000);
        }));
    }

    /**
     * Reimputa 114010/521130 (y IVA crédito fiscal de OP) a los gastos de resultado
     * de la misma operación (l-mayorconc.c reimputa_cuentas). El importe se reparte
     * entre cuentas 4xx/5xx en proporción a su monto; no se mezcla sobre otras
     * cuentas de IVA/impuesto (114/214), para no inflar p.ej. 114010-008 en un EGR.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function reimputarCuentasAnticipoCompras(array $lineas): array
    {
        $cuentasReimputa = MayorConceptoMemoriaMotor::CUENTAS_REIMPUTA_CONCEPTO;
        $esReimputable = static fn (array $linea): bool => in_array((int) ($linea['cuenta'] ?? 0), $cuentasReimputa, true)
            || ($linea['origen'] ?? '') === 'IVA crédito fiscal';
        $porOperacion = [];

        foreach ($lineas as $indice => $linea) {
            if (($linea['origen'] ?? '') === 'Remanente mayor plano') {
                continue;
            }

            $nroOperacion = (int) ($linea['nro_asiento'] ?? 0);
            if ($nroOperacion <= 0) {
                continue;
            }

            $porOperacion[$nroOperacion][] = $indice;
        }

        foreach ($porOperacion as $indices) {
            $indicesGasto = [];
            $totalNetoGasto = 0.0;
            $indicesIva = [];

            foreach ($indices as $indice) {
                if ($esReimputable($lineas[$indice])) {
                    // En EGR (gastos bancarios) cada pierna 114010/521130 queda
                    // con el importe del subdiario; no absorberlas en comisiones.
                    if (str_starts_with((string) ($lineas[$indice]['origen'] ?? ''), 'EGR')) {
                        continue;
                    }
                    $indicesIva[] = $indice;

                    continue;
                }

                $cuentaDestino = (int) ($lineas[$indice]['cuenta'] ?? 0);
                if (! $this->esCuentaDestinoReimputacionIva($cuentaDestino)) {
                    continue;
                }

                $monto = round(
                    max((float) ($lineas[$indice]['debe'] ?? 0), (float) ($lineas[$indice]['haber'] ?? 0)),
                    2,
                );
                if ($monto <= 0) {
                    continue;
                }

                $indicesGasto[] = $indice;
                $totalNetoGasto += $monto;
            }

            if ($indicesGasto === [] || $indicesIva === [] || $totalNetoGasto <= 0) {
                continue;
            }

            foreach ($indicesIva as $indiceIva) {
                $debeIva = round((float) ($lineas[$indiceIva]['debe'] ?? 0), 2);
                $haberIva = round((float) ($lineas[$indiceIva]['haber'] ?? 0), 2);
                $dispDebeIva = round((float) ($lineas[$indiceIva]['disp_debe'] ?? 0), 2);
                $dispHaberIva = round((float) ($lineas[$indiceIva]['disp_haber'] ?? 0), 2);
                $pesoIva = max($debeIva, $haberIva);
                if ($pesoIva <= 0) {
                    unset($lineas[$indiceIva]);

                    continue;
                }

                $acumDebe = 0.0;
                $acumHaber = 0.0;
                $acumDispDebe = 0.0;
                $acumDispHaber = 0.0;
                $ultimo = count($indicesGasto) - 1;

                foreach ($indicesGasto as $pos => $indiceGasto) {
                    $pesoGasto = max(
                        (float) ($lineas[$indiceGasto]['debe'] ?? 0),
                        (float) ($lineas[$indiceGasto]['haber'] ?? 0),
                    );
                    $factor = $pesoGasto / $totalNetoGasto;

                    if ($pos === $ultimo) {
                        $addDebe = round($debeIva - $acumDebe, 2);
                        $addHaber = round($haberIva - $acumHaber, 2);
                        $addDispDebe = round($dispDebeIva - $acumDispDebe, 2);
                        $addDispHaber = round($dispHaberIva - $acumDispHaber, 2);
                    } else {
                        $addDebe = round($debeIva * $factor, 2);
                        $addHaber = round($haberIva * $factor, 2);
                        $addDispDebe = round($dispDebeIva * $factor, 2);
                        $addDispHaber = round($dispHaberIva * $factor, 2);
                        $acumDebe += $addDebe;
                        $acumHaber += $addHaber;
                        $acumDispDebe += $addDispDebe;
                        $acumDispHaber += $addDispHaber;
                    }

                    $lineas[$indiceGasto]['debe'] = round((float) ($lineas[$indiceGasto]['debe'] ?? 0) + $addDebe, 2);
                    $lineas[$indiceGasto]['haber'] = round((float) ($lineas[$indiceGasto]['haber'] ?? 0) + $addHaber, 2);
                    $lineas[$indiceGasto]['disp_debe'] = round((float) ($lineas[$indiceGasto]['disp_debe'] ?? 0) + $addDispDebe, 2);
                    $lineas[$indiceGasto]['disp_haber'] = round((float) ($lineas[$indiceGasto]['disp_haber'] ?? 0) + $addDispHaber, 2);
                }

                unset($lineas[$indiceIva]);
            }
        }

        return array_values($lineas);
    }

    /**
     * Destino de reimputación de IVA: solo cuentas de resultado (venta/gasto).
     * Las 114/214 (crédito fiscal, percepciones, etc.) conservan su propio importe.
     */
    private function esCuentaDestinoReimputacionIva(int $cuenta): bool
    {
        return $cuenta >= 400000000 && $cuenta < 600000000;
    }

    /**
     * Agrupa anticipos 114040 del mismo prefijo en una operación (arma_list Anita).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function consolidarAnticiposMismaOperacion(array $lineas): array
    {
        $porOperacion = [];

        foreach ($lineas as $indice => $linea) {
            if (($linea['origen'] ?? '') === 'Remanente mayor plano') {
                continue;
            }

            $prefijo = trim((string) ($linea['anticipo_prefijo_origen'] ?? ''));
            if ($prefijo === '') {
                continue;
            }

            $nroOperacion = (int) ($linea['nro_asiento'] ?? 0);
            if ($nroOperacion <= 0) {
                continue;
            }

            $porOperacion[$nroOperacion][$prefijo][] = $indice;
        }

        $fusionar = [];
        foreach ($porOperacion as $prefijos) {
            $tieneSplit = isset($prefijos['114010']) && isset($prefijos['114040']);
            foreach ($prefijos as $prefijo => $indices) {
                if ($tieneSplit && $prefijo === '114040' && count($indices) <= 1) {
                    continue;
                }

                if (count($indices) <= 1) {
                    continue;
                }

                $fusionar[] = $indices;
            }
        }

        foreach ($fusionar as $indices) {
            $base = $indices[0];
            for ($i = 1; $i < count($indices); $i++) {
                $indice = $indices[$i];
                $lineas[$base]['debe'] = round((float) $lineas[$base]['debe'] + (float) $lineas[$indice]['debe'], 2);
                $lineas[$base]['haber'] = round((float) $lineas[$base]['haber'] + (float) $lineas[$indice]['haber'], 2);
                $lineas[$base]['disp_debe'] = round((float) $lineas[$base]['disp_debe'] + (float) $lineas[$indice]['disp_debe'], 2);
                $lineas[$base]['disp_haber'] = round((float) $lineas[$base]['disp_haber'] + (float) $lineas[$indice]['disp_haber'], 2);
                unset($lineas[$indice]);
            }
        }

        return array_values($lineas);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function agruparPorConcepto(array $lineas): array
    {
        $porConcepto = [];
        foreach ($lineas as $linea) {
            $cid = (int) $linea['concepto_id'];
            $cuenta = (int) $linea['cuenta'];
            if (! isset($porConcepto[$cid])) {
                $porConcepto[$cid] = [
                    'concepto_id' => $cid,
                    'concepto_nombre' => $linea['concepto_nombre'],
                    'cuentas' => [],
                ];
            }
            if (! isset($porConcepto[$cid]['cuentas'][$cuenta])) {
                $porConcepto[$cid]['cuentas'][$cuenta] = [
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $linea['cuenta_codigo'],
                    'cuenta_nombre' => $linea['cuenta_nombre'],
                    'lineas' => [],
                    'total_debe' => 0.0,
                    'total_haber' => 0.0,
                ];
            }
            $porConcepto[$cid]['cuentas'][$cuenta]['lineas'][] = $linea;
            $porConcepto[$cid]['cuentas'][$cuenta]['total_debe'] += (float) $linea['debe'];
            $porConcepto[$cid]['cuentas'][$cuenta]['total_haber'] += (float) $linea['haber'];
        }

        foreach ($porConcepto as $cid => $sec) {
            foreach ($sec['cuentas'] as $cuenta => $cuentaBlock) {
                usort(
                    $porConcepto[$cid]['cuentas'][$cuenta]['lineas'],
                    static function (array $a, array $b): int {
                        $fechaA = (int) ($a['fecha'] ?? 0);
                        $fechaB = (int) ($b['fecha'] ?? 0);
                        if ($fechaA !== $fechaB) {
                            return $fechaA <=> $fechaB;
                        }

                        $nroA = (int) ($a['nro_asiento'] ?? 0);
                        $nroB = (int) ($b['nro_asiento'] ?? 0);
                        if ($nroA !== $nroB) {
                            return $nroA <=> $nroB;
                        }

                        return strcmp((string) ($a['comprobante'] ?? ''), (string) ($b['comprobante'] ?? ''));
                    },
                );
            }
        }

        $secciones = [];
        foreach ($porConcepto as $sec) {
            $cuentas = [];
            foreach ($sec['cuentas'] as $c) {
                $c['total_debe'] = round($c['total_debe'], 2);
                $c['total_haber'] = round($c['total_haber'], 2);
                $cuentas[] = $c;
            }
            usort($cuentas, fn ($a, $b) => $a['cuenta'] <=> $b['cuenta']);
            $sec['cuentas'] = $cuentas;
            $secciones[] = $sec;
        }

        usort($secciones, fn ($a, $b) => $a['concepto_id'] <=> $b['concepto_id']);

        return $secciones;
    }

    private function lineaReporte(
        ?object $origen,
        int $cuenta,
        int $conceptoId,
        float $importeOrigen,
        string $dh,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        string $origenLog,
        array $meta = [],
    ): array {
        $fecha = (int) ($origen->subd_fecha ?? 0);
        $codMon = (string) ($origen->subd_cod_mon ?? '1');
        $cotiz = (float) ($origen->subd_cotizacion ?? 0);
        $importe = $monedaConverter->convertirImporte($importeOrigen, $codMon, $cotiz, $fecha, $monedaReporteId);

        $refTipo = trim((string) ($origen->subd_ref_tipo ?? $origen->subd_tipo ?? ''));
        $refLetra = trim((string) ($origen->subd_ref_letra ?? $origen->subd_letra ?? ' '));
        $refSuc = (int) ($origen->subd_ref_sucursal ?? $origen->subd_sucursal ?? 0);
        $refNro = (int) ($origen->subd_ref_nro ?? $origen->subd_nro ?? 0);

        $conceptoNombre = $conceptoId === 0 ? 'SIN CLASIFICAR' : DB::table('conceptogasto')->where('id', $conceptoId)->value('nombre') ?? 'Concepto '.$conceptoId;
        $cuentaNombre = DB::table('cuentacontable')
            ->where('empresa_id', $this->empresaActiva)
            ->where('codigo', $cuenta)
            ->value('nombre') ?? $this->motor->formatearCodigoCuenta($cuenta);

        $cuentaDisp = (int) ($meta['cuenta_disponibilidad'] ?? 0);
        $importeDebe = $dh === 'D' ? round($importe, 2) : 0.0;
        $importeHaber = $dh === 'H' ? round($importe, 2) : 0.0;
        [$dispDebe, $dispHaber] = $this->dispDebeHaberLinea($cuenta, $cuentaDisp, $importeDebe, $importeHaber);

        $desdeOperacionDisp = $origenLog !== 'Remanente mayor plano';
        if ($desdeOperacionDisp) {
            $this->acumularPlanoContrapartidaDesdeDisp($cuenta, $importeDebe, $importeHaber);
        }

        return [
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $conceptoNombre,
            'cuenta' => $cuenta,
            'cuenta_codigo' => $this->motor->formatearCodigoCuenta($cuenta),
            'cuenta_nombre' => $cuentaNombre,
            'cuenta_disponibilidad' => $cuentaDisp,
            'cuenta_disponibilidad_codigo' => $cuentaDisp > 0 ? $this->motor->formatearCodigoCuenta($cuentaDisp) : '',
            'fecha' => $fecha,
            'fecha_fmt' => $this->fmtFecha($fecha),
            'nro_asiento' => (int) ($origen->subd_nro_operacion ?? 0),
            'tipo_comp' => $refTipo,
            'comprobante' => $this->formatearComprobante($refTipo, $refLetra, $refSuc, $refNro),
            'cheque' => $this->resolverChequeLineaReporte($meta, $origen),
            'nro_oc' => (int) ($meta['nro_oc'] ?? 0),
            'emisor' => trim((string) ($meta['emisor'] ?? '')),
            'cuit' => trim((string) ($meta['cuit'] ?? '')),
            'descripcion' => trim((string) ($origen->subd_desc_mov ?? '')),
            'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
            'cotizacion' => $cotiz,
            'debe' => $importeDebe,
            'haber' => $importeHaber,
            'disp_debe' => $dispDebe,
            'disp_haber' => $dispHaber,
            'origen' => $origenLog,
            'desde_operacion_disponibilidad' => $desdeOperacionDisp,
            'anticipo_prefijo_origen' => trim((string) ($meta['anticipo_prefijo_origen'] ?? '')),
        ];
    }

    private function acumularPlanoContrapartidaDesdeDisp(int $cuenta, float $debe, float $haber): void
    {
        if ($cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
            return;
        }

        if (! isset($this->planoContrapartidasDesdeDisp[$cuenta])) {
            $this->planoContrapartidasDesdeDisp[$cuenta] = ['debe' => 0.0, 'haber' => 0.0, 'movimientos' => 0];
        }

        $this->planoContrapartidasDesdeDisp[$cuenta]['debe'] += $debe;
        $this->planoContrapartidasDesdeDisp[$cuenta]['haber'] += $haber;
        $this->planoContrapartidasDesdeDisp[$cuenta]['movimientos']++;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finalizarPlanoContrapartidasDesdeDisp(): array
    {
        $porCuenta = [];

        foreach ($this->planoContrapartidasDesdeDisp as $cuenta => $row) {
            $porCuenta[$cuenta] = [
                'cuenta' => $cuenta,
                'debe' => round((float) ($row['debe'] ?? 0), 2),
                'haber' => round((float) ($row['haber'] ?? 0), 2),
                'movimientos' => (int) ($row['movimientos'] ?? 0),
                'cuenta_codigo' => $this->motor->formatearCodigoCuenta($cuenta),
                'cuenta_nombre' => DB::table('cuentacontable')
                    ->where('empresa_id', $this->empresaActiva)
                    ->where('codigo', $cuenta)
                    ->value('nombre') ?? $this->motor->formatearCodigoCuenta($cuenta),
            ];
        }

        ksort($porCuenta);

        return $porCuenta;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function dispDebeHaberLinea(int $cuentaMostrada, int $cuentaDisp, float $debe, float $haber): array
    {
        if ($cuentaDisp <= 0) {
            return [0.0, 0.0];
        }

        if ($cuentaMostrada === $cuentaDisp) {
            return [$debe, $haber];
        }

        return [$haber, $debe];
    }

    private function fmtFecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 2, 2);
    }

    private function formatearComprobante(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        $letra = trim($letra);
        if ($letra !== '') {
            return $letra.sprintf('%04d-%d', $sucursal, $nro);
        }

        return sprintf(' %04d-%07d', $sucursal, $nro);
    }

    private function lineaVisible(object $linea, MayorConceptoMonedaConverter $mon, int $monedaId, bool $soloOrigen): bool
    {
        return $mon->movimientoVisible(
            (string) ($linea->subd_cod_mon ?? '1'),
            (float) ($linea->subd_cotizacion ?? 0),
            $monedaId,
            $soloOrigen,
        );
    }

    private function claveOperacionPago(int $empresaId, string $tipo, int $nro, int $fecha): string
    {
        return $empresaId.'|'.strtoupper(trim($tipo)).'|'.$nro.'|'.$fecha;
    }

    private function claveOperacionPagoRef(int $empresaId, string $tipo, int $nro): string
    {
        return $empresaId.'|'.strtoupper(trim($tipo)).'|'.$nro;
    }

    private function claveOperacionCtamov(int $nroAsiento, int $fecha): string
    {
        return 'CTM|'.$nroAsiento.'|'.$fecha;
    }

    private function claveAsientoContable(int $nroAsiento, int $fecha): string
    {
        return 'ASI|'.$nroAsiento.'|'.$fecha;
    }

    private function claveAsientoIndex(int $nroAsiento, int $fecha): string
    {
        return $nroAsiento.'|'.$fecha;
    }

    /**
     * @param  list<object>  $subdiario
     * @return array<string, list<object>>
     */
    private function indexarSubdiarioPorAsiento(array $subdiario): array
    {
        $porAsiento = [];

        foreach ($subdiario as $linea) {
            $nroAsiento = (int) ($linea->subd_nro_operacion ?? 0);
            if ($nroAsiento <= 0) {
                continue;
            }

            $clave = $this->claveAsientoIndex($nroAsiento, (int) ($linea->subd_fecha ?? 0));
            $porAsiento[$clave][] = $linea;
        }

        return $porAsiento;
    }

    /**
     * @param  list<object>  $ctamovLista
     * @return array<string, list<object>>
     */
    private function indexarCtamovPorAsiento(array $ctamovLista): array
    {
        $porAsiento = [];

        foreach ($ctamovLista as $linea) {
            $nroAsiento = (int) ($linea->ctav_nro_asiento ?? 0);
            if ($nroAsiento <= 0) {
                continue;
            }

            $clave = $this->claveAsientoIndex($nroAsiento, (int) ($linea->ctav_fecha ?? 0));
            $porAsiento[$clave][] = $linea;
        }

        return $porAsiento;
    }

    /**
     * Une líneas de subdiario y ctamov del mismo asiento (patrón l-mayor: ambas fuentes suman).
     *
     * @param  list<object>  $lineasOp
     * @param  list<object>  $lineasSubdiario
     * @param  list<object>  $lineasCtamov
     * @return list<object>
     */
    private function mergeLineasOpSubdiarioCtamov(array $lineasOp, array $lineasSubdiario, array $lineasCtamov = []): array
    {
        $existentes = [];
        foreach ($lineasOp as $linea) {
            $existentes[$this->claveLineaOperacion($linea)] = true;
        }

        foreach ($lineasSubdiario as $linea) {
            $clave = $this->claveLineaOperacion($linea);
            if (isset($existentes[$clave])) {
                continue;
            }
            $lineasOp[] = $linea;
            $existentes[$clave] = true;
        }

        if ($lineasCtamov !== []) {
            foreach ($this->ctamovAsientoComoLineasOp($lineasCtamov) as $linea) {
                $clave = $this->claveLineaOperacion($linea);
                if (isset($existentes[$clave])) {
                    continue;
                }
                $lineasOp[] = $linea;
                $existentes[$clave] = true;
            }
        }

        return $lineasOp;
    }

    private function claveLineaOperacion(object $linea): string
    {
        return (int) ($linea->subd_cuenta ?? 0)
            .'|'.(int) ($linea->subd_contrapartida ?? 0)
            .'|'.strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')))
            .'|'.number_format((float) ($linea->subd_importe ?? 0), 2, '.', '');
    }

    /**
     * @param  list<object>  $subdiario
     * @return list<object>
     */
    private function filtrarSubdiarioPorRef(array $subdiario, object $ref): array
    {
        $tipo = trim((string) ($ref->subd_ref_tipo ?? ''));
        $letra = trim((string) ($ref->subd_ref_letra ?? ' '));
        $suc = (int) ($ref->subd_ref_sucursal ?? 0);
        $nro = (int) ($ref->subd_ref_nro ?? 0);
        $fecha = (int) ($ref->subd_fecha ?? 0);

        return array_values(array_filter(
            $subdiario,
            fn ($l) => trim((string) ($l->subd_ref_tipo ?? '')) === $tipo
                && trim((string) ($l->subd_ref_letra ?? ' ')) === $letra
                && (int) ($l->subd_ref_sucursal ?? 0) === $suc
                && (int) ($l->subd_ref_nro ?? 0) === $nro
                && (int) ($l->subd_fecha ?? 0) === $fecha,
        ));
    }

    /**
     * @return list<object>
     */
    private function cargarComDesdeFactura(object $aplicacion): array
    {
        $comLineas = [];
        foreach ($this->resolverClavesComDesdeFactura($aplicacion) as $claveCom) {
            $comLineas = array_merge($comLineas, $this->resolverComSubdiarioConFallback($claveCom));
        }

        return $comLineas;
    }

    /**
     * Subdiario COM Anita; si viene vacío, recepción confirmada en ERP (asiento_movimiento).
     *
     * @return list<object>
     */
    private function resolverComSubdiarioConFallback(string $claveCom): array
    {
        if (! isset($this->comSubdiarioCache[$claveCom])) {
            [$ct, $cl, $cs, $cn] = array_pad(explode('|', $claveCom, 4), 4, '');
            $this->consultasBridgeIndividuales++;
            $this->comSubdiarioCache[$claveCom] = $this->reader->cargarComSubdiario(
                $this->empresaActiva,
                $ct,
                $cl,
                (int) $cs,
                (int) $cn,
                $this->erroresBridge,
            );
        }

        if (($this->comSubdiarioCache[$claveCom] ?? []) === []) {
            $erp = $this->comRecepcionErpSupport->lineasGastoDesdeClaveCom(
                $this->empresaActiva,
                $claveCom,
                $this->motor,
            );
            if ($erp !== []) {
                $this->comSubdiarioCache[$claveCom] = $erp;
                $this->comSubdiarioErpFallback++;
            }
        }

        return $this->comSubdiarioCache[$claveCom] ?? [];
    }

    /**
     * @param  list<string>  $clavesCom
     */
    private function completarComSubdiarioDesdeRecepcionErp(array $clavesCom): void
    {
        $vacias = array_values(array_filter(
            $clavesCom,
            fn ($clave) => ($this->comSubdiarioCache[$clave] ?? []) === [],
        ));

        if ($vacias === []) {
            return;
        }

        foreach ($this->comRecepcionErpSupport->lineasGastoPorClavesCom($this->empresaActiva, $vacias, $this->motor) as $clave => $lineas) {
            if ($lineas === []) {
                continue;
            }

            $this->comSubdiarioCache[$clave] = $lineas;
            $this->comSubdiarioErpFallback++;
        }
    }

    /**
     * Sigue aplicped (FGA→PEP→COM, FIB→COM, etc.) para resolver subdiarios COM.
     *
     * @return list<string>
     */
    private function resolverClavesComDesdeFactura(object $aplicacion): array
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);

        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->consultasBridgeIndividuales++;
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        if (! isset($this->ordenesComPorFactura[$claveFac])) {
            $this->ordenesComPorFactura[$claveFac] = [];
        }

        $visitados = [];
        $pendientes = [[$tipoAp, $letraAp, $sucAp, $nroAp]];
        $clavesCom = [];

        while ($pendientes !== []) {
            [$tipo, $letra, $suc, $nro] = array_shift($pendientes);
            $claveDoc = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            if (isset($visitados[$claveDoc])) {
                continue;
            }
            $visitados[$claveDoc] = true;

            if (! isset($this->aplicpedCache[$claveDoc])) {
                $this->consultasBridgeIndividuales++;
                $this->aplicpedCache[$claveDoc] = $this->reader->cargarAplicpedFactura(
                    $prov, $tipo, $letra, $suc, $nro, $this->erroresBridge,
                );
            }

            foreach ($this->aplicpedCache[$claveDoc] as $apl) {
                $refTipo = strtoupper(trim((string) ($apl->aplp_ref_tipo ?? '')));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);

                if ($refTipo === 'COM' && $refNro > 0) {
                    $claveCom = $refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro;
                    $clavesCom[$claveCom] = $claveCom;
                    // Número de OC = nro del comprobante COM (nunca aplp_orden = renglón).
                    $this->ordenesComPorFactura[$claveFac][$claveCom] = $refNro;

                    continue;
                }

                if ($refTipo !== '' && $refNro > 0) {
                    $pendientes[] = [$refTipo, $refLetra, $refSuc, $refNro];
                }
            }
        }

        return array_values($clavesCom);
    }

    /**
     * COM efectiva de la factura: enlace directo en aplicped, o hermana vía PEP
     * solo cuando la factura no trae gasto propio (p. ej. 211010-004 FC a recibir).
     *
     * @return list<object>
     */
    private function cargarComDesdeFacturaEfectivo(object $aplicacion): array
    {
        $com = $this->cargarComDesdeFactura($aplicacion);
        if ($com !== []) {
            return $com;
        }

        if ($this->facturaTieneGastoPropioEnSubdiario($aplicacion)) {
            return [];
        }

        return $this->cargarComDesdeFacturaViaPepHermano($aplicacion);
    }

    /**
     * True si el subdiario de la factura ya aporta gasto/anticipo imputable
     * (no solo puente proveedor 211010-004).
     */
    private function facturaTieneGastoPropioEnSubdiario(object $aplicacion): bool
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
        $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);

        if ($tipoAp === 'FIS') {
            $sub = $this->enriquecerSubdiarioFacturaConCtamov($aplicacion, $sub);
            $desdeSub = $this->resolverGastoFisSubdiario($sub);
            if ($desdeSub === []) {
                return false;
            }

            // Solo 114xxx adelantado/anticipo: no bloquea hop FIS→PEP←COM (reglas 114020/114040).
            return $this->lineasGastoIncluyenResultadoCompras($desdeSub);
        }

        if ($tipoAp === 'FGA') {
            return $this->filtrarLineasFgaMayorConcepto($sub) !== [];
        }

        // --- FIX-FIB-COM-VIA-PEP (facturaTieneGastoPropio) ---
        if (in_array($tipoAp, ['FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA'], true)) {
            $adelantada = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
            if ($adelantada === []) {
                return false;
            }

            // Solo IVA 114010 / puente: no bloquea hop FIB→PEP←COM.
            return $this->lineasGastoIncluyenResultadoCompras($adelantada);
        }
        // --- /FIX-FIB-COM-VIA-PEP ---

        $adelantada = $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
        if ($this->subTieneAnticipo114040($adelantada)) {
            return true;
        }

        return $this->filtrarLineasGastoNetoComprobanteCompras($sub) !== [];
    }

    /**
     * FIS/FGA → PEP ← COM (mismo proveedor): la factura y la COM referencian el PEP.
     *
     * @return list<object>
     */
    private function cargarComDesdeFacturaViaPepHermano(object $aplicacion): array
    {
        $comLineas = [];
        foreach ($this->resolverClavesComViaPepHermano($aplicacion) as $claveCom) {
            $comLineas = array_merge($comLineas, $this->resolverComSubdiarioConFallback($claveCom));
        }

        return $comLineas;
    }

    /**
     * Busca COMs que referencian el mismo PEP que la factura (aplicped por ref + proveedor).
     *
     * @return list<string> claves COM|letra|suc|nro
     */
    private function resolverClavesComViaPepHermano(object $aplicacion): array
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        if ($prov === '') {
            return [];
        }

        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;

        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->consultasBridgeIndividuales++;
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        if (! isset($this->ordenesComPorFactura[$claveFac])) {
            $this->ordenesComPorFactura[$claveFac] = [];
        }

        $clavesCom = [];
        $pepsVistos = [];

        foreach ($this->aplicpedCache[$claveFac] as $apl) {
            $refTipo = strtoupper(trim((string) ($apl->aplp_ref_tipo ?? '')));
            if ($refTipo !== 'PEP') {
                continue;
            }

            $refLetra = trim((string) ($apl->aplp_ref_letra ?? 'X'));
            $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            if ($refNro <= 0) {
                continue;
            }

            $clavePep = $prov.'|PEP|'.$refLetra.'|'.$refSuc.'|'.$refNro;
            if (isset($pepsVistos[$clavePep])) {
                continue;
            }
            $pepsVistos[$clavePep] = true;

            if (! isset($this->aplicpedPorRefCache[$clavePep])) {
                $this->consultasBridgeIndividuales++;
                $this->aplicpedPorRefCache[$clavePep] = $this->reader->cargarAplicpedPorReferencia(
                    'PEP',
                    $refLetra,
                    $refSuc,
                    $refNro,
                    $prov,
                    $this->erroresBridge,
                );
            }

            foreach ($this->aplicpedPorRefCache[$clavePep] as $hermano) {
                if (strtoupper(trim((string) ($hermano->aplp_tipo ?? ''))) !== 'COM') {
                    continue;
                }

                $comLetra = trim((string) ($hermano->aplp_letra ?? 'X'));
                $comSuc = (int) ($hermano->aplp_sucursal ?? 0);
                $comNro = (int) ($hermano->aplp_nro ?? 0);
                if ($comNro <= 0) {
                    continue;
                }

                $claveCom = 'COM|'.$comLetra.'|'.$comSuc.'|'.$comNro;
                $clavesCom[$claveCom] = $claveCom;
                $this->ordenesComPorFactura[$claveFac][$claveCom] = $comNro;
            }
        }

        return array_values($clavesCom);
    }

    /**
     * @return array<string, mixed>
     */
    private function metaRetencionPago(int $empresaId, object $retencion): array
    {
        $meta = $this->metaPagoProveedor($empresaId, $retencion, null, 0);
        $meta['cuenta_disponibilidad'] = 0;

        return $meta;
    }

    private function ordenComDesdeAplicacion(object $aplicacion): int
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;

        // El propio documento aplicado ya es la OC.
        if ($tipoAp === 'COM' && $nroAp > 0) {
            return $nroAp;
        }

        $this->resolverClavesComDesdeFactura($aplicacion);

        foreach ($this->ordenesComPorFactura[$claveFac] ?? [] as $clave => $orden) {
            if ($orden > 0) {
                return (int) $orden;
            }
        }

        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->consultasBridgeIndividuales++;
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        foreach ($this->aplicpedCache[$claveFac] as $apl) {
            $refTipo = strtoupper(trim((string) ($apl->aplp_ref_tipo ?? '')));
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            // Solo COM: aplp_orden es renglón; PEP.ref_nro es pedido, no OC.
            if ($refTipo === 'COM' && $refNro > 0) {
                return $refNro;
            }
        }

        if (! $this->facturaTieneGastoPropioEnSubdiario($aplicacion)) {
            $this->resolverClavesComViaPepHermano($aplicacion);
            foreach ($this->ordenesComPorFactura[$claveFac] ?? [] as $orden) {
                if ($orden > 0) {
                    return (int) $orden;
                }
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaPagoProveedor(
        int $empresaId,
        object $aplicacion,
        ?object $lineaCom,
        int $cuentaDisponibilidad,
        string $nroChequeOp = '',
    ): array {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $prom = null;
        if ($prov !== '') {
            if (! isset($this->promaeCache[$prov])) {
                $this->consultasBridgeIndividuales++;
                $this->promaeCache[$prov] = $this->reader->cargarPromae($prov, $this->erroresBridge);
            }
            $prom = $this->promaeCache[$prov];
        }

        $nroOc = $this->ordenComDesdeAplicacion($aplicacion);
        if ($nroOc <= 0 && $lineaCom !== null) {
            $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
            $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
            $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
            $nroAp = (int) ($aplicacion->axp_nro ?? 0);
            $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
            $claveCom = $this->claveComprobanteDesdeSubdiario($lineaCom);
            $nroOc = (int) ($this->ordenesComPorFactura[$claveFac][$claveCom] ?? 0);
        }

        $cheque = $this->numeroChequeDesdeAplicacion($aplicacion);
        if ($cheque === '' && $nroChequeOp !== '') {
            $cheque = $nroChequeOp;
        }

        return [
            'cuenta_disponibilidad' => $cuentaDisponibilidad,
            'cheque' => $cheque,
            'nro_oc' => $nroOc,
            'emisor' => $prom ? $this->truncarTexto(trim((string) ($prom->prom_nombre ?? '')), 15) : '',
            'cuit' => $prom ? trim((string) ($prom->prom_cuit ?? '')) : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolverChequeLineaReporte(array $meta, ?object $origen): string
    {
        $cheque = trim((string) ($meta['cheque'] ?? ''));
        if ($cheque !== '') {
            return $cheque;
        }

        if ($origen === null) {
            return '';
        }

        return $this->numeroChequeDesdeDescripcion(trim((string) ($origen->subd_desc_mov ?? '')));
    }

    /**
     * Número de cheque propio (CHP) en auxpag. No usar axp_banco: en facturas es
     * un código fijo (ej. 000001) y en CHP es la cuenta bancaria, no el nro.
     *
     * @param  list<object>  $auxpag
     */
    private function numeroChequeDesdeAuxpag(array $auxpag): string
    {
        $numeros = [];
        foreach ($auxpag as $fila) {
            $nro = $this->numeroChequeDesdeAplicacion($fila);
            if ($nro !== '') {
                $numeros[$nro] = $nro;
            }
        }

        if ($numeros === []) {
            return '';
        }

        return (string) reset($numeros);
    }

    private function numeroChequeDesdeAplicacion(object $aplicacion): string
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
        if ($tipoAp !== 'CHP') {
            return '';
        }

        $nro = trim((string) ($aplicacion->axp_nro ?? ''));
        if ($nro === '' || $nro === '0') {
            return '';
        }

        return $nro;
    }

    private function numeroChequeDesdeDescripcion(string $descripcion): string
    {
        if ($descripcion === '') {
            return '';
        }

        if (preg_match('/\bCh:\s*(\d{5,12})\b/i', $descripcion, $m)) {
            return $m[1];
        }

        return '';
    }

    private function claveComprobanteDesdeSubdiario(object $linea): string
    {
        return trim((string) ($linea->subd_tipo ?? $linea->subd_ref_tipo ?? ''))
            .'|'.trim((string) ($linea->subd_letra ?? $linea->subd_ref_letra ?? ' '))
            .'|'.(int) ($linea->subd_sucursal ?? $linea->subd_ref_sucursal ?? 0)
            .'|'.(int) ($linea->subd_nro ?? $linea->subd_ref_nro ?? 0);
    }

    private function truncarTexto(string $texto, int $max): string
    {
        if ($texto === '') {
            return '';
        }

        return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max) : $texto;
    }

    /**
     * @param  list<object>  $subdiario
     * @param  list<object>  $ctamovLista
     * @return array<int, array<string, mixed>>
     */
    private function construirMayorPlanoDisponibilidad(
        array $subdiario,
        array $ctamovLista,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        $porCuenta = [];
        $asientosDentroLimite = $this->indexarAsientosDentroLimiteMayorConcepto($subdiario, $ctamovLista);
        // Venta máquinas / gastro / estacionamiento: imputan por cuenta concepto (disp = misma
        // cuenta > tope). Sus piernas de caja/banco no deben generar "Ajuste mayor plano".
        $asientosVentaLiteralPorConcepto = $this->indexarAsientosVentaLiteralPorConcepto($ctamovLista);

        foreach ($ctamovLista as $lineaCtamov) {
            $adaptada = $this->ctamovComoSubdiario($lineaCtamov);
            if (! $this->lineaVisible($adaptada, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $nroAsiento = (int) ($lineaCtamov->ctav_nro_asiento ?? 0);
            if ($nroAsiento <= 0 || ! isset($asientosDentroLimite[$nroAsiento])) {
                continue;
            }
            if (isset($asientosVentaLiteralPorConcepto[$nroAsiento])) {
                continue;
            }

            $cuenta = (int) ($lineaCtamov->ctav_cuenta ?? 0);
            if (! $this->motor->esDisponibilidadPlano($cuenta)) {
                continue;
            }

            $dh = strtoupper(trim((string) ($lineaCtamov->ctav_d_h ?? '')));
            $importe = $monedaConverter->convertirImporte(
                (float) ($lineaCtamov->ctav_importe ?? 0),
                (string) ($lineaCtamov->ctav_cod_mon ?? '1'),
                (float) ($lineaCtamov->ctav_cotizacion ?? 0),
                (int) ($lineaCtamov->ctav_fecha ?? 0),
                $monedaReporteId,
            );

            $this->acumularMovimientoPlanoDisponibilidad($porCuenta, $cuenta, $dh, $importe);
        }

        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $nroOperacion = (int) ($linea->subd_nro_operacion ?? 0);
            if ($nroOperacion <= 0 || ! isset($asientosDentroLimite[$nroOperacion])) {
                continue;
            }
            if (isset($asientosVentaLiteralPorConcepto[$nroOperacion])) {
                continue;
            }

            $importe = $monedaConverter->convertirImporte(
                (float) ($linea->subd_importe ?? 0),
                (string) ($linea->subd_cod_mon ?? '1'),
                (float) ($linea->subd_cotizacion ?? 0),
                (int) ($linea->subd_fecha ?? 0),
                $monedaReporteId,
            );

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            $omitirCuenta = $this->omitirPiernaPlanoSubdiarioBancoCreditoComercial($cuenta, $contrapartida, true);
            $omitirContrapartida = $this->omitirPiernaPlanoSubdiarioBancoCreditoComercial($cuenta, $contrapartida, false);

            if ($this->subdiarioPiernaPlanoDisponibilidad($cuenta, $contrapartida, true) && ! $omitirCuenta) {
                $this->acumularMovimientoPlanoDisponibilidad($porCuenta, $cuenta, $mov, $importe);
            }

            if ($contrapartida > 0
                && $this->subdiarioPiernaPlanoDisponibilidad($cuenta, $contrapartida, false)
                && ! $omitirContrapartida) {
                $movContra = $mov === 'D' ? 'H' : 'D';
                $this->acumularMovimientoPlanoDisponibilidad($porCuenta, $contrapartida, $movContra, $importe);
            }
        }

        foreach ($porCuenta as $cuenta => $row) {
            $porCuenta[$cuenta]['debe'] = round($row['debe'], 2);
            $porCuenta[$cuenta]['haber'] = round($row['haber'], 2);
            $porCuenta[$cuenta]['cuenta_codigo'] = $this->motor->formatearCodigoCuenta($cuenta);
            $porCuenta[$cuenta]['cuenta_nombre'] = DB::table('cuentacontable')
                ->where('empresa_id', $this->empresaActiva)
                ->where('codigo', $cuenta)
                ->value('nombre') ?? $porCuenta[$cuenta]['cuenta_codigo'];
        }

        ksort($porCuenta);

        return $porCuenta;
    }

    /**
     * Asientos ctamov de venta máquinas (tienen pierna 41201x).
     *
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosVentaMaquinas(array $ctamovLista): array
    {
        $porAsiento = [];
        foreach ($ctamovLista as $linea) {
            $nro = (int) ($linea->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $porAsiento[$nro][] = $this->ctamovComoSubdiario($linea);
        }

        $index = [];
        foreach ($porAsiento as $nro => $lineasOp) {
            if ($this->esAsientoCtamovVentaMaquinas($lineasOp)) {
                $index[(int) $nro] = true;
            }
        }

        return $index;
    }

    /**
     * Asientos ctamov con imputación literal por concepto (máquinas + gastro/estacionamiento
     * + sistema B con medios fuera de límite). Fuera del mayor plano de disponibilidad.
     *
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosVentaLiteralPorConcepto(array $ctamovLista): array
    {
        $index = $this->indexarAsientosVentaMaquinas($ctamovLista);

        foreach ($this->agruparCtamovPorAsiento($ctamovLista) as $lineasAsiento) {
            if ($lineasAsiento === []) {
                continue;
            }

            $nro = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
            if ($nro <= 0 || isset($index[$nro])) {
                continue;
            }

            $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
            if ($this->debeProcesarAsientoCtamovVentaMultibanco($lineasAsiento, $lineasOp)
                || ($this->debeProcesarAsientoCtamovSistemaB($lineasAsiento, $lineasOp)
                    && $this->esAsientoCtamovSistemaBLiteralPorConcepto($lineasOp))) {
                $index[$nro] = true;
            }
        }

        return $index;
    }

    /**
     * Sistema B con crédito/medios fuera del límite analítico: prorrateo parcial → literal.
     *
     * @param  list<object>  $lineasOp
     */
    private function esAsientoCtamovSistemaBLiteralPorConcepto(array $lineasOp): bool
    {
        return $this->factorProrrateoAnaliticoControlContrapartidas($lineasOp, false) < 1.0 - 1e-9;
    }

    /**
     * Sin cuenta ≤ límite mayor por concepto (112010-008): el asiento no entra al plano ni a la imputación.
     *
     * @param  list<object>  $subdiario
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosDentroLimiteMayorConcepto(array $subdiario, array $ctamovLista): array
    {
        $index = [];

        foreach ($subdiario as $linea) {
            $nro = (int) ($linea->subd_nro_operacion ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if ($this->motor->esDisponibilidad($cuenta) || $this->motor->esDisponibilidad($contrapartida)) {
                $index[$nro] = true;
            }
        }

        foreach ($ctamovLista as $linea) {
            $nro = (int) ($linea->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $cuenta = (int) ($linea->ctav_cuenta ?? 0);
            if ($this->motor->esDisponibilidad($cuenta)) {
                $index[$nro] = true;
            }
        }

        return $index;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function asientoOpTieneCuentaDentroLimiteMayorConcepto(array $lineasOp): bool
    {
        foreach ($lineasOp as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if ($this->motor->esDisponibilidad($cuenta) || $this->motor->esDisponibilidad($contrapartida)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subdiario en mayor plano: 113xxx solo si la contrapartida es caja/banco dentro del límite mayor por concepto.
     * OPs puras 113↔gasto/percepción (ej. EGR 5263065) quedan fuera del bridge de remanente.
     */
    private function subdiarioPiernaPlanoDisponibilidad(int $cuenta, int $contrapartida, bool $esPiernaCuenta): bool
    {
        $pierna = $esPiernaCuenta ? $cuenta : $contrapartida;
        $otra = $esPiernaCuenta ? $contrapartida : $cuenta;

        if ($pierna <= 0 || ! $this->motor->esDisponibilidadPlano($pierna)) {
            return false;
        }

        if ($this->motor->esDisponibilidad($pierna)) {
            return true;
        }

        return $otra > 0 && $this->motor->esDisponibilidad($otra);
    }

    /**
     * ING/EGR/IEV: banco dentro del límite mayor por concepto + crédito comercial 113 fuera.
     * La imputación ancla el 100% al banco (cuenta_disponibilidad); el plano no debe duplicar la pierna 113.
     */
    private function omitirPiernaPlanoSubdiarioBancoCreditoComercial(int $cuenta, int $contrapartida, bool $esPiernaCuenta): bool
    {
        if ($cuenta <= 0 || $contrapartida <= 0) {
            return false;
        }

        $tieneBancoDentroLimite = $this->motor->esDisponibilidad($cuenta) || $this->motor->esDisponibilidad($contrapartida);
        $tieneCreditoComercial = $this->motor->esCuentaCreditoComercialDisp($cuenta)
            || $this->motor->esCuentaCreditoComercialDisp($contrapartida);

        if (! $tieneBancoDentroLimite || ! $tieneCreditoComercial) {
            return false;
        }

        if ($esPiernaCuenta) {
            return $this->motor->esCuentaCreditoComercialDisp($cuenta);
        }

        return $this->motor->esCuentaCreditoComercialDisp($contrapartida);
    }

    /**
     * @param  array<int, array<string, mixed>>  $porCuenta
     */
    private function acumularMovimientoPlanoDisponibilidad(array &$porCuenta, int $cuenta, string $mov, float $importe): void
    {
        // El mayor por concepto topa en 112010008 (esDisponibilidad). Las cuentas por encima
        // del tope (113xxx coin/tarjetas, 112040xxx, etc.) no tienen mayor propio: su movimiento
        // se refleja en la caja/banco narrow contra la que operan, no se agrega al plano. Esto
        // evita el "Ajuste mayor plano" por doble conteo de la pierna > tope (la recaudación
        // coin 113 ya queda imputada en el banco donde se deposita).
        if ($cuenta <= 0 || $importe <= 0 || ! $this->motor->esDisponibilidad($cuenta)) {
            return;
        }

        if (! isset($porCuenta[$cuenta])) {
            $porCuenta[$cuenta] = ['cuenta' => $cuenta, 'debe' => 0.0, 'haber' => 0.0, 'movimientos' => 0];
        }

        if ($mov === 'D') {
            $porCuenta[$cuenta]['debe'] += $importe;
        } elseif ($mov === 'H') {
            $porCuenta[$cuenta]['haber'] += $importe;
        }
        $porCuenta[$cuenta]['movimientos']++;
    }

    /**
     * Mayor plano analítico: movimientos nativos + efecto contrapartida (subdiario + ctamov).
     *
     * @param  list<object>  $subdiario
     * @param  list<object>  $ctamovLista
     * @return array<int, array<string, mixed>>
     */
    private function construirMayorPlanoAnalitico(
        array $subdiario,
        array $ctamovLista,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        $porCuenta = [];

        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            $importe = $monedaConverter->convertirImporte(
                (float) ($linea->subd_importe ?? 0),
                (string) ($linea->subd_cod_mon ?? '1'),
                (float) ($linea->subd_cotizacion ?? 0),
                (int) ($linea->subd_fecha ?? 0),
                $monedaReporteId,
            );

            $this->acumularMovimientoPlanoAnalitico($porCuenta, $cuenta, $mov, $importe);

            if ($contrapartida > 0 && $contrapartida !== $cuenta) {
                $movContra = $mov === 'D' ? 'H' : 'D';
                $this->acumularMovimientoPlanoAnalitico($porCuenta, $contrapartida, $movContra, $importe);
            }
        }

        foreach ($ctamovLista as $lineaCtamov) {
            $adaptada = $this->ctamovComoSubdiario($lineaCtamov);
            if (! $this->lineaVisible($adaptada, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuenta = (int) ($lineaCtamov->ctav_cuenta ?? 0);
            $dh = strtoupper(trim((string) ($lineaCtamov->ctav_d_h ?? '')));
            $importe = $monedaConverter->convertirImporte(
                (float) ($lineaCtamov->ctav_importe ?? 0),
                (string) ($lineaCtamov->ctav_cod_mon ?? '1'),
                (float) ($lineaCtamov->ctav_cotizacion ?? 0),
                (int) ($lineaCtamov->ctav_fecha ?? 0),
                $monedaReporteId,
            );

            $this->acumularMovimientoPlanoAnalitico($porCuenta, $cuenta, $dh, $importe);
        }

        foreach ($porCuenta as $cuenta => $row) {
            $porCuenta[$cuenta]['debe'] = round($row['debe'], 2);
            $porCuenta[$cuenta]['haber'] = round($row['haber'], 2);
            $porCuenta[$cuenta]['cuenta_codigo'] = $this->motor->formatearCodigoCuenta($cuenta);
            $porCuenta[$cuenta]['cuenta_nombre'] = DB::table('cuentacontable')
                ->where('empresa_id', $this->empresaActiva)
                ->where('codigo', $cuenta)
                ->value('nombre') ?? $porCuenta[$cuenta]['cuenta_codigo'];
        }

        ksort($porCuenta);

        return $porCuenta;
    }

    /**
     * Mayor analítico de control agrupado por asiento (cuentas ≤ límite analítico).
     * Solo pierna nativa: subd_cuenta/subd_tipo_mov y ctav_cuenta/ctav_d_h (sin espejar contrapartida).
     *
     * @param  list<object>  $subdiario
     * @param  list<object>  $ctamovLista
     * @return array<int, array<string, mixed>>
     */
    private function construirAnaliticoPorAsientoControl(
        array $subdiario,
        array $ctamovLista,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        $porAsiento = [];
        $asientosCtamovMediosAnalitico = $this->indexarAsientosCtamovMediosAnalitico($ctamovLista);
        $asientosCtamovVentaGastro = $this->indexarAsientosCtamovVentaCobranza($ctamovLista);
        $asientosVentaLiteralPorConcepto = $this->indexarAsientosVentaLiteralPorConcepto($ctamovLista);
        $asientosVentaMaquinasCtamov = $this->indexarAsientosCtamovVentaMaquinas($ctamovLista);

        $subdiarioPorOperacion = [];
        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $nro = (int) ($linea->subd_nro_operacion ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $subdiarioPorOperacion[$nro][] = $linea;
        }

        foreach ($subdiarioPorOperacion as $nro => $lineasOp) {
            if (isset($asientosVentaMaquinasCtamov[$nro])) {
                continue;
            }

            if ($this->esAsientoTraspasoInternoDisponibilidad($lineasOp)) {
                foreach ($lineasOp as $linea) {
                    $this->acumularAnaliticoSubdiarioNativo(
                        $porAsiento,
                        $linea,
                        $monedaConverter,
                        $monedaReporteId,
                    );
                }

                continue;
            }

            foreach ($lineasOp as $linea) {
                $refTipo = trim((string) ($linea->subd_ref_tipo ?? $linea->subd_tipo ?? ''));
                $refTipoUpper = strtoupper(trim($refTipo));
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
                $importe = $monedaConverter->convertirImporte(
                    (float) ($linea->subd_importe ?? 0),
                    (string) ($linea->subd_cod_mon ?? '1'),
                    (float) ($linea->subd_cotizacion ?? 0),
                    (int) ($linea->subd_fecha ?? 0),
                    $monedaReporteId,
                );
                $fecha = (int) ($linea->subd_fecha ?? 0);

                if ($importe <= 0) {
                    continue;
                }

                if (in_array($refTipoUpper, ['ING', 'EGR', 'IEV'], true)) {
                    if ($this->debeIngresoEgresoDobleDisponibilidad($cuenta, $contrapartida)) {
                        if ($this->esLineaDisparadoraIngresoEgresoDobleDisponibilidad($linea, $cuenta, $contrapartida)) {
                            foreach ($this->itemsTraspasoDoblePierna($linea, $refTipoUpper) as $item) {
                                $this->acumularAnaliticoPorAsientoControl(
                                    $porAsiento,
                                    $nro,
                                    $fecha,
                                    (int) $item['cuenta_contra'],
                                    (string) $item['dh_imputacion'],
                                    $importe,
                                );
                            }
                        }

                        continue;
                    }

                    if ($this->acumularAnaliticoIngresoEgresoCreditoComercial(
                        $porAsiento,
                        $nro,
                        $fecha,
                        $linea,
                        $refTipoUpper,
                        $importe,
                    )) {
                        continue;
                    }

                    $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
                    $this->acumularAnaliticoPorAsientoControl($porAsiento, $nro, $fecha, $cuenta, $mov, $importe);

                    continue;
                }

                if ($this->debeTraspasoDoblePierna($cuenta, $contrapartida, $refTipoUpper)) {
                    foreach ($this->itemsTraspasoDoblePierna($linea, $refTipoUpper) as $item) {
                        $this->acumularAnaliticoPorAsientoControl(
                            $porAsiento,
                            $nro,
                            $fecha,
                            (int) $item['cuenta_contra'],
                            (string) $item['dh_imputacion'],
                            $importe,
                        );
                    }

                    continue;
                }

                if ($this->acumularAnaliticoIngresoEgresoCreditoComercial(
                    $porAsiento,
                    $nro,
                    $fecha,
                    $linea,
                    $refTipo,
                    $importe,
                )) {
                    continue;
                }

                $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
                $this->acumularAnaliticoPorAsientoControl($porAsiento, $nro, $fecha, $cuenta, $mov, $importe);
            }
        }

        foreach ($this->agruparCtamovPorAsiento($ctamovLista) as $lineasAsiento) {
            if ($lineasAsiento === []) {
                continue;
            }

            $nro = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
            $incluirMediosCtamov = isset($asientosCtamovMediosAnalitico[$nro]);
            $esVentaGastro = isset($asientosCtamovVentaGastro[$nro]);
            $esLiteralPorConcepto = isset($asientosVentaLiteralPorConcepto[$nro]);
            $esSistemaBCtamov = $this->esCtamovAsientoSistemaB($lineasAsiento);
            $esSistemaB = $this->debeProcesarAsientoCtamovSistemaB($lineasAsiento, $lineasOp);
            $esReclasificacionDispContra = $esSistemaBCtamov
                && $this->esReclasificacionDispContrapartidasCtamovSistemaB($lineasOp);
            $esVentaMaquinas = $esSistemaBCtamov && $this->esAsientoCtamovVentaMaquinas($lineasOp);
            $asientoMultimedio = count($this->mediosCobranzaDebeAsiento($lineasOp)) >= 2;

            foreach ($lineasAsiento as $lineaCtamov) {
                $adaptada = $this->ctamovComoSubdiario($lineaCtamov);
                if (! $this->lineaVisible($adaptada, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                    continue;
                }

                $cuenta = (int) ($lineaCtamov->ctav_cuenta ?? 0);
                $mov = strtoupper(trim((string) ($lineaCtamov->ctav_d_h ?? '')));
                $importe = $monedaConverter->convertirImporte(
                    (float) ($lineaCtamov->ctav_importe ?? 0),
                    (string) ($lineaCtamov->ctav_cod_mon ?? '1'),
                    (float) ($lineaCtamov->ctav_cotizacion ?? 0),
                    (int) ($lineaCtamov->ctav_fecha ?? 0),
                    $monedaReporteId,
                );
                $fecha = (int) ($lineaCtamov->ctav_fecha ?? 0);

                if ($esLiteralPorConcepto && ! $esReclasificacionDispContra) {
                    // Literal por concepto (máquinas/gastro/sistema B parcial): analítico ≤ límite.
                    if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                        continue;
                    }
                } elseif ($esVentaGastro && ! $esReclasificacionDispContra && ! $esVentaMaquinas) {
                    if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                        continue;
                    }
                } elseif ($esSistemaBCtamov && ! $esVentaMaquinas && ! $esReclasificacionDispContra && ! $incluirMediosCtamov) {
                    // Sistema B puro (no gastro): solo caja/banco ≤ límite analítico.
                    if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta)) {
                        continue;
                    }
                } elseif ($incluirMediosCtamov && $esSistemaB
                    && ! $this->motor->esCuentaAnaliticoControl($cuenta)
                    && ! $this->esPiernaDirectaNativaCtamovSistemaB($cuenta, $mov, $asientoMultimedio)) {
                    continue;
                }

                if ($esReclasificacionDispContra
                    && (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta))) {
                    continue;
                }

                if ($esVentaMaquinas
                    && (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esCuentaAnaliticoControl($cuenta))) {
                    continue;
                }

                // Literal/gastro: 113/211 van a concepto; no ampliar analítico.
                $this->acumularAnaliticoPorAsientoControl(
                    $porAsiento,
                    $nro,
                    $fecha,
                    $cuenta,
                    $mov,
                    $importe,
                    $incluirMediosCtamov && ! $esLiteralPorConcepto && ! $esVentaGastro
                        && ! $esReclasificacionDispContra && ! $esVentaMaquinas,
                );
            }
        }

        foreach ($porAsiento as $nro => $row) {
            $cuentas = array_keys($row['cuentas']);
            sort($cuentas);
            $porAsiento[$nro]['debe'] = round($row['debe'], 2);
            $porAsiento[$nro]['haber'] = round($row['haber'], 2);
            $porAsiento[$nro]['cuentas'] = $cuentas;
        }

        ksort($porAsiento, SORT_NUMERIC);

        return $porAsiento;
    }

    /**
     * @param  array<int, array<string, mixed>>  $porAsiento
     */
    private function acumularAnaliticoSubdiarioNativo(
        array &$porAsiento,
        object $linea,
        MayorConceptoMonedaConverter $monedaConverter,
        int $monedaReporteId,
    ): void {
        $nro = (int) ($linea->subd_nro_operacion ?? 0);
        $cuenta = (int) ($linea->subd_cuenta ?? 0);
        $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
        $importe = $monedaConverter->convertirImporte(
            (float) ($linea->subd_importe ?? 0),
            (string) ($linea->subd_cod_mon ?? '1'),
            (float) ($linea->subd_cotizacion ?? 0),
            (int) ($linea->subd_fecha ?? 0),
            $monedaReporteId,
        );
        $fecha = (int) ($linea->subd_fecha ?? 0);

        $this->acumularAnaliticoPorAsientoControl($porAsiento, $nro, $fecha, $cuenta, $mov, $importe);
    }

    /**
     * IEV/ING/EGR: el subdiario puede traer solo la pierna fuera del límite (113, 116 interco, etc.)
     * con el banco en contrapartida. Concepto imputa esa contrapartida; el analítico debe reflejar
     * el D/H del banco ≤ límite para que NetoA + NetoC = 0.
     *
     * @param  array<int, array<string, mixed>>  $porAsiento
     */
    private function acumularAnaliticoIngresoEgresoCreditoComercial(
        array &$porAsiento,
        int $nroAsiento,
        int $fecha,
        object $linea,
        string $refTipo,
        float $importe,
    ): bool {
        if ($importe <= 0 || ! in_array(strtoupper(trim($refTipo)), ['ING', 'EGR', 'IEV'], true)) {
            return false;
        }

        $imputacion = $this->imputacionIngresoEgreso($linea, $refTipo);
        if ($imputacion === null) {
            return false;
        }

        $cuentaContra = (int) ($imputacion['cuenta_contra'] ?? 0);
        $cuentaBanco = (int) ($imputacion['cuenta_disponibilidad'] ?? 0);
        if ($cuentaContra <= 0 || $cuentaBanco <= 0) {
            return false;
        }

        // Banco dentro del mayor analítico; contrapartida fuera (si ambas ≤ límite ya lo cubre
        // doble disponibilidad / pierna nativa).
        if (! $this->motor->esCuentaAnaliticoControl($cuentaBanco)
            || $this->motor->esCuentaAnaliticoControl($cuentaContra)) {
            return false;
        }

        $dhConcepto = strtoupper(trim((string) ($imputacion['dh_imputacion'] ?? 'D')));
        if (! in_array($dhConcepto, ['D', 'H'], true)) {
            return false;
        }

        $movAnalitico = $dhConcepto === 'D' ? 'H' : 'D';
        $this->acumularAnaliticoPorAsientoControl(
            $porAsiento,
            $nroAsiento,
            $fecha,
            $cuentaBanco,
            $movAnalitico,
            $importe,
        );

        return true;
    }

    /**
     * Ctamov sistema B/V con medios 113/111: mismas piernas nativas que imputa procesarAsientoCtamovSistemaB.
     *
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosCtamovMediosAnalitico(array $ctamovLista): array
    {
        $index = $this->indexarAsientosCtamovVentaCobranza($ctamovLista);

        foreach ($this->agruparCtamovPorAsiento($ctamovLista) as $lineasAsiento) {
            if ($lineasAsiento === []) {
                continue;
            }

            $nro = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
            if ($nro <= 0 || isset($index[$nro])) {
                continue;
            }

            $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
            if ($this->debeProcesarAsientoCtamovSistemaB($lineasAsiento, $lineasOp)
                || $this->debeProcesarAsientoCtamovVentaMaquinas($lineasAsiento, $lineasOp)) {
                $index[$nro] = true;
            }
        }

        return $index;
    }

    /**
     * Ctamov gastro/estacionamiento (sistema B o V): marca asientos cuya cobranza 113/211
     * va a concepto (analítico solo caja ≤ límite).
     *
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosCtamovVentaCobranza(array $ctamovLista): array
    {
        $index = [];

        foreach ($this->agruparCtamovPorAsiento($ctamovLista) as $lineasAsiento) {
            if ($lineasAsiento === []) {
                continue;
            }

            $nro = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $sistemaOk = true;
            foreach ($lineasAsiento as $linea) {
                if (! in_array(strtoupper(trim((string) ($linea->ctav_sistema ?? ''))), ['B', 'V'], true)) {
                    $sistemaOk = false;
                    break;
                }
            }

            if (! $sistemaOk) {
                continue;
            }

            $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
            if ($this->esCtamovVentaGastronomiaEstacionamiento($lineasAsiento, $lineasOp)) {
                $index[$nro] = true;
            }
        }

        return $index;
    }

    /**
     * @param  list<object>  $ctamovLista
     * @return array<int, true>
     */
    private function indexarAsientosCtamovVentaMaquinas(array $ctamovLista): array
    {
        $index = [];

        foreach ($this->agruparCtamovPorAsiento($ctamovLista) as $lineasAsiento) {
            if ($lineasAsiento === []) {
                continue;
            }

            $nro = (int) ($lineasAsiento[0]->ctav_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }

            $lineasOp = $this->ctamovAsientoComoLineasOp($lineasAsiento);
            if ($this->esCtamovAsientoSistemaB($lineasAsiento)
                && $this->esAsientoCtamovVentaMaquinas($lineasOp)) {
                $index[$nro] = true;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, array<string, mixed>>  $porAsiento
     */
    private function acumularAnaliticoPorAsientoControl(
        array &$porAsiento,
        int $nroAsiento,
        int $fecha,
        int $cuenta,
        string $mov,
        float $importe,
        bool $incluirMediosCobranzaVentaCtamov = false,
    ): void {
        if ($nroAsiento <= 0 || $importe <= 0) {
            return;
        }

        if (! in_array($mov, ['D', 'H'], true)) {
            return;
        }

        $incluir = $this->motor->esCuentaAnaliticoControl($cuenta)
            || ($incluirMediosCobranzaVentaCtamov && $this->esMedioCobranzaCtamovVenta($cuenta, $mov))
            || ($incluirMediosCobranzaVentaCtamov && $this->esPiernaDirectaCreditoComercialCtamovSistemaB($cuenta, $mov))
            || ($incluirMediosCobranzaVentaCtamov && $mov === 'D' && $this->esCuentaPasivaPublicoCtamovSistemaB($cuenta));

        if (! $incluir) {
            return;
        }

        if (! isset($porAsiento[$nroAsiento])) {
            $porAsiento[$nroAsiento] = [
                'nro_asiento' => $nroAsiento,
                'debe' => 0.0,
                'haber' => 0.0,
                'fecha_min' => $fecha > 0 ? $fecha : null,
                'cuentas' => [],
            ];
        }

        if ($fecha > 0 && ($porAsiento[$nroAsiento]['fecha_min'] === null || $fecha < $porAsiento[$nroAsiento]['fecha_min'])) {
            $porAsiento[$nroAsiento]['fecha_min'] = $fecha;
        }

        $codigo = $this->motor->formatearCodigoCuenta($cuenta);
        $porAsiento[$nroAsiento]['cuentas'][$codigo] = true;

        if ($mov === 'D') {
            $porAsiento[$nroAsiento]['debe'] += $importe;
        } else {
            $porAsiento[$nroAsiento]['haber'] += $importe;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $porCuenta
     */
    private function acumularMovimientoPlanoAnalitico(array &$porCuenta, int $cuenta, string $mov, float $importe): void
    {
        if ($cuenta <= 0 || $importe <= 0) {
            return;
        }

        if (! isset($porCuenta[$cuenta])) {
            $porCuenta[$cuenta] = ['cuenta' => $cuenta, 'debe' => 0.0, 'haber' => 0.0, 'movimientos' => 0];
        }

        if ($mov === 'D') {
            $porCuenta[$cuenta]['debe'] += $importe;
        } elseif ($mov === 'H') {
            $porCuenta[$cuenta]['haber'] += $importe;
        }
        $porCuenta[$cuenta]['movimientos']++;
    }

    private function proveedorInscripto(string $proveedor): bool
    {
        if ($proveedor === '') {
            return false;
        }
        if (! isset($this->promaeCache[$proveedor])) {
            $this->consultasBridgeIndividuales++;
            $this->promaeCache[$proveedor] = $this->reader->cargarPromae($proveedor, $this->erroresBridge);
        }
        $prom = $this->promaeCache[$proveedor];

        return $prom && trim((string) ($prom->prom_cond_iva ?? '')) === '1';
    }

    /**
     * @param  list<object>  $comSub
     * @return list<object>
     */
    private function filtrarComGasto(array $comSub): array
    {
        $lineas = array_values(array_filter($comSub, function ($linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));

            return $mov === 'D'
                && ! $this->motor->esProveedor($cuenta)
                && ! $this->motor->esDisponibilidad($cuenta)
                && $cuenta !== 521130001;
        }));

        $vistas = [];
        $unicas = [];

        foreach ($lineas as $linea) {
            $clave = (int) ($linea->subd_cuenta ?? 0)
                .'|'.number_format((float) ($linea->subd_importe ?? 0), 2, '.', '');
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $unicas[] = $linea;
        }

        return $unicas;
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function cuentaRetencion(array $lineasOp, object $retencion): int
    {
        $monto = (float) ($retencion->axp_monto_ap ?? 0);

        foreach ($lineasOp as $linea) {
            if (abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                $c = (int) ($linea->subd_cuenta ?? 0);
                if ($c >= 214010000 && $c < 215000000) {
                    return $c;
                }
            }
        }

        return $this->cuentaRetencionPorTipo(trim((string) ($retencion->axp_tipo_ap ?? '')));
    }

    private function cuentaRetencionPorTipo(string $tipoAp): int
    {
        return match (strtoupper(trim($tipoAp))) {
            'RTP' => 214010014,
            'RGP' => 214010013,
            'RIV' => 214010004,
            'RSP' => 214010008,
            default => 214010013,
        };
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function buscarLineaRetencionSubdiario(array $lineasOp, int $cuenta, float $monto): ?object
    {
        foreach ($lineasOp as $linea) {
            if ((int) ($linea->subd_cuenta ?? 0) === $cuenta
                && strtoupper(trim((string) ($linea->subd_tipo_mov ?? ''))) === 'H'
                && abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                return $linea;
            }
        }

        return $this->buscarLinea($lineasOp, $cuenta, $monto);
    }

    /**
     * Retenciones descontadas en OPP: subdiario H en 214xxx → Haber en el reporte.
     * Depósitos directos (solo contrapartida D): Debe en el reporte.
     *
     * @param  list<object>  $lineasOp
     */
    private function dhImputacionRetencion(array $lineasOp, int $cuentaRet, float $monto): string
    {
        foreach ($lineasOp as $linea) {
            if ((int) ($linea->subd_cuenta ?? 0) === $cuentaRet
                && strtoupper(trim((string) ($linea->subd_tipo_mov ?? ''))) === 'H'
                && abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                return 'H';
            }
        }

        return 'D';
    }

    /**
     * @param  list<object>  $lineasOp
     */
    private function buscarLinea(array $lineasOp, int $cuenta, float $monto): ?object
    {
        foreach ($lineasOp as $linea) {
            if ((int) ($linea->subd_cuenta ?? 0) === $cuenta && abs((float) ($linea->subd_importe ?? 0) - $monto) < 0.01) {
                return $linea;
            }
        }

        return null;
    }

    private function esFactura(object $fila): bool
    {
        return $this->tcompSupport->esFacturaAplicada($fila);
    }

    private function esRetencion(object $fila): bool
    {
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        return in_array($t, ['RTP', 'RGP', 'RSP', 'RIV', 'RGU', 'RLP', 'RSU'], true);
    }
}
