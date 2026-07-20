<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use Illuminate\Database\Eloquent\Collection;

/**
 * Total facturado Anita en jornada (fechajornada), alineado a Facturas del día / cierre de turno.
 *
 * - Neto: SUM(venta.total) con NC incluidas una sola vez (signo negativo en ERP).
 * - Cuadro fila 1: cobranzas ERP de toda facturación del día excepto TOTEM (incl. terminales sin Waitry y NC).
 * - Las facturas CF del proceso de cierre Waitry (identificador_pc CIERRE-JORNADA-WAITRY) no entran
 *   en el cuadro «Anita jornada» ni en el asiento 2: van en el asiento 1 del proceso.
 * - `anita_sin_waitry` / `total_sin_waitry`: solo informativo arriba (subset Sport Bar, etc.).
 */
final class CierreJornadaFacturadoAnitaSupport
{
    private const TASA_IVA_DEFAULT = 21.0;
    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array{
     *   anita_jornada: array<string, mixed>,
     *   anita_sin_waitry: array<string, mixed>,
     *   anita_totem: array<string, mixed>,
     *   qr: float,
     *   mp: float,
     *   efectivo: float,
     *   otros: float,
     *   total: float,
     *   total_facturas: float,
     *   total_notas_credito: float,
     *   cantidad_facturas: int,
     *   cantidad_notas_credito: int,
     *   cantidad_facturas_totem: int,
     *   cantidad_facturas_sin_waitry: int,
     *   total_sin_waitry: float,
     *   etiqueta: string,
     *   tipo: string
     * }
     */
    public static function totalesDesdeEmisiones(Collection $emisiones, int $empresaId): array
    {
        $filaJornada = CierreJornadaProcesoGrillaSupport::filaVacia(
            'Facturado Anita (jornada — cobranzas ERP, cuadra con asiento 2)',
            'anita_jornada',
        );
        $filaSinWaitry = CierreJornadaProcesoGrillaSupport::filaVacia(
            'Facturado Anita — terminales sin Waitry (referencia)',
            'anita_sin_waitry',
        );
        $filaTotem = CierreJornadaProcesoGrillaSupport::filaVacia(
            'Facturado Anita — cobro TOTEM (medio real Waitry)',
            'anita_totem',
        );
        $totalNeto = 0.0;
        $totalFacturas = 0.0;
        $totalNotasCredito = 0.0;
        $cantidadFacturas = 0;
        $cantidadNotasCredito = 0;
        $cantidadFacturasTotem = 0;
        $cantidadFacturasSinWaitry = 0;
        $totalSinWaitry = 0.;

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            if (self::esEmisionFacturaProcesoCierreJornada($emision)) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $esNotaCredito = ($emision->venta_factura_origen_id ?? null) !== null;
            $totalNeto = round($totalNeto + $monto, 2);
            $esTotem = self::esFacturaCobroTotem($emision, $empresaId, $totemId);
            $esSinWaitry = self::esEmisionTerminalSinWaitry($emision);

            if ($esNotaCredito) {
                $totalNotasCredito = round($totalNotasCredito + $monto, 2);
                $cantidadNotasCredito++;
                if ($esTotem) {
                    self::acumularEmisionEnFilaCuadro($filaTotem, $emision, $empresaId);
                } else {
                    if ($esSinWaitry) {
                        self::acumularEmisionEnFilaCuadro($filaSinWaitry, $emision, $empresaId);
                    }
                    self::acumularEmisionEnFilaCuadro($filaJornada, $emision, $empresaId);
                }

                continue;
            }

            $totalFacturas = round($totalFacturas + $monto, 2);
            $cantidadFacturas++;

            if ($esTotem) {
                $cantidadFacturasTotem++;
                self::acumularEmisionEnFilaCuadro($filaTotem, $emision, $empresaId);
            } else {
                if ($esSinWaitry) {
                    $cantidadFacturasSinWaitry++;
                    $totalSinWaitry = round($totalSinWaitry + $monto, 2);
                    self::acumularEmisionEnFilaCuadro($filaSinWaitry, $emision, $empresaId);
                }
                self::acumularEmisionEnFilaCuadro($filaJornada, $emision, $empresaId);
            }
        }

        $filaJornada = self::cerrarFilaFacturado($filaJornada);
        $filaSinWaitry = self::cerrarFilaFacturado($filaSinWaitry);
        $filaTotem = self::cerrarFilaFacturado($filaTotem);

        return [
            'anita_jornada' => $filaJornada,
            'anita_sin_waitry' => $filaSinWaitry,
            'anita_totem' => $filaTotem,
            'qr' => round((float) $filaJornada['qr'], 2),
            'mp' => round((float) $filaJornada['mp'], 2),
            'efectivo' => round((float) $filaJornada['efectivo'], 2),
            'otros' => round((float) $filaJornada['otros'], 2),
            'total' => $totalNeto,
            'total_facturas' => $totalFacturas,
            'total_notas_credito' => $totalNotasCredito,
            'cantidad_facturas' => $cantidadFacturas,
            'cantidad_notas_credito' => $cantidadNotasCredito,
            'cantidad_facturas_totem' => $cantidadFacturasTotem,
            'cantidad_facturas_sin_waitry' => $cantidadFacturasSinWaitry,
            'total_sin_waitry' => round($totalSinWaitry, 2),
            'etiqueta' => $filaJornada['etiqueta'],
            'tipo' => $filaJornada['tipo'],
        ];
    }

    /**
     * Terminal configurada con integración Waitry deshabilitada (config PV gastronomía).
     */
    public static function esEmisionTerminalSinWaitry(VentaGastronomiaEmision $emision): bool
    {
        $emision->loadMissing('configuracionPuntoventa');
        $cfg = $emision->configuracionPuntoventa;
        if ($cfg === null) {
            return false;
        }

        return ! (bool) ($cfg->waitry_habilitado ?? true);
    }

    /**
     * @return array{
     *   anita_jornada: array<string, mixed>,
     *   anita_totem: array<string, mixed>,
     *   qr: float,
     *   mp: float,
     *   efectivo: float,
     *   otros: float,
     *   total: float,
     *   total_facturas: float,
     *   total_notas_credito: float,
     *   cantidad_facturas: int,
     *   cantidad_notas_credito: int,
     *   cantidad_facturas_totem: int,
     *   etiqueta: string,
     *   tipo: string
     * }
     */
    public static function totalesJornadaEmpresa(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return self::totalesDesdeEmisiones(new Collection, $empresaId);
        }

        $emisiones = self::emisionesJornadaEmpresa($empresaId, $fechaJornada);

        return self::totalesDesdeEmisiones($emisiones, $empresaId);
    }

    /**
     * @return Collection<int, VentaGastronomiaEmision>
     */
    public static function emisionesJornadaEmpresa(int $empresaId, string $fechaJornada): Collection
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return new Collection;
        }

        return VentaGastronomiaEmision::query()
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta',
                'configuracionPuntoventa',
            ])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId))
                    ->whereDoesntHave('estacionamientoEmision');
            })
            ->get();
    }

    /**
     * Emisiones de terminales sin integración Waitry (entran en asiento 1 / factura proceso).
     *
     * @return Collection<int, VentaGastronomiaEmision>
     */
    public static function emisionesSinWaitryJornadaEmpresa(int $empresaId, string $fechaJornada): Collection
    {
        return self::emisionesJornadaEmpresa($empresaId, $fechaJornada)
            ->filter(fn (VentaGastronomiaEmision $e) => self::esEmisionTerminalSinWaitry($e))
            ->values();
    }

    public static function totalNetoJornadaEmpresa(int $empresaId, string $fechaJornada): float
    {
        return self::totalesJornadaEmpresa($empresaId, $fechaJornada)['total'];
    }

    /**
     * Facturas Anita de la jornada con cobranza en efectivo (cuenta real, sin TOTEM),
     * para compensar en redistribución Waitry→efectivo (Anita efectivo→QR).
     *
     * @return list<array<string, mixed>>
     */
    public static function movimientosEfectivoParaCompensacion(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return [];
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        $out = [];

        foreach ($emisiones as $emision) {
            if (($emision->venta_factura_origen_id ?? null) !== null) {
                continue;
            }
            if (self::esFacturaCobroTotem($emision, $empresaId, $totemId)) {
                continue;
            }
            if (self::esEmisionFacturaProcesoCierreJornada($emision)) {
                continue;
            }

            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            $totalEfectivo = self::totalCobranzaEfectivoEmision($emision, $empresaId);
            if ($totalEfectivo <= 0.0001) {
                continue;
            }

            $waitryId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            $medio = self::primerMedioCobranza($emision, $empresaId);

            $out[] = [
                'waitry_order_id' => $waitryId > 0 ? $waitryId : null,
                'venta_id' => (int) $venta->id,
                'venta_codigo' => (string) ($venta->codigo ?? ''),
                'total' => $totalEfectivo,
                'impuesto_interno' => self::sumarImpuestoInternoVenta($venta),
                'facturada_erp' => true,
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
                'medio_anita_clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'anita_cuentacaja_id' => $efectivoId ?? (int) ($medio['cuentacaja_id'] ?? 0),
                'anita_cuentacaja_label' => $medio['label'] ?? '',
                'anita_es_totem' => false,
                'anita_compensacion_redistribucion' => true,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) ($a['venta_id'] ?? 0)) <=> ((int) ($b['venta_id'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['waitry_order_id'] ?? 0)) <=> ((int) ($b['waitry_order_id'] ?? 0));
        });

        return $out;
    }

    private static function totalCobranzaEfectivoEmision(VentaGastronomiaEmision $emision, int $empresaId): float
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return 0.;
        }

        $montoFactura = round((float) ($venta->total ?? 0), 2);
        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $totalEfectivo = 0.;
        $sumCobranza = 0.;

        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                $montoMedio = round((float) ($medio->monto ?? 0), 2);
                if ($ccId <= 0 || abs($montoMedio) <= 0.0001) {
                    continue;
                }
                $sumCobranza = round($sumCobranza + $montoMedio, 2);
                $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                    ['id' => $ccId, 'codigo' => (string) ($medio->codigo ?? '')],
                    $empresaId,
                );
                if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                    $totalEfectivo = round($totalEfectivo + $montoMedio, 2);
                }
            }
        }

        if ($totalEfectivo > 0.0001) {
            return $totalEfectivo;
        }

        if (self::esInvitacionSinCobranza($montoFactura, $sumCobranza)) {
            return 0.;
        }

        $primerMedio = self::primerMedioCobranza($emision, $empresaId);
        if ($primerMedio === null) {
            return 0.;
        }

        $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
            ['id' => (int) $primerMedio['cuentacaja_id']],
            $empresaId,
        );
        if ($clave !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
            return 0.;
        }

        return round(abs($montoFactura), 2);
    }

    /**
     * Datos para asiento 2: toda la facturación Anita de la jornada excl. cobro TOTEM.
     * Debe por cuentacaja real de cada cobranza; haber ventas / IVA (IVA cigarrillos aparte).
     *
     * @return array{
     *   total: float,
     *   cantidad_emisiones: int,
     *   cantidad_notas_credito: int,
     *   impuesto_interno_total: float,
     *   facturas_con_impuesto_interno: int,
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float,
     *   debe_por_cuenta: array<int, array{concepto:string,cuenta_id:int,debe:float}>,
     *   advertencias: list<string>
     * }
     */
    public static function datosAsientoVentasJornadaExclTotem(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return self::datosAsientoVacios();
        }

        $emisiones = self::emisionesJornadaEmpresa($empresaId, $fechaJornada)
            ->loadMissing(['venta.venta_impuestos']);

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $emisionesAsiento2 = $emisiones->filter(function (VentaGastronomiaEmision $e) use ($empresaId, $totemId) {
            return ! self::esFacturaCobroTotem($e, $empresaId, $totemId);
        })->values();

        return self::datosAsientoDesdeEmisiones($emisionesAsiento2, $empresaId);
    }

    /**
     * Datos contables de facturas emitidas en terminales sin Waitry (grilla grupo 1; incluidas en asiento 2).
     *
     * @return array<string, mixed>
     */
    public static function datosAsientoVentasJornadaSoloTotem(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return self::datosAsientoVacios();
        }

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $emisiones = self::emisionesJornadaEmpresa($empresaId, $fechaJornada)
            ->filter(fn (VentaGastronomiaEmision $e) => self::esFacturaCobroTotem($e, $empresaId, $totemId))
            ->values();

        return self::datosAsientoDesdeEmisiones($emisiones, $empresaId, false);
    }

    public static function datosAsientoFacturaProcesoSinWaitry(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0 || $fechaJornada === '') {
            return self::datosAsientoVacios();
        }

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $emisiones = self::emisionesSinWaitryJornadaEmpresa($empresaId, $fechaJornada)
            ->loadMissing(['venta.venta_impuestos'])
            ->filter(fn (VentaGastronomiaEmision $e) => ! self::esFacturaCobroTotem($e, $empresaId, $totemId))
            ->values();

        return self::datosAsientoDesdeEmisiones($emisiones, $empresaId);
    }

    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array{
     *   total: float,
     *   cantidad_emisiones: int,
     *   cantidad_notas_credito: int,
     *   impuesto_interno_total: float,
     *   facturas_con_impuesto_interno: int,
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float,
     *   debe_por_cuenta: array<int, array{concepto:string,cuenta_id:int,debe:float}>,
     *   advertencias: list<string>
     * }
     */
    public static function datosAsientoDesdeEmisiones(
        Collection $emisiones,
        int $empresaId,
        bool $omitirFacturasTotem = true,
    ): array {
        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        /** @var array<int, array{concepto:string,cuenta_id:int,debe:float}> */
        $debePorCuenta = [];
        $ventasGravadas = 0.;
        $ventasKiosco = 0.;
        $ivaNormal = 0.;
        $ivaCigarrillos = 0.;
        $impuestoInternoTotal = 0.;
        $conImpuestoInterno = 0;
        $cantidadEmisiones = 0;
        $cantidadNotasCredito = 0;
        $totalFacturado = 0.;
        $cantidadInvitaciones = 0;
        $debeDiferenciaCaja = 0.;
        $advertencias = [];

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            if (self::esEmisionFacturaProcesoCierreJornada($emision)) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            if ($omitirFacturasTotem && self::esFacturaCobroTotem($emision, $empresaId, $totemId)) {
                continue;
            }

            $esNotaCredito = ($emision->venta_factura_origen_id ?? null) !== null;
            $cantidadEmisiones++;
            if ($esNotaCredito) {
                $cantidadNotasCredito++;
            }

            $totalFacturado = round($totalFacturado + $monto, 2);
            $importeCigarrillos = CierreJornadaVentasCigarrillosSupport::importeLineasMenuCigarrillos($venta, $empresaId);
            $impuestoInterno = CierreJornadaVentasCigarrillosSupport::resolverImpuestoInternoVenta(
                $venta,
                $empresaId,
                $importeCigarrillos,
            );
            $exento = CierreJornadaVentasCigarrillosSupport::resolverExentoVenta($venta);
            $desglose = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(
                $monto,
                $impuestoInterno,
                $importeCigarrillos,
                $exento,
            );

            if (abs($impuestoInterno) > 0.0001) {
                $ventasKiosco = round($ventasKiosco + $desglose['ventas_kiosco'], 2);
                $ventasGravadas = round($ventasGravadas + $desglose['ventas_gravadas'], 2);
                $ivaCigarrillos = round($ivaCigarrillos + $desglose['iva_cigarrillos'], 2);
                $ivaNormal = round($ivaNormal + $desglose['iva_normal'], 2);
                $impuestoInternoTotal = round($impuestoInternoTotal + $impuestoInterno, 2);
                if (! $esNotaCredito) {
                    $conImpuestoInterno++;
                }
            } else {
                $ventasGravadas = round($ventasGravadas + $desglose['ventas_gravadas'], 2);
                $ivaNormal = round($ivaNormal + $desglose['iva_normal'], 2);
            }

            $sumCobranza = 0.;
            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            foreach ($medios as $lineas) {
                foreach ($lineas as $medio) {
                    $ccId = (int) ($medio->cuentacaja_id ?? 0);
                    $montoMedio = round((float) ($medio->monto ?? 0), 2);
                    if ($ccId <= 0 || abs($montoMedio) <= 0.0001) {
                        continue;
                    }
                    $sumCobranza = round($sumCobranza + $montoMedio, 2);
                    $codigo = trim((string) ($medio->codigo ?? ''));
                    $nombre = trim((string) ($medio->nombre ?? ''));
                    $label = $codigo !== '' && $nombre !== ''
                        ? $codigo.' — '.$nombre
                        : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));
                    if (! isset($debePorCuenta[$ccId])) {
                        $debePorCuenta[$ccId] = [
                            'concepto' => 'Medio de cobro — '.$label,
                            'cuenta_id' => $ccId,
                            'debe' => 0.,
                        ];
                    }
                    $debePorCuenta[$ccId]['debe'] = round($debePorCuenta[$ccId]['debe'] + $montoMedio, 2);
                }
            }

            if (self::esInvitacionSinCobranza($monto, $sumCobranza)) {
                $debeDiferenciaCaja = round($debeDiferenciaCaja + $monto, 2);
                $cantidadInvitaciones++;

                continue;
            }

            if (abs($sumCobranza) <= 0.0001 && abs($monto) > 0.0001) {
                $medio = self::primerMedioCobranza($emision, $empresaId);
                if ($medio !== null) {
                    $ccId = (int) $medio['cuentacaja_id'];
                    if (! isset($debePorCuenta[$ccId])) {
                        $debePorCuenta[$ccId] = [
                            'concepto' => 'Medio de cobro — '.$medio['label'],
                            'cuenta_id' => $ccId,
                            'debe' => 0.,
                        ];
                    }
                    $debePorCuenta[$ccId]['debe'] = round($debePorCuenta[$ccId]['debe'] + $monto, 2);
                    $advertencias[] = 'Venta #'.(int) $venta->id.': sin movimiento de caja en cobranza; se imputó el total al medio detectado.';
                } else {
                    $advertencias[] = 'Venta #'.(int) $venta->id.': sin cobranza ni cuenta caja; revise antes de grabar el asiento.';
                }
            } elseif (abs(round($sumCobranza - $monto, 2)) > 0.02) {
                $advertencias[] = 'Venta #'.(int) $venta->id.': cobranzas ('.round($sumCobranza, 2).') difieren del total factura ('.$monto.').';
            }
        }

        return [
            'total' => round($totalFacturado, 2),
            'cantidad_emisiones' => $cantidadEmisiones,
            'cantidad_notas_credito' => $cantidadNotasCredito,
            'impuesto_interno_total' => round($impuestoInternoTotal, 2),
            'facturas_con_impuesto_interno' => $conImpuestoInterno,
            'ventas_gravadas' => round($ventasGravadas, 2),
            'ventas_kiosco' => round($ventasKiosco, 2),
            'iva_normal' => round($ivaNormal, 2),
            'iva_cigarrillos' => round($ivaCigarrillos, 2),
            'debe_por_cuenta' => $debePorCuenta,
            'debe_diferencia_caja' => round($debeDiferenciaCaja, 2),
            'cantidad_invitaciones' => $cantidadInvitaciones,
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    public static function esFacturaCobroTotemPublico(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
    ): bool {
        return self::esFacturaCobroTotem($emision, $empresaId, $totemId);
    }

    /**
     * Facturas CF emitidas por el proceso de cierre Waitry (asiento 1); no van al asiento 2 / cuadro Anita jornada.
     */
    public static function esEmisionFacturaProcesoCierreJornada(VentaGastronomiaEmision $emision): bool
    {
        if ((int) ($emision->cierre_jornada_proceso_lote ?? 0) > 0) {
            return true;
        }

        return (string) ($emision->identificador_pc ?? '') === GastronomiaVentaWaitryComandasSupport::IDENTIFICADOR_PC_CIERRE_JORNADA;
    }

    public static function columnaMedioParaEmisionPublico(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
        bool $esTotem,
    ): string {
        return self::columnaMedioParaFactura($emision, $empresaId, $totemId, $esTotem);
    }

    private static function esFacturaCobroTotem(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
    ): bool {
        $medio = self::primerMedioCobranza($emision, $empresaId);
        if ($medio !== null) {
            return $totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId;
        }

        return (bool) ($emision->cuenta?->waitry_cobro_totem ?? false);
    }

    private static function columnaMedioParaFactura(
        VentaGastronomiaEmision $emision,
        int $empresaId,
        int $totemId,
        bool $esTotem,
    ): string {
        $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
        $medio = self::primerMedioCobranza($emision, $empresaId);

        if ($esTotem && $waitryTipo !== null && $waitryTipo !== '') {
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeWaitryTipo($waitryTipo);
        } elseif ($medio !== null) {
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                ['id' => (int) $medio['cuentacaja_id']],
                $empresaId,
            );
        } else {
            $clave = CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
        }

        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    /**
     * Reparte el importe de la factura en columnas del cuadro según cada línea de cobranza ERP
     * (misma lógica de clasificación que el debe del asiento 2).
     *
     * @param  array<string, mixed>  $fila
     */
    public static function acumularEmisionEnFilaCuadro(
        array &$fila,
        VentaGastronomiaEmision $emision,
        int $empresaId,
    ): void {
        $venta = $emision->venta;
        if ($venta === null) {
            return;
        }

        $montoFactura = round((float) ($venta->total ?? 0), 2);
        if (abs($montoFactura) <= 0.0001) {
            return;
        }

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $esTotem = self::esFacturaCobroTotem($emision, $empresaId, $totemId);

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $sumCobranza = 0.;
        $imputoFactura = false;

        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                $montoMedio = round((float) ($medio->monto ?? 0), 2);
                if ($ccId <= 0 || abs($montoMedio) <= 0.0001) {
                    continue;
                }
                $sumCobranza = round($sumCobranza + $montoMedio, 2);
                $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                    ['id' => $ccId, 'codigo' => (string) ($medio->codigo ?? '')],
                    $empresaId,
                );
                $col = self::columnaCuadroDesdeClaveMedio($clave);
                $fila[$col] = round((float) ($fila[$col] ?? 0) + $montoMedio, 2);
                if (! isset($fila['por_cuenta']) || ! is_array($fila['por_cuenta'])) {
                    $fila['por_cuenta'] = [];
                }
                $fila['por_cuenta'][$ccId] = round((float) ($fila['por_cuenta'][$ccId] ?? 0) + $montoMedio, 2);
                $imputoFactura = true;
            }
        }

        if (self::esInvitacionSinCobranza($montoFactura, $sumCobranza)) {
            $fila['diferencia_caja'] = round((float) ($fila['diferencia_caja'] ?? 0) + $montoFactura, 2);

            return;
        }

        if (! $imputoFactura && abs($montoFactura) > 0.0001) {
            $medio = self::primerMedioCobranza($emision, $empresaId);
            if ($medio !== null) {
                $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(
                    ['id' => (int) $medio['cuentacaja_id']],
                    $empresaId,
                );
                $col = self::columnaCuadroDesdeClaveMedio($clave);
            } else {
                $col = self::columnaMedioParaFactura($emision, $empresaId, $totemId, $esTotem);
            }
            $fila[$col] = round((float) ($fila[$col] ?? 0) + $montoFactura, 2);
            if ($col !== 'diferencia_caja' && $medio !== null) {
                $ccFallback = (int) ($medio['cuentacaja_id'] ?? 0);
                if ($ccFallback > 0) {
                    if (! isset($fila['por_cuenta']) || ! is_array($fila['por_cuenta'])) {
                        $fila['por_cuenta'] = [];
                    }
                    $fila['por_cuenta'][$ccFallback] = round(
                        (float) ($fila['por_cuenta'][$ccFallback] ?? 0) + $montoFactura,
                        2,
                    );
                }
            }
        }
    }

    public static function columnaCuadroDesdeClaveMedio(string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function cerrarFilaFacturado(array $fila): array
    {
        foreach (['qr', 'mp', 'efectivo', 'otros', 'diferencia_caja'] as $k) {
            $fila[$k] = round((float) ($fila[$k] ?? 0), 2);
        }
        $fila['total'] = round(
            $fila['qr'] + $fila['mp'] + $fila['efectivo'] + $fila['otros'] + $fila['diferencia_caja'],
            2,
        );

        return $fila;
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private static function primerMedioCobranza(VentaGastronomiaEmision $emision, int $empresaId): ?array
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));
                $label = $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

                return ['cuentacaja_id' => $ccId, 'label' => $label];
            }
        }

        return null;
    }

    /**
     * @return array{
     *   total: float,
     *   cantidad_emisiones: int,
     *   cantidad_notas_credito: int,
     *   impuesto_interno_total: float,
     *   facturas_con_impuesto_interno: int,
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float,
     *   debe_por_cuenta: array<int, array{concepto:string,cuenta_id:int,debe:float}>,
     *   debe_diferencia_caja: float,
     *   cantidad_invitaciones: int,
     *   advertencias: list<string>
     * }
     */
    private static function datosAsientoVacios(): array
    {
        return [
            'total' => 0.,
            'cantidad_emisiones' => 0,
            'cantidad_notas_credito' => 0,
            'impuesto_interno_total' => 0.,
            'facturas_con_impuesto_interno' => 0,
            'ventas_gravadas' => 0.,
            'ventas_kiosco' => 0.,
            'iva_normal' => 0.,
            'iva_cigarrillos' => 0.,
            'debe_por_cuenta' => [],
            'debe_diferencia_caja' => 0.,
            'cantidad_invitaciones' => 0,
            'advertencias' => [],
        ];
    }

    /**
     * Factura de cortesía / invitación ($0,01) sin cobranza en caja — no es error de cobranza.
     */
    public static function esInvitacionSinCobranzaPublico(float $montoVenta, float $montoCobrado): bool
    {
        return self::esInvitacionSinCobranza($montoVenta, $montoCobrado);
    }

    private static function esInvitacionSinCobranza(float $montoVenta, float $montoCobrado): bool
    {
        if (abs(abs($montoVenta) - GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA) >= 0.001) {
            return false;
        }

        return abs($montoCobrado) < 0.001;
    }

    private static function sumarImpuestoInternoVenta(Venta $venta): float
    {
        $venta->loadMissing('venta_impuestos');
        $total = 0.;
        foreach ($venta->venta_impuestos ?? [] as $vi) {
            $concepto = mb_strtolower((string) ($vi->concepto ?? ''));
            if (str_contains($concepto, 'intern')) {
                $total += (float) ($vi->importe ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @return array{gravado:float,iva:float,neto_venta:float}
     */
    private static function desglosarBaseIvaConSigno(float $total, float $impuestoInterno): array
    {
        $sign = $total >= 0 ? 1. : -1.;
        $absTotal = abs($total);
        $absImpuestoInterno = abs($impuestoInterno);
        $netoVentas = round(max(0., $absTotal - $absImpuestoInterno), 2);
        $gravado = round($netoVentas / (1. + self::TASA_IVA_DEFAULT / 100.), 2);
        $iva = round($netoVentas - $gravado, 2);

        return [
            'gravado' => round($sign * $gravado, 2),
            'iva' => round($sign * $iva, 2),
            'neto_venta' => round($sign * $netoVentas, 2),
        ];
    }
}
