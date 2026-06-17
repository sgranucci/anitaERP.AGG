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

    private int $empresaActiva = 0;

    private readonly MayorConceptoMediopagoSupport $mediopagoSupport;

    public function __construct(
        private readonly MayorConceptoMemoriaMotor $motor,
        private readonly MayorConceptoAnitaBridgeReader $reader,
        ?MayorConceptoMediopagoSupport $mediopagoSupport = null,
    ) {
        $this->mediopagoSupport = $mediopagoSupport ?? new MayorConceptoMediopagoSupport();
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
        $this->motor->prepararEmpresa($empresaId, $datos['ctaconc'] ?? []);
        $this->erroresBridge = array_merge($this->erroresBridge, $datos['errores'] ?? []);

        $subdiario = $datos['subdiario'] ?? [];
        $ctamovLista = $datos['ctamov'] ?? [];
        $auxpagLista = $datos['auxpag'] ?? [];

        $auxpagPorOp = [];
        foreach ($auxpagLista as $axp) {
            $clave = $this->claveOperacionPago(
                trim((string) ($axp->axp_tipo ?? '')),
                (int) ($axp->axp_rec ?? 0),
                (int) ($axp->axp_fecha ?? 0),
            );
            $auxpagPorOp[$clave][] = $axp;
        }

        $statsPreload = $this->precargarCachesCompras($auxpagLista);

        $subdiarioPorAsiento = $this->indexarSubdiarioPorAsiento($subdiario);
        $ctamovPorAsiento = $this->indexarCtamovPorAsiento($ctamovLista);

        $lineasReporte = [];
        $opsProcesadas = [];

        foreach ($subdiario as $linea) {
            if (! $this->lineaVisible($linea, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $refTipo = trim((string) ($linea->subd_ref_tipo ?? ''));
            if (! in_array($refTipo, MayorConceptoMemoriaMotor::TIPOS_REF_IMPUTABLE, true)) {
                continue;
            }

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if (! $this->motor->esDisponibilidad($cuenta) && ! $this->motor->esDisponibilidad($contrapartida)) {
                continue;
            }

            $claveOp = $this->claveOperacionPago(
                $refTipo,
                (int) ($linea->subd_ref_nro ?? 0),
                (int) ($linea->subd_fecha ?? 0),
            );

            if (isset($opsProcesadas[$claveOp])) {
                continue;
            }

            $lineasOp = $this->filtrarSubdiarioPorRef($subdiario, $linea);
            $nroAsiento = (int) ($linea->subd_nro_operacion ?? 0);
            $fechaAsiento = (int) ($linea->subd_fecha ?? 0);
            if ($nroAsiento > 0) {
                $lineasOp = $this->mergeLineasOpSubdiarioCtamov(
                    $lineasOp,
                    [],
                    $ctamovPorAsiento[$this->claveAsientoIndex($nroAsiento, $fechaAsiento)] ?? [],
                );
            }

            if ($this->debeProcesarComoPagoProveedor($refTipo, $lineasOp, $auxpagPorOp, $claveOp)) {
                $lineasReporte = array_merge(
                    $lineasReporte,
                    $this->procesarPago($empresaId, $lineasOp, $auxpagPorOp[$claveOp] ?? [], $monedaConverter, $monedaReporteId),
                );
            } else {
                $lineasReporte = array_merge(
                    $lineasReporte,
                    $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen),
                );
            }

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
            'stats' => array_merge([
                'subdiario_filas' => count($subdiario),
                'ctamov_filas' => count($ctamovLista),
                'auxpag_filas' => count($auxpagLista),
                'operaciones_procesadas' => count($opsProcesadas),
            ], $statsPreload),
            'mayor_plano_disponibilidad' => $mayorPlano,
            'mayor_plano_analitico' => $mayorPlanoAnalitico,
            'mayor_plano_contrapartidas_disponibilidad' => $this->finalizarPlanoContrapartidasDesdeDisp(),
        ];
    }

    private function resetCaches(): void
    {
        $this->comSubdiarioCache = [];
        $this->aplicpedCache = [];
        $this->ordenesComPorFactura = [];
        $this->promaeCache = [];
        $this->erroresBridge = [];
        $this->planoContrapartidasDesdeDisp = [];
        $this->consultasBridgeIndividuales = 0;
        $this->conceptoPorOcCache = [];
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

            if ($prov === '' || $tipoAp === '' || $nroAp <= 0) {
                continue;
            }

            $clave = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
            $seeds[$clave] = [$prov, $tipoAp, $letraAp, $sucAp, $nroAp];
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
                    $pendientes[] = [$prov, $refTipo, $refLetra, $refSuc, $refNro];
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
            foreach ($this->reader->cargarComSubdiarioLote($faltantes, $this->erroresBridge) as $clave => $lineas) {
                $this->comSubdiarioCache[$clave] = $lineas;
            }
        }

        return [
            'aplicped_precargadas' => array_sum(array_map('count', $this->aplicpedCache)),
            'promae_precargados' => count($this->promaeCache),
            'com_subdiario_precargados' => count($this->comSubdiarioCache),
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
        $totalFacturas = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $facturas));
        $totalCheques = array_sum(array_map(fn ($f) => (float) ($f->axp_monto_ap ?? 0), $cheques));

        if ($totalFacturas <= 0 && $totalCheques <= 0) {
            return $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, false);
        }

        $cuentaBanco = $this->resolverCuentaDisponibilidad($lineaBanco ?? $lineaRef);
        if ($cuentaBanco <= 0) {
            return $lineas;
        }

        $imputoGastoDesdeFacturas = false;

        if ($this->debeImputarChequeProveedor($totalCheques, $totalFacturas, $totalBancoHaber, $auxpag)) {
            foreach ($cheques as $cheque) {
                $monto = (float) ($cheque->axp_monto_ap ?? 0);
                if ($monto <= 0) {
                    continue;
                }

                $cuentaCheque = $this->cuentaChequeMayorConcepto($cheque, $auxpag, $empresaId);

                $lineas[] = $this->lineaReporte(
                    $lineaBanco ?? $lineaRef,
                    $cuentaCheque,
                    $this->conceptoImputacionGasto($empresaId, $cuentaCheque, 'CHP'),
                    $monto,
                    'D',
                    $monedaConverter,
                    $monedaReporteId,
                    'OPP cheque CHP',
                    $this->metaPagoProveedor($empresaId, $cheque, null, $cuentaBanco),
                );
            }
        } elseif ($facturas !== []) {
            $imputoGastoDesdeFacturas = true;

            foreach ($facturas as $aplicacion) {
                $gastoAgrupadoFactura = [];
                $montoFactura = (float) ($aplicacion->axp_monto_ap ?? 0);
                if ($montoFactura <= 0) {
                    continue;
                }

                $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
                $inscripto = $this->proveedorInscripto(trim((string) ($aplicacion->axp_pro ?? '')));
                $montoBanco = $totalBancoHaber > 0
                    ? $totalBancoHaber * ($montoFactura / max($totalFacturas, 1.0))
                    : $montoFactura;

                $lineasGasto = $this->cargarGastoDesdeAplicacion($aplicacion);
                $anticipo114040 = $this->subTieneAnticipo114040($lineasGasto);
                if ($anticipo114040) {
                    $lineasGasto = $this->filtrarLineasAnticipoProveedor($lineasGasto);
                }

                $totalNeto = array_sum(array_map(fn ($l) => (float) ($l->subd_importe ?? 0), $lineasGasto));
                if ($totalNeto <= 0) {
                    continue;
                }

                $nroOc = $this->ordenComDesdeAplicacion($aplicacion);
                $fisAdelantado = $tipoAp === 'FIS' && ! $this->aplicacionTieneComGasto($aplicacion);
                $gastoAdelantado = ! $this->aplicacionTieneComGasto($aplicacion);
                $fisServicios = $fisAdelantado && ! $anticipo114040;
                $parteIva = max(0.0, $montoFactura - $totalNeto);
                $percepcionesFactura = $this->percepcionesRetencionDesdeAplicacion($aplicacion, $montoBanco, $montoFactura);
                $totalPercepciones = array_sum(array_map(fn ($p) => (float) ($p['importe'] ?? 0), $percepcionesFactura));

                if ($this->comGastoEsSolo117010($lineasGasto)) {
                    $cuentaCheque = 117010001;
                    $claveAgrup = $cuentaCheque.'|'.$this->motor->conceptoDeCuenta($empresaId, $cuentaCheque);
                    $gastoAgrupadoFactura[$claveAgrup] = [
                        'cuenta' => $cuentaCheque,
                        'concepto' => $this->motor->conceptoDeCuenta($empresaId, $cuentaCheque),
                        'importe' => round($montoBanco, 2),
                        'origen_log' => 'COM cheque 117010',
                        'aplicacion' => $aplicacion,
                        'linea_gasto' => null,
                    ];
                } else {
                    foreach ($lineasGasto as $lineaGasto) {
                        $netoLinea = (float) ($lineaGasto->subd_importe ?? 0);
                        if ($netoLinea <= 0) {
                            continue;
                        }

                        $cuentaGasto = $this->cuentaVisibleDesdeDocumentoGasto($lineaGasto, $tipoAp, $anticipo114040);
                        if ($cuentaGasto <= 0) {
                            continue;
                        }

                        $conceptoGasto = $this->conceptoImputacionGasto(
                            $empresaId,
                            $cuentaGasto,
                            $tipoAp,
                            $nroOc,
                            trim((string) ($aplicacion->axp_pro ?? '')),
                        );

                        if ($anticipo114040) {
                            $netoImp = $totalNeto > 0
                                ? round($montoBanco * ($netoLinea / $totalNeto), 2)
                                : $montoBanco;
                            $ivaImp = 0.0;
                            if ($tipoAp === 'FNB' && $cuentaGasto >= 114040000 && $cuentaGasto < 114050000) {
                                $netoImp = round(max(0.0, $montoBanco - $totalPercepciones), 2);
                            }
                        } elseif ($tipoAp === 'FNB' && $cuentaGasto >= 114040000 && $cuentaGasto < 114050000) {
                            $netoImp = $montoBanco;
                            $ivaImp = 0.0;
                        } elseif ($fisServicios) {
                            $netoImp = $netoLinea;
                            $ivaImp = 0.0;
                        } elseif ($gastoAdelantado) {
                            $netoImp = $totalNeto > 0
                                ? round($montoBanco * ($netoLinea / $totalNeto), 2)
                                : $montoBanco;
                            $ivaImp = 0.0;
                        } elseif ($tipoAp === 'FIS') {
                            $netoImp = $fisAdelantado
                                ? $netoLinea
                                : ($totalNeto > 0
                                    ? round($montoBanco * ($netoLinea / $totalNeto), 2)
                                    : $montoBanco);
                            $ivaImp = 0.0;
                        } elseif ($tipoAp === 'FGA') {
                            $netoImp = $totalNeto > 0
                                ? round($montoBanco * ($netoLinea / $totalNeto), 2)
                                : $montoBanco;
                            $ivaImp = 0.0;
                        } else {
                            $peso = $netoLinea / $totalNeto;
                            $netoImp = $montoBanco * ($netoLinea / $montoFactura);
                            $ivaImp = 0.0;
                            if ($inscripto) {
                                $ivaImp = $montoBanco * ($parteIva / $montoFactura) * $peso;
                            }
                        }

                        $origenGasto = match (true) {
                            $anticipo114040 => 'Anticipo 114040',
                            $tipoAp === 'FGA' => 'FGA COM neto',
                            $fisServicios => 'FIS gasto adelantado',
                            $gastoAdelantado => 'Factura adelantada',
                            $tipoAp === 'FIS' => 'COM neto',
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
        }

        if (! $imputoGastoDesdeFacturas) {
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

                $lineas[] = $this->lineaReporte(
                    $lineaOrigenRet,
                    $cuentaRet,
                    $this->motor->conceptoImputacionCuenta($empresaId, $cuentaRet),
                    $monto,
                    $dhRet,
                    $monedaConverter,
                    $monedaReporteId,
                    'Retención '.($retencion->axp_tipo_ap ?? ''),
                    $this->metaRetencionPago($empresaId, $retencion),
                );
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

        return $lineas;
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

        foreach ($this->contrapartidasImputablesAsiento($lineasOp, $refTipo) as $item) {
            if (! $this->lineaVisible($item['linea'], $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $cuentaBanco = (int) ($item['cuenta_disponibilidad'] ?? 0);
            if ($cuentaBanco <= 0) {
                $cuentaBanco = $this->resolverCuentaDisponibilidad($item['linea']);
            }
            if ($cuentaBanco <= 0 || $this->motor->esCuentaCreditoComercialDisp($cuentaBanco)) {
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
        }

        $lineasOp = array_values(array_filter(
            $lineasOp,
            fn ($l) => $this->lineaVisible($l, $monedaConverter, $monedaReporteId, $soloMonedaOrigen),
        ));

        if ($lineasOp === []) {
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
            $refTipo,
            (int) ($ref->subd_ref_nro ?? 0),
            $fecha,
        );

        if (isset($opsProcesadas[$claveOp])) {
            return [];
        }

        if ($this->debeProcesarComoPagoProveedor($refTipo, $lineasOp, $auxpagPorOp, $claveOp)) {
            $lineas = $this->procesarPago($empresaId, $lineasOp, $auxpagPorOp[$claveOp] ?? [], $monedaConverter, $monedaReporteId);
        } elseif ($this->debeProcesarAsientoMultilinea($lineasOp, $refTipo)) {
            $lineas = $this->procesarAsientoMultilinea($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $refTipo);
        } else {
            $lineas = $this->procesarDirectoAsiento($empresaId, $lineasOp, $monedaConverter, $monedaReporteId, $soloMonedaOrigen, $refTipo);
        }

        if ($lineas !== []) {
            $opsProcesadas[$claveCtamov] = true;
            $opsProcesadas[$this->claveAsientoContable($nroAsiento, $fecha)] = true;
        }

        return $lineas;
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

        return trim((string) ($ref->ctav_tipo ?? '')) === 'COM'
            && (int) ($ref->ctav_o_compra ?? 0) > 0;
    }

    /**
     * Recepción COM en ctamov (AnitaERP): imputa gasto 521xxx con ancla 117010 y PEP en ctav_o_compra.
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
            return true;
        }

        return $this->asientoTieneProveedor($lineasOp) && ! empty($auxpagPorOp[$claveOp]);
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
        $items = [];
        $vistas = [];
        $refTipo = strtoupper(trim($refTipo));

        foreach ($lineasOp as $linea) {
            foreach ($this->itemsImputacionDesdeLinea($linea, $refTipo) as $item) {
                $clave = $item['cuenta_contra'].'|'.number_format($item['importe'], 2, '.', '').'|'.$item['dh_imputacion']
                    .'|'.($item['cuenta_disponibilidad'] ?? 0);
                if (isset($vistas[$clave])) {
                    continue;
                }
                $vistas[$clave] = true;
                $items[] = $item;
            }
        }

        return $items;
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

        if ($this->debeTraspasoDoblePierna($cuenta, $contrapartida, $refTipo)) {
            $items = $this->itemsTraspasoDoblePierna($linea, $refTipo);
            if ($items !== []) {
                return $items;
            }
        }

        if (in_array($refTipo, ['ING', 'EGR', 'IEV'], true)) {
            $imputacion = $this->imputacionIngresoEgreso($linea, $refTipo);
            if ($imputacion !== null) {
                return [$imputacion];
            }
        }

        return $this->imputacionMovimientoDirectoLegacy($linea);
    }

    private function debeTraspasoDoblePierna(int $cuenta, int $contrapartida, string $refTipo): bool
    {
        if (! $this->motor->esDisponibilidad($cuenta) || ! $this->motor->esDisponibilidad($contrapartida)) {
            return false;
        }

        if ($this->motor->esProveedor($cuenta) || $this->motor->esProveedor($contrapartida)) {
            return false;
        }

        if ($this->motor->esCuentaCreditoComercialDisp($cuenta)
            || $this->motor->esCuentaCreditoComercialDisp($contrapartida)) {
            return false;
        }

        if ($refTipo === 'TRF') {
            return true;
        }

        if (in_array($refTipo, ['0', ''], true)) {
            return $this->motor->esDisponibilidad($cuenta)
                && $this->motor->esDisponibilidad($contrapartida)
                && ! $this->motor->esCuentaCreditoComercialDisp($cuenta)
                && ! $this->motor->esCuentaCreditoComercialDisp($contrapartida);
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
     * ING/EGR: imputa la contrapartida (gasto, crédito comercial 113…) como un COM de proveedor.
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

        if ($this->motor->esCuentaBancoCaja($cuentaContra)) {
            return null;
        }

        if ($this->motor->esCuentaInversionDisp($cuentaContra)) {
            return null;
        }

        $dhImputacion = $mov === 'D' ? 'H' : 'D';

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
                $dhImputacion = $mov === 'H' ? 'D' : 'H';
            } elseif ($this->motor->esCuentaBancoCaja($cuenta)
                || $this->motor->esCuentaInversionDisp($cuenta)) {
                return [];
            } else {
                $cuentaContra = $cuenta;
                $dhImputacion = $mov === 'D' ? 'D' : 'H';
            }
        } elseif ($this->motor->esDisponibilidad($contrapartida)) {
            $cuentaContra = $cuenta;
            $dhImputacion = $mov === 'D' ? 'D' : 'H';
        } else {
            return [];
        }

        if ($this->motor->esProveedor($cuentaContra)) {
            return [];
        }

        return [[
            'cuenta_contra' => $cuentaContra,
            'importe' => $importe,
            'dh_imputacion' => $dhImputacion,
            'linea' => $linea,
            'origen' => 'Movimiento directo',
        ]];
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
            $cuentaDisp = $this->resolverBancoParaLineaAsiento($linea, $lineasOp);
            if ($cuentaDisp <= 0) {
                $cuentaDisp = $bancoReferencia > 0 ? $bancoReferencia : $cuenta;
            }

            if ($this->motor->esCuentaCreditoComercialDisp($cuenta)) {
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

            $lineas[] = $this->lineaReporte(
                $linea,
                $cuenta,
                $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
                $importe,
                $mov,
                $monedaConverter,
                $monedaReporteId,
                $refTipo.' contrapartida asiento',
                [
                    'cuenta_disponibilidad' => $cuentaDisp,
                    'emisor' => trim((string) ($linea->subd_emisor ?? '')),
                ],
            );
        }

        return $lineas;
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
    ): int {
        $tipoAp = strtoupper(trim($tipoAp));

        if ($tipoAp === 'FIS' && $cuenta === 114020009) {
            return 0;
        }

        if ($cuenta >= 114040000 && $cuenta < 114050000) {
            $concepto = $this->motor->conceptoDeCuenta($empresaId, $cuenta);
            if ($concepto > 0) {
                return $concepto;
            }

            if ($nroOc > 0) {
                return $this->resolverConceptoDesdeOrdenCompra($empresaId, $nroOc, $proveedor);
            }

            return 0;
        }

        return $this->motor->conceptoImputacionCuenta($empresaId, $cuenta);
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
     * CHP: 521240 gaming si hay OC (FNB/PEP), 114040/FNB-COM si aplica; si no 117010.
     *
     * @param  list<object>  $auxpag
     */
    private function cuentaChequeMayorConcepto(?object $cheque, array $auxpag = [], int $empresaId = 0): int
    {
        $cuentaGaming = $this->resolverCuentaGastoGamingDesdeOp($auxpag);
        if ($cuentaGaming > 0) {
            return $cuentaGaming;
        }

        return 117010001;
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

        foreach ($auxpag as $aplicacion) {
            if (strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? ''))) !== 'FNB') {
                continue;
            }

            if ($this->ordenComDesdeAplicacion($aplicacion) > 0) {
                return 521240002;
            }
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
            if ($disp <= 0) {
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

            if (abs($dDebe) >= 0.05) {
                $remanente[] = $this->lineaRemanenteMayorPlano(
                    $empresaId,
                    $cuenta,
                    $dDebe,
                    'D',
                    $monedaConverter,
                    $monedaReporteId,
                );
            }

            if (abs($dHaber) >= 0.05) {
                $remanente[] = $this->lineaRemanenteMayorPlano(
                    $empresaId,
                    $cuenta,
                    $dHaber,
                    'H',
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
            $this->motor->conceptoImputacionCuenta($empresaId, $cuenta),
            $importe,
            $dh,
            $monedaConverter,
            $monedaReporteId,
            'Remanente mayor plano',
            ['cuenta_disponibilidad' => $cuenta],
        );
    }

    /**
     * Indica si la factura FIS resuelve gasto vía cadena COM (aplicped).
     */
    private function aplicacionTieneComGasto(object $aplicacion): bool
    {
        return $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion)) !== [];
    }

    /**
     * @return list<object>
     */
    /**
     * Percepciones/retenciones 214xxx del subdiario del comprobante, prorrateadas al monto banco.
     *
     * @return list<array{cuenta: int, importe: float}>
     */
    private function percepcionesRetencionDesdeAplicacion(
        object $aplicacion,
        float $montoBanco,
        float $montoFactura,
    ): array {
        if ($montoBanco <= 0 || $montoFactura <= 0) {
            return [];
        }

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

        $resultado = [];
        foreach ($porCuenta as $cuenta => $importeSub) {
            if ($importeSub <= 0) {
                continue;
            }

            $resultado[] = [
                'cuenta' => $cuenta,
                'importe' => round($montoBanco * ($importeSub / $montoFactura), 2),
            ];
        }

        return $resultado;
    }

    private function cargarGastoDesdeAplicacion(object $aplicacion): array
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));

        if ($tipoAp === 'FIS') {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== []) {
                return $comGasto;
            }

            $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);

            return $this->resolverGastoFisSubdiario($sub);
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
        }

        if (in_array($tipoAp, ['FIB', 'FIC', 'FID', 'FIE', 'FIF', 'FIG', 'FIH', 'FIA'], true)) {
            $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
            if ($comGasto !== []) {
                return $comGasto;
            }

            $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);

            return $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
        }

        $comGasto = $this->filtrarComGasto($this->cargarComDesdeFactura($aplicacion));
        if ($comGasto !== []) {
            return $comGasto;
        }

        $sub = $this->cargarSubdiarioComprobanteAplicacion($aplicacion);

        return $this->filtrarLineasFacturaAdelantadaMayorConcepto($sub);
    }

    /**
     * FIS sin COM: anticipo 114040 (públicidad/gaming) o servicios 114010→114020-009, o reimputa 521xxx.
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

        $cuentaGasto = 0;
        foreach ($todas as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 521000000 && $cuenta < 600000000 && $cuenta !== 521130001) {
                $cuentaGasto = $cuenta;
                break;
            }
        }

        if ($cuentaGasto <= 0) {
            return $this->filtrarLineasFisMayorConcepto($sub);
        }

        $lineas = [];
        foreach ($todas as $linea) {
            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            if ($cuenta >= 214010000 && $cuenta < 215000000) {
                continue;
            }

            $clon = clone $linea;
            $clon->subd_cuenta = $cuentaGasto;
            $lineas[] = $clon;
        }

        return $lineas;
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
            if (! $this->motor->esCuentaBancoCaja($cuenta)) {
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

        if ($nroAp <= 0) {
            $nroAp = (int) ($aplicacion->axp_nro_interno ?? 0);
        }

        $clave = $tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
        if (! isset($this->comSubdiarioCache[$clave])) {
            $this->comSubdiarioCache[$clave] = $this->reader->cargarComSubdiario(
                $tipoAp,
                $letraAp,
                $sucAp,
                $nroAp,
                $this->erroresBridge,
            );
        }

        return $this->comSubdiarioCache[$clave];
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

    private function cuentaVisibleDesdeDocumentoGasto(object $lineaGasto, string $tipoAp, bool $anticipo114040 = false): int
    {
        $cuenta = (int) ($lineaGasto->subd_cuenta ?? 0);
        if ($cuenta <= 0) {
            return 0;
        }

        if ($anticipo114040 && $cuenta >= 114010000 && $cuenta < 114020000) {
            return 114040001;
        }

        if (strtoupper($tipoAp) === 'FIS' && $cuenta >= 114010000 && $cuenta < 114020000) {
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
     * Reimputa 114010/521130 al mayor gasto de la misma operación (l-mayorconc.c reimputa_cuentas).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function reimputarCuentasAnticipoCompras(array $lineas): array
    {
        $cuentasReimputa = MayorConceptoMemoriaMotor::CUENTAS_REIMPUTA_CONCEPTO;
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
            $indiceDestino = null;
            $maxMonto = 0.0;

            foreach ($indices as $indice) {
                $cuenta = (int) ($lineas[$indice]['cuenta'] ?? 0);
                $monto = max((float) ($lineas[$indice]['debe'] ?? 0), (float) ($lineas[$indice]['haber'] ?? 0));
                if (in_array($cuenta, $cuentasReimputa, true)) {
                    continue;
                }

                if ($monto > $maxMonto) {
                    $maxMonto = $monto;
                    $indiceDestino = $indice;
                }
            }

            if ($indiceDestino === null) {
                continue;
            }

            $cuentaDestino = (int) ($lineas[$indiceDestino]['cuenta'] ?? 0);
            $conceptoDestino = (int) ($lineas[$indiceDestino]['concepto_id'] ?? 0);
            $nombreDestino = (string) ($lineas[$indiceDestino]['concepto_nombre'] ?? '');

            foreach ($indices as $indice) {
                $cuenta = (int) ($lineas[$indice]['cuenta'] ?? 0);
                if (! in_array($cuenta, $cuentasReimputa, true)) {
                    continue;
                }

                $lineas[$indice]['cuenta'] = $cuentaDestino;
                $lineas[$indice]['cuenta_codigo'] = $this->motor->formatearCodigoCuenta($cuentaDestino);
                $lineas[$indice]['concepto_id'] = $conceptoDestino;
                $lineas[$indice]['concepto_nombre'] = $nombreDestino;
            }
        }

        return $lineas;
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
            'cheque' => trim((string) ($meta['cheque'] ?? '')),
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

    private function claveOperacionPago(string $tipo, int $nro, int $fecha): string
    {
        return strtoupper(trim($tipo)).'|'.$nro.'|'.$fecha;
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
        if (! isset($this->comSubdiarioCache[$claveCom])) {
            [$ct, $cl, $cs, $cn] = explode('|', $claveCom, 4);
            $this->consultasBridgeIndividuales++;
            $this->comSubdiarioCache[$claveCom] = $this->reader->cargarComSubdiario(
                $ct, $cl, (int) $cs, (int) $cn, $this->erroresBridge,
            );
        }
            $comLineas = array_merge($comLineas, $this->comSubdiarioCache[$claveCom]);
        }

        return $comLineas;
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
                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);
                $orden = (int) ($apl->aplp_orden ?? 0);

                if ($refTipo === 'COM') {
                    $claveCom = $refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro;
                    $clavesCom[$claveCom] = $claveCom;
                    $this->ordenesComPorFactura[$claveFac][$claveCom] = $orden > 0 ? $orden : $refNro;
                    continue;
                }

                if ($refTipo !== '' && $refNro > 0) {
                    $pendientes[] = [$refTipo, $refLetra, $refSuc, $refNro];
                    if ($orden <= 0 && in_array($refTipo, ['PEP', 'COM'], true)) {
                        $this->ordenesComPorFactura[$claveFac]['orden|'.$refTipo.'|'.$refNro] = $refNro;
                    }
                }
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
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;

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
            $orden = (int) ($apl->aplp_orden ?? 0);
            if ($orden > 0) {
                return $orden;
            }

            $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            if (in_array($refTipo, ['PEP', 'COM'], true) && $refNro > 0) {
                return $refNro;
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

        return [
            'cuenta_disponibilidad' => $cuentaDisponibilidad,
            'cheque' => $this->numeroChequeDesdeAplicacion($aplicacion),
            'nro_oc' => $nroOc,
            'emisor' => $prom ? $this->truncarTexto(trim((string) ($prom->prom_nombre ?? '')), 15) : '',
            'cuit' => $prom ? trim((string) ($prom->prom_cuit ?? '')) : '',
        ];
    }

    private function numeroChequeDesdeAplicacion(object $aplicacion): string
    {
        $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
        if ($tipoAp === 'CHP') {
            $nro = trim((string) ($aplicacion->axp_nro ?? ''));
            if ($nro !== '' && $nro !== '0') {
                return $nro;
            }
        }

        return trim((string) ($aplicacion->axp_banco ?? ''));
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

        foreach ($ctamovLista as $lineaCtamov) {
            $adaptada = $this->ctamovComoSubdiario($lineaCtamov);
            if (! $this->lineaVisible($adaptada, $monedaConverter, $monedaReporteId, $soloMonedaOrigen)) {
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

            $importe = $monedaConverter->convertirImporte(
                (float) ($linea->subd_importe ?? 0),
                (string) ($linea->subd_cod_mon ?? '1'),
                (float) ($linea->subd_cotizacion ?? 0),
                (int) ($linea->subd_fecha ?? 0),
                $monedaReporteId,
            );

            $cuenta = (int) ($linea->subd_cuenta ?? 0);
            $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
            if ($this->motor->esDisponibilidadPlano($cuenta)) {
                $this->acumularMovimientoPlanoDisponibilidad($porCuenta, $cuenta, $mov, $importe);
            }

            $contrapartida = (int) ($linea->subd_contrapartida ?? 0);
            if ($contrapartida > 0 && $this->motor->esDisponibilidadPlano($contrapartida)) {
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
     * @param  array<int, array<string, mixed>>  $porCuenta
     */
    private function acumularMovimientoPlanoDisponibilidad(array &$porCuenta, int $cuenta, string $mov, float $importe): void
    {
        if ($cuenta <= 0 || $importe <= 0 || ! $this->motor->esDisponibilidadPlano($cuenta)) {
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
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        if ($this->mediopagoSupport->esAuxpagIgnorado($t)
            || $this->mediopagoSupport->esMedioPagoAuxpag($t)) {
            return false;
        }

        return in_array($t, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true);
    }

    private function esRetencion(object $fila): bool
    {
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        return in_array($t, ['RTP', 'RGP', 'RSP', 'RIV', 'RGU', 'RLP', 'RSU'], true);
    }
}
