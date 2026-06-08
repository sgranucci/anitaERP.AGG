<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

/**
 * Preview de asientos contables del cierre de jornada (sin persistir).
 */
final class CierreJornadaProcesoAsientosPreviewSupport
{
    private const TASA_IVA_DEFAULT = 21.0;

    public const COMANDAS_ALCANCE_FACTURA_PROCESO = 'factura_proceso';

    public const COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO = 'efectivo_no_facturado';

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array{
     *   asientos: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float
     * }
     */
    public static function generar(array $movimientos, int $empresaId, array $configContable): array
    {
        $advertencias = [];
        $faltantes = CierreJornadaProcesoConfigSupport::faltantes($configContable, $empresaId);
        if ($faltantes !== []) {
            $advertencias[] = 'Faltan datos de configuración: '.implode(', ', $faltantes).'.';
        }

        $cuentaVentas = (int) ($configContable['cuenta_ventas_id'] ?? 0);
        $cuentaIva = (int) ($configContable['cuenta_iva_id'] ?? 0);
        $cuentaVentasKiosco = (int) ($configContable['cuenta_ventas_kiosco_id'] ?? 0);
        $cuentaFondoFijo = (int) ($configContable['cuenta_fondo_fijo_maquinas_id'] ?? 0);

        $asientos = [];
        $n = 0;

        foreach ($movimientos as $mov) {
            $grupo = (string) ($mov['grupo'] ?? '');
            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA) {
                continue;
            }
            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR) {
                continue;
            }

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            $impuestoInterno = round((float) ($mov['impuesto_interno'] ?? 0), 2);
            $base = self::desglosarBaseIva($total, $impuestoInterno);

            if ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL) {
                $medios = self::mediosPlanificadosFacturado($mov, $empresaId);
                $n++;
                $asientos[] = self::asientoFacturadoMedioReal(
                    $n,
                    $mov,
                    $medios,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaVentasKiosco,
                    $impuestoInterno,
                );
            } elseif ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM) {
                $n++;
                $asientos[] = self::asientoTotemPrincipal(
                    $n,
                    $mov,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaVentasKiosco,
                    $impuestoInterno,
                    $empresaId,
                );
                $n++;
                $asientos[] = self::asientoTotemPuente(
                    $n,
                    $mov,
                    $total,
                    $empresaId,
                );
            } elseif ($grupo === CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
                if (! self::debeFacturarSinFacturar($mov)) {
                    continue;
                }
                $medios = self::mediosPlanificadosSinFacturar($mov, $empresaId);
                $n++;
                $asientos[] = self::asientoPendienteFacturacion(
                    $n,
                    $mov,
                    $medios,
                    $base,
                    $cuentaVentas,
                    $cuentaIva,
                    $cuentaVentasKiosco,
                    $impuestoInterno,
                    $cuentaFondoFijo,
                );
            }
        }

        $debe = 0.;
        $haber = 0.;
        foreach ($asientos as $a) {
            foreach ($a['lineas'] ?? [] as $ln) {
                $debe += (float) ($ln['debe'] ?? 0);
                $haber += (float) ($ln['haber'] ?? 0);
            }
        }

        return [
            'asientos' => $asientos,
            'advertencias' => $advertencias,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
        ];
    }

    /**
     * Movimientos Waitry que entrarían en la única factura del proceso (QR / Mercado Pago tras redistribución).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosFacturaProceso(array $movimientos): array
    {
        return CierreJornadaProcesoFacturaComandasSupport::movimientosFacturacion($movimientos);
    }

    /**
     * Comandas Waitry 100 % efectivo en el plan (ajuste insumos / compensación contable).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosCompensacionEfectivoNoFacturado(array $movimientos): array
    {
        return CierreJornadaProcesoFacturaComandasSupport::movimientosAjusteInsumos($movimientos);
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function montoEfectivoNoFacturadoDesdeMov(array $mov): float
    {
        $grupo = (string) ($mov['grupo'] ?? '');
        if (! in_array($grupo, [
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM,
        ], true)) {
            return 0.;
        }

        $plan = $mov['medios_pago_planificados'] ?? null;
        if (! is_array($plan)) {
            return 0.;
        }

        $sum = 0.;
        foreach ($plan as $parte) {
            if ((string) ($parte['clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                continue;
            }
            $sum = round($sum + (float) ($parte['monto'] ?? 0), 2);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function movimientosComandasPorAlcance(array $movimientos, string $alcance): array
    {
        return match ($alcance) {
            self::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO => self::movimientosCompensacionEfectivoNoFacturado($movimientos),
            default => self::movimientosFacturaProceso($movimientos),
        };
    }

    /**
     * Importe de comanda que entra en la factura del proceso (total completo si va a facturación).
     *
     * @param  array<string, mixed>  $mov
     */
    public static function montoQrFacturaProcesoDesdeMov(array $mov): float
    {
        if (! CierreJornadaProcesoFacturaComandasSupport::comandaVaAFacturacion($mov)) {
            return 0.;
        }

        return CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($mov);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function totalQrFacturaProceso(array $movimientos): float
    {
        return round(array_sum(array_map(
            static fn (array $m) => self::montoQrFacturaProcesoDesdeMov($m),
            self::movimientosFacturaProceso($movimientos),
        )), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    public static function mediosCobroConsolidadosFacturaProceso(array $movimientos, int $empresaId): array
    {
        return self::mediosCobroConsolidadosComandas(
            self::movimientosFacturaProceso($movimientos),
            $empresaId,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    public static function mediosCobroConsolidadosComandas(array $comandas, int $empresaId): array
    {
        /** @var array<int, float> $porCuenta */
        $porCuenta = [];

        foreach ($comandas as $mov) {
            foreach (self::mediosPlanificadosComandaCompleta($mov, $empresaId) as $medio) {
                $cuentaId = (int) ($medio['cuentacaja_id'] ?? 0);
                $monto = round((float) ($medio['monto'] ?? 0), 2);
                if ($cuentaId <= 0 || $monto <= 0.0001) {
                    continue;
                }
                $porCuenta[$cuentaId] = round(($porCuenta[$cuentaId] ?? 0.) + $monto, 2);
            }
        }

        $medios = [];
        foreach ($porCuenta as $cuentaId => $monto) {
            $medios[] = [
                'cuentacaja_id' => (int) $cuentaId,
                'moneda_id' => 1,
                'monto' => (float) $monto,
                'cotizacion' => 1.,
                'observacion' => 'Cierre Waitry — factura proceso',
            ];
        }

        return self::ajustarMontosMediosCobroAlTotal(
            $medios,
            CierreJornadaProcesoFacturaComandasSupport::totalComandas($comandas),
        );
    }

    /**
     * Medios de cobranza por comanda facturada: todo el plan post-redistribución (QR, MP, efectivo)
     * excepto TOTEM (puente en otros asientos).
     *
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    public static function mediosPlanificadosCobranzaFacturaProceso(array $mov, int $empresaId): array
    {
        return self::mediosPlanificadosComandaCompleta($mov, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    public static function mediosPlanificadosComandaCompleta(array $mov, int $empresaId): array
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            $filtrados = array_values(array_filter(
                $plan,
                static fn (array $p) => (string) ($p['clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_TOTEM
                    && (float) ($p['monto'] ?? 0) > 0.0001,
            ));
            if ($filtrados !== []) {
                return self::resolverMediosDesdePlan($filtrados, $empresaId);
            }

            return [];
        }

        $total = round((float) ($mov['total'] ?? 0), 2);
        $claveDefault = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_QR);

        return self::unMedio($claveDefault, $total, $empresaId);
    }

    /**
     * Ajusta el último renglón de cobranza para cuadrar con el total facturable (centavos).
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>  $medios
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion:float,observacion:string}>
     */
    public static function ajustarMontosMediosCobroAlTotal(array $medios, float $totalEsperado): array
    {
        if ($medios === [] || $totalEsperado <= 0.0001) {
            return $medios;
        }

        $suma = round(array_sum(array_map(static fn (array $m) => (float) ($m['monto'] ?? 0), $medios)), 2);
        if (abs($suma - $totalEsperado) <= 0.02) {
            return $medios;
        }

        $delta = round($totalEsperado - $suma, 2);
        $ultimo = count($medios) - 1;
        $medios[$ultimo]['monto'] = round((float) $medios[$ultimo]['monto'] + $delta, 2);

        return $medios;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    public static function mediosPlanificadosSinFacturarPublico(array $mov, int $empresaId): array
    {
        return self::mediosPlanificadosSinFacturar($mov, $empresaId);
    }

    /**
     * Preview consolidado de la factura única del proceso (asiento + totales, sin persistir).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array{
     *   cantidad_comandas:int,
     *   total:float,
     *   asiento:?array<string,mixed>,
     *   resumen_debe:float,
     *   resumen_haber:float,
     *   advertencias:list<string>
     * }
     */
    public static function generarPreviewFacturaProceso(
        array $movimientos,
        int $empresaId,
        array $configContable,
    ): array {
        $advertencias = [];
        $faltantes = CierreJornadaProcesoConfigSupport::faltantes($configContable, $empresaId);
        if ($faltantes !== []) {
            $advertencias[] = 'Faltan datos de configuración: '.implode(', ', $faltantes).'.';
        }

        $incluidos = self::movimientosFacturaProceso($movimientos);
        if ($incluidos === []) {
            return [
                'cantidad_comandas' => 0,
                'total' => 0.,
                'asiento' => null,
                'resumen_debe' => 0.,
                'resumen_haber' => 0.,
                'advertencias' => array_merge($advertencias, [
                    'No hay comandas Waitry pendientes de facturación con medio QR o Mercado Pago tras aplicar el porcentaje.',
                ]),
            ];
        }

        $cuentaVentas = (int) ($configContable['cuenta_ventas_id'] ?? 0);
        $cuentaIva = (int) ($configContable['cuenta_iva_id'] ?? 0);
        $cuentaVentasKiosco = (int) ($configContable['cuenta_ventas_kiosco_id'] ?? 0);

        $totalFactura = 0.;
        $impuestoInternoTotal = 0.;
        /** @var array<int, array{concepto:string,cuenta_id:int,debe:float}> */
        $debePorCuenta = [];

        foreach ($incluidos as $mov) {
            $totalComanda = self::montoQrFacturaProcesoDesdeMov($mov);
            if ($totalComanda <= 0.0001) {
                continue;
            }
            $totalFactura += $totalComanda;
            $impuestoInternoTotal += round((float) ($mov['impuesto_interno'] ?? 0), 2);

            foreach (self::mediosPlanificadosCobranzaFacturaProceso($mov, $empresaId) as $medio) {
                $cuentaId = (int) ($medio['cuentacaja_id'] ?? 0);
                $monto = round((float) ($medio['monto'] ?? 0), 2);
                if ($monto <= 0.0001) {
                    continue;
                }
                if (! isset($debePorCuenta[$cuentaId])) {
                    $debePorCuenta[$cuentaId] = [
                        'concepto' => 'Medio de cobro — '.$medio['label'],
                        'cuenta_id' => $cuentaId,
                        'debe' => 0.,
                    ];
                }
                $debePorCuenta[$cuentaId]['debe'] += $monto;
            }
        }

        $totalFactura = round($totalFactura, 2);
        $impuestoInternoTotal = round($impuestoInternoTotal, 2);
        $base = self::desglosarBaseIva($totalFactura, $impuestoInternoTotal);

        $lineas = [];
        foreach ($debePorCuenta as $ln) {
            $lineas[] = self::lineaDebe($ln['concepto'], $ln['cuenta_id'], $ln['debe']);
        }
        $lineas = array_merge(
            $lineas,
            self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaVentasKiosco, $impuestoInternoTotal),
        );

        $debe = 0.;
        $haber = 0.;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        $asiento = [
            'numero' => 1,
            'titulo' => 'Factura cierre Waitry — QR / Mercado Pago (preview)',
            'pendiente_ejecucion' => true,
            'cantidad_comandas' => count($incluidos),
            'total' => $totalFactura,
            'lineas' => $lineas,
        ];

        return [
            'cantidad_comandas' => count($incluidos),
            'total' => $totalFactura,
            'asiento' => $asiento,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Preview consolidado del cierre: asientos 1–2 siempre; 3–4 TOTEM si aplica;
     * compensación efectivo no facturado vs fondo fijo máquinas cuando hay redistribución a efectivo.
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array{
     *   asientos: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   faltantes: list<array<string, mixed>>,
     *   cuentas_requeridas: list<array<string, mixed>>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   cuadre: array<string, mixed>
     * }
     */
    public static function generarPreviewCompletoProceso(
        array $movimientos,
        int $empresaId,
        array $configContable,
        array $contextoCuadro = [],
    ): array {
        $auditoria = self::auditarCuentasRequeridas($movimientos, $empresaId, $configContable, $contextoCuadro);
        $advertencias = $auditoria['advertencias'];

        $asientos = [];
        $a1 = self::asientoConsolidadoSinFacturarQr($movimientos, $empresaId, $configContable);
        if ($a1 !== null) {
            $asientos[] = $a1;
        }
        $a2 = self::asientoConsolidadoFacturadoAnitaJornada($empresaId, $configContable, $contextoCuadro);
        if ($a2 !== null) {
            $asientos[] = $a2;
            foreach ($a2['advertencias_asiento'] ?? [] as $adv) {
                $advertencias[] = $adv;
            }
        }
        foreach (self::asientosConsolidadosTotem($movimientos, $empresaId, $configContable) as $aTotem) {
            $asientos[] = $aTotem;
        }
        $aComp = self::asientoConsolidadoCompensacionEfectivoNoFacturado($movimientos, $empresaId, $configContable);
        if ($aComp !== null) {
            $asientos[] = $aComp;
            foreach ($aComp['advertencias_asiento'] ?? [] as $adv) {
                $advertencias[] = $adv;
            }
        } elseif (self::totalEfectivoNoFacturadoProceso($movimientos) > 0.0001) {
            $advertencias[] = 'Hay efectivo Waitry no facturado que no entra en la factura del proceso: configure cuenta caja efectivo y fondo fijo máquinas para el asiento de compensación.';
        }

        foreach ($asientos as $i => &$asiento) {
            $asiento['numero'] = $i + 1;
            $codigo = (string) ($asiento['codigo'] ?? '');
            if ($codigo === 'compensacion_efectivo_no_facturado') {
                $asiento['titulo'] = $asiento['numero'].' — Compensación efectivo no facturado (Waitry) vs fondo fijo máquinas';
                $movsComp = self::movimientosCompensacionEfectivoNoFacturado($movimientos);
                $asiento['comandas_alcance'] = self::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO;
                $asiento['cantidad_comandas'] = count($movsComp);
            } elseif ($codigo === 'sin_facturar_qr') {
                $asiento['comandas_alcance'] = self::COMANDAS_ALCANCE_FACTURA_PROCESO;
            }
        }
        unset($asiento);

        $debe = 0.;
        $haber = 0.;
        foreach ($asientos as $asiento) {
            foreach ($asiento['lineas'] ?? [] as $ln) {
                if (($ln['tipo'] ?? '') === 'info') {
                    continue;
                }
                $debe += (float) ($ln['debe'] ?? 0);
                $haber += (float) ($ln['haber'] ?? 0);
            }
        }

        return [
            'asientos' => $asientos,
            'advertencias' => array_values(array_unique($advertencias)),
            'faltantes' => $auditoria['faltantes'],
            'cuentas_requeridas' => $auditoria['cuentas_requeridas'],
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
            'cuadre' => self::armarCuadreAsientos($asientos, $movimientos, $contextoCuadro),
        ];
    }

    /**
     * Cuadre de totales de asientos vs montos del cuadro / grupos del tramo.
     *
     * @param  list<array<string, mixed>>  $asientos
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $contextoCuadro
     * @return array<string, mixed>
     */
    public static function armarCuadreAsientos(
        array $asientos,
        array $movimientos,
        array $contextoCuadro = [],
    ): array {
        $tolerancia = 0.02;
        $totalFacturacionJornada = round((float) ($contextoCuadro['total_facturacion'] ?? 0), 2);
        $totalPendienteWaitry = round((float) ($contextoCuadro['total_pendiente_facturar'] ?? 0), 2);
        $totalAnitaJornadaCuadro = round((float) ($contextoCuadro['total_anita_jornada_cuadro'] ?? 0), 2);

        $totalMedioReal = self::sumaTotalesGrupo(
            $movimientos,
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL,
        );
        $totalTotem = self::sumaTotalesGrupo(
            $movimientos,
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM,
        );
        $totalAFacturarQr = self::totalQrFacturaProceso($movimientos);
        $totalFacturadoWaitry = round($totalMedioReal + $totalTotem, 2);

        $totalAnitaJornadaAsiento = 0.;
        foreach ($asientos as $asientoRef) {
            if (($asientoRef['codigo'] ?? '') === 'ventas_medio_real') {
                $totalAnitaJornadaAsiento = round((float) ($asientoRef['total'] ?? 0), 2);
                break;
            }
        }

        $mapReferencia = [
            'sin_facturar_qr' => [
                'total' => $totalAFacturarQr,
                'etiqueta' => 'Waitry sin facturar — QR / Mercado Pago (tras redistribución)',
            ],
            'ventas_medio_real' => [
                'total' => $totalAnitaJornadaAsiento > 0.0001
                    ? $totalAnitaJornadaAsiento
                    : round((float) ($contextoCuadro['total_anita_asiento2'] ?? 0), 2),
                'etiqueta' => 'Facturación Anita jornada (excl. TOTEM, cobranzas ERP)',
            ],
            'totem_ventas_iva' => [
                'total' => $totalTotem,
                'etiqueta' => 'Facturado Anita — cobro TOTEM (tramo Waitry)',
            ],
            'totem_puente' => [
                'total' => $totalTotem,
                'etiqueta' => 'Facturado Anita — cobro TOTEM (contrapartida puente)',
            ],
            'compensacion_efectivo_no_facturado' => [
                'total' => self::totalEfectivoNoFacturadoProceso($movimientos),
                'etiqueta' => 'Cuadro: Waitry → efectivo (no entra en factura del proceso)',
            ],
        ];

        $filas = [];
        $totalAsiento1 = 0.;
        $totalAsientos23 = 0.;

        foreach ($asientos as $asiento) {
            $codigo = (string) ($asiento['codigo'] ?? '');
            $debe = round((float) ($asiento['resumen_debe'] ?? 0), 2);
            $haber = round((float) ($asiento['resumen_haber'] ?? 0), 2);
            $totalAsiento = round((float) ($asiento['total'] ?? 0), 2);
            if ($totalAsiento <= 0.0001) {
                $totalAsiento = round(max($debe, $haber), 2);
            }

            $ref = $mapReferencia[$codigo] ?? null;
            $refTotal = $ref !== null ? round((float) $ref['total'], 2) : null;
            $dif = $refTotal !== null ? round($totalAsiento - $refTotal, 2) : null;

            if ($codigo === 'sin_facturar_qr') {
                $totalAsiento1 = $totalAsiento;
            }
            if (in_array($codigo, ['ventas_medio_real', 'totem_ventas_iva'], true)) {
                $totalAsientos23 += $totalAsiento;
            }

            $filas[] = [
                'asiento_numero' => (int) ($asiento['numero'] ?? 0),
                'asiento_codigo' => $codigo,
                'asiento_titulo' => (string) ($asiento['titulo'] ?? ''),
                'total_asiento' => $totalAsiento,
                'debe' => $debe,
                'haber' => $haber,
                'debe_haber_cuadra' => abs($debe - $haber) <= $tolerancia,
                'referencia_total' => $refTotal,
                'referencia_etiqueta' => $ref['etiqueta'] ?? null,
                'diferencia' => $dif,
                'referencia_cuadra' => $dif === null || abs($dif) <= $tolerancia,
            ];
        }

        $validaciones = array_merge(
            self::validacionesMediosAsiento2VsCuadroAnita($asientos, $contextoCuadro, $tolerancia),
            [
            [
                'etiqueta' => 'Asiento 1 vs a facturar (QR / Mercado Pago)',
                'monto_asientos' => $totalAsiento1,
                'monto_referencia' => $totalAFacturarQr,
                'diferencia' => round($totalAsiento1 - $totalAFacturarQr, 2),
                'cuadra' => abs($totalAsiento1 - $totalAFacturarQr) <= $tolerancia,
            ],
            [
                'etiqueta' => 'Asientos 2+3 vs facturado Waitry del tramo (referencia)',
                'monto_asientos' => round($totalAsientos23, 2),
                'monto_referencia' => $totalFacturadoWaitry,
                'diferencia' => round($totalAsientos23 - $totalFacturadoWaitry, 2),
                'cuadra' => null,
                'nota' => 'El asiento 2 refleja toda la jornada Anita; el tramo Waitry puede ser un subconjunto.',
            ],
            [
                'etiqueta' => 'Asiento 2 vs facturación Anita jornada (excl. TOTEM)',
                'monto_asientos' => $totalAnitaJornadaAsiento,
                'monto_referencia' => round((float) ($contextoCuadro['total_anita_asiento2'] ?? $totalAnitaJornadaCuadro), 2),
                'diferencia' => round(
                    $totalAnitaJornadaAsiento - (float) ($contextoCuadro['total_anita_asiento2'] ?? $totalAnitaJornadaCuadro),
                    2,
                ),
                'cuadra' => abs(
                    $totalAnitaJornadaAsiento - (float) ($contextoCuadro['total_anita_asiento2'] ?? $totalAnitaJornadaCuadro),
                ) <= $tolerancia,
                'nota' => 'Incluye terminales con y sin Waitry; excluye cobro TOTEM.',
            ],
            [
                'etiqueta' => 'Facturado Waitry vs facturación Anita jornada (referencia)',
                'monto_asientos' => $totalFacturadoWaitry,
                'monto_referencia' => $totalFacturacionJornada,
                'diferencia' => round($totalFacturadoWaitry - $totalFacturacionJornada, 2),
                'cuadra' => null,
                'nota' => 'La jornada Anita incluye ventas sin Waitry; no tiene por qué coincidir.',
            ],
            [
                'etiqueta' => 'Pendiente Waitry cuadro vs asiento 1 (referencia)',
                'monto_asientos' => $totalAsiento1,
                'monto_referencia' => $totalPendienteWaitry,
                'diferencia' => round($totalAsiento1 - $totalPendienteWaitry, 2),
                'cuadra' => null,
                'nota' => 'El cuadro incluye todo Waitry sin facturar; el asiento 1 solo el QR a facturar tras %.',
            ],
        ],
        );

        $todasCuadran = true;
        foreach ($filas as $f) {
            if ($f['referencia_etiqueta'] !== null && ! $f['debe_haber_cuadra']) {
                $todasCuadran = false;
            }
            if ($f['referencia_etiqueta'] !== null && ! $f['referencia_cuadra']) {
                $todasCuadran = false;
            }
        }
        foreach ($validaciones as $v) {
            if ($v['cuadra'] === false) {
                $todasCuadran = false;
            }
        }

        return [
            'filas' => $filas,
            'referencias_cuadro' => [
                'total_facturacion_anita_jornada' => $totalFacturacionJornada,
                'total_anita_jornada_cuadro' => $totalAnitaJornadaCuadro,
                'total_anita_jornada_asiento' => $totalAnitaJornadaAsiento,
                'total_pendiente_facturar_waitry' => $totalPendienteWaitry,
                'total_a_facturar_qr_proceso' => $totalAFacturarQr,
                'total_anita_asiento2' => round((float) ($contextoCuadro['total_anita_asiento2'] ?? 0), 2),
                'total_facturado_medio_real' => $totalMedioReal,
                'total_facturado_totem' => $totalTotem,
                'total_facturado_waitry_tramo' => $totalFacturadoWaitry,
            ],
            'validaciones' => $validaciones,
            'cuadre_global_ok' => $todasCuadran,
        ];
    }

    /**
     * Cruza columnas QR/MP/Efectivo/Otros/Dif.caja del asiento 2 con la fila Anita del cuadro.
     *
     * @param  list<array<string, mixed>>  $asientos
     * @param  array<string, mixed>  $contextoCuadro
     * @return list<array<string, mixed>>
     */
    private static function validacionesMediosAsiento2VsCuadroAnita(
        array $asientos,
        array $contextoCuadro,
        float $tolerancia,
    ): array {
        $empresaId = (int) ($contextoCuadro['empresa_id'] ?? 0);
        $filaCuadro = is_array($contextoCuadro['anita_asiento2_fila_ref'] ?? null)
            ? $contextoCuadro['anita_asiento2_fila_ref']
            : (is_array($contextoCuadro['anita_jornada_fila'] ?? null)
                ? $contextoCuadro['anita_jornada_fila']
                : []);
        if ($empresaId <= 0 || $filaCuadro === []) {
            return [];
        }

        $asiento2 = null;
        foreach ($asientos as $asiento) {
            if (($asiento['codigo'] ?? '') === 'ventas_medio_real') {
                $asiento2 = $asiento;
                break;
            }
        }
        if ($asiento2 === null) {
            return [];
        }

        $debeCols = ['qr' => 0., 'mp' => 0., 'efectivo' => 0., 'otros' => 0., 'diferencia_caja' => 0.];
        foreach ($asiento2['lineas'] ?? [] as $ln) {
            $monto = round((float) ($ln['debe'] ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            $concepto = mb_strtolower((string) ($ln['concepto'] ?? ''));
            if (str_contains($concepto, 'diferencia') || str_contains($concepto, 'invit')) {
                $debeCols['diferencia_caja'] = round($debeCols['diferencia_caja'] + $monto, 2);

                continue;
            }
            $ccId = (int) ($ln['cuenta_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(['id' => $ccId], $empresaId);
            $col = CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio($clave);
            $debeCols[$col] = round($debeCols[$col] + $monto, 2);
        }

        $out = [];
        foreach (['qr' => 'QR', 'mp' => 'MP', 'efectivo' => 'Efectivo', 'otros' => 'Otros', 'diferencia_caja' => 'Dif. caja'] as $col => $label) {
            $ref = round((float) ($filaCuadro[$col] ?? 0), 2);
            $asientoMonto = round((float) ($debeCols[$col] ?? 0), 2);
            if (abs($ref) <= 0.0001 && abs($asientoMonto) <= 0.0001) {
                continue;
            }
            $out[] = [
                'etiqueta' => 'Asiento 2 vs cuadro — '.$label,
                'monto_asientos' => $asientoMonto,
                'monto_referencia' => $ref,
                'diferencia' => round($asientoMonto - $ref, 2),
                'cuadra' => abs($asientoMonto - $ref) <= $tolerancia,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    private static function sumaTotalesGrupo(array $movimientos, string $grupo): float
    {
        $sum = 0.;
        foreach ($movimientos as $mov) {
            if (($mov['grupo'] ?? '') !== $grupo) {
                continue;
            }
            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }
            $sum = round($sum + $total, 2);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array{
     *   advertencias: list<string>,
     *   faltantes: list<array<string, mixed>>,
     *   cuentas_requeridas: list<array<string, mixed>>
     * }
     */
    public static function auditarCuentasRequeridas(
        array $movimientos,
        int $empresaId,
        array $configContable,
        array $contextoCuadro = [],
    ): array {
        $advertencias = [];
        $faltantes = [];
        $requeridas = [];

        $agregarContable = static function (string $concepto, ?int $id, string $origen) use (&$requeridas, &$faltantes, &$advertencias): void {
            $ok = $id !== null && $id > 0;
            $requeridas[] = [
                'tipo' => 'contable',
                'concepto' => $concepto,
                'cuenta_id' => $id,
                'origen' => $origen,
                'ok' => $ok,
            ];
            if (! $ok) {
                $faltantes[] = ['tipo' => 'contable', 'concepto' => $concepto, 'origen' => $origen];
                $advertencias[] = 'Falta '.$concepto.' ('.$origen.').';
            }
        };

        $agregarContable('Cuenta ventas', self::intOrNull($configContable['cuenta_ventas_id'] ?? null), 'config cierre');
        $agregarContable('Cuenta IVA (débito fiscal)', self::intOrNull($configContable['cuenta_iva_id'] ?? null), 'config cierre');

        $hayImpuestoInterno = false;
        foreach ($movimientos as $mov) {
            if (round((float) ($mov['impuesto_interno'] ?? 0), 2) > 0.0001) {
                $hayImpuestoInterno = true;
                break;
            }
        }
        $datosAnitaAsiento = self::resolverDatosAsientoAnitaJornada($empresaId, $contextoCuadro);
        if (($datosAnitaAsiento['facturas_con_impuesto_interno'] ?? 0) > 0) {
            $hayImpuestoInterno = true;
        }
        if ($hayImpuestoInterno) {
            $idKiosco = self::intOrNull($configContable['cuenta_ventas_kiosco_id'] ?? null);
            $requeridas[] = [
                'tipo' => 'contable',
                'concepto' => 'Cuenta ventas de kiosco (cigarrillos)',
                'cuenta_id' => $idKiosco,
                'origen' => 'config cierre',
                'ok' => $idKiosco !== null && $idKiosco > 0,
                'opcional' => true,
            ];
            if ($idKiosco === null || $idKiosco <= 0) {
                $advertencias[] = 'Hay facturas con impuesto interno (cigarrillos): configure cuenta ventas de kiosco en el cierre Waitry; '
                    .'mientras tanto se usará la cuenta de ventas general para el haber kiosco.';
            }
        }

        if (($datosAnitaAsiento['cantidad_invitaciones'] ?? 0) > 0) {
            $idDif = self::intOrNull($configContable['cuenta_diferencia_caja_id'] ?? null);
            $agregarContable('Cuenta diferencia de caja (invitaciones $0,01)', $idDif, 'config cierre');
            if ($idDif === null || $idDif <= 0) {
                $advertencias[] = 'Hay '.(int) $datosAnitaAsiento['cantidad_invitaciones']
                    .' invitación(es) $0,01 sin cobranza: configure cuenta diferencia de caja en el cierre Waitry.';
            }
        }

        $totalEfectivoNoFacturado = self::totalEfectivoNoFacturadoProceso($movimientos);
        if ($totalEfectivoNoFacturado > 0.0001) {
            $agregarContable(
                'Cuenta fondo fijo máquinas (compensación efectivo no facturado)',
                self::intOrNull($configContable['cuenta_fondo_fijo_maquinas_id'] ?? null),
                'config cierre',
            );
        }

        $agregarCaja = static function (string $concepto, ?int $id, string $origen) use (&$requeridas, &$faltantes, &$advertencias, $empresaId): void {
            $ok = $id !== null && $id > 0 && self::cuentacajaExiste($id, $empresaId);
            $requeridas[] = [
                'tipo' => 'caja',
                'concepto' => $concepto,
                'cuenta_id' => $id,
                'origen' => $origen,
                'ok' => $ok,
            ];
            if (! $ok) {
                $faltantes[] = ['tipo' => 'caja', 'concepto' => $concepto, 'origen' => $origen];
                $advertencias[] = 'Falta '.$concepto.' ('.$origen.').';
            }
        };

        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $totemId = self::intOrNull($totem['id'] ?? null);
        $agregarCaja('Cuenta puente TOTEM', $totemId, 'GastronomiaCuentacajaTotem');
        if ($totemId === null || $totemId <= 0) {
            $msgTotem = GastronomiaCuentacajaTotem::mensajeErrorResolucion($empresaId);
            if ($msgTotem !== null) {
                $advertencias[] = $msgTotem;
            }
        }

        $clavesMedio = [];
        foreach ($movimientos as $mov) {
            $grupo = (string) ($mov['grupo'] ?? '');
            if (in_array($grupo, [
                CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA,
                CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR,
            ], true)) {
                continue;
            }
            $medios = match ($grupo) {
                CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR => self::mediosPlanificadosSinFacturar($mov, $empresaId),
                CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM => self::mediosPuenteTotem($mov, round((float) ($mov['total'] ?? 0), 2), $empresaId),
                default => self::mediosPlanificadosFacturado($mov, $empresaId),
            };
            foreach ($medios as $m) {
                $clavesMedio[(string) ($m['clave'] ?? '')] = true;
            }
        }

        if (isset($clavesMedio[CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO])) {
            $agregarCaja('Cuenta caja efectivo', GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId), 'GastronomiaCuentacajaEfectivo');
        }
        if (isset($clavesMedio[CierreJornadaProcesoMedioSupport::CLAVE_QR])) {
            $agregarCaja(
                'Cuenta caja QR (Totalcoin)',
                WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN, $empresaId),
                'WaitryMedioPagoCuentacajaSupport',
            );
        }
        if (isset($clavesMedio[CierreJornadaProcesoMedioSupport::CLAVE_MP])) {
            $agregarCaja(
                'Cuenta caja Mercado Pago',
                WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO, $empresaId),
                'WaitryMedioPagoCuentacajaSupport',
            );
        }

        $cuentasCajaAnita = [];
        foreach ($datosAnitaAsiento['debe_por_cuenta'] ?? [] as $ln) {
            $ccId = self::intOrNull($ln['cuenta_id'] ?? null);
            if ($ccId !== null && $ccId > 0) {
                $cuentasCajaAnita[$ccId] = (string) ($ln['concepto'] ?? 'Medio de cobro Anita');
            }
        }
        foreach ($cuentasCajaAnita as $ccId => $concepto) {
            if ($totemId !== null && $ccId === $totemId) {
                continue;
            }
            $agregarCaja($concepto, $ccId, 'cobranza Anita jornada');
        }

        if (CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId) === null) {
            $faltantes[] = ['tipo' => 'proceso', 'concepto' => 'Punto de venta del proceso', 'origen' => 'config / env'];
            $advertencias[] = 'Falta punto de venta del proceso (config BD o GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA).';
        }

        return [
            'advertencias' => array_values(array_unique($advertencias)),
            'faltantes' => $faltantes,
            'cuentas_requeridas' => $requeridas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $asientos
     * @param  array<string, mixed>  $configContable
     * @return list<array<string, mixed>>
     */
    public static function enriquecerAsientosConEtiquetas(array $asientos, int $empresaId, array $configContable): array
    {
        $ids = [];
        foreach ($asientos as $asiento) {
            foreach ($asiento['lineas'] ?? [] as $ln) {
                $id = self::intOrNull($ln['cuenta_id'] ?? null);
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        $mapEtiquetas = self::mapEtiquetasCuentaPorIds(array_keys($ids), $empresaId, $configContable);

        foreach ($asientos as $ai => $asiento) {
            $lineas = $asiento['lineas'] ?? [];
            if (! is_array($lineas)) {
                continue;
            }
            foreach ($lineas as $li => $ln) {
                $id = self::intOrNull($ln['cuenta_id'] ?? null);
                if ($id === null) {
                    $lineas[$li]['cuenta_label'] = '—';
                    $lineas[$li]['cuenta_codigo'] = '';
                    $lineas[$li]['cuenta_nombre'] = '';
                    continue;
                }
                $etiq = $mapEtiquetas[$id] ?? null;
                if ($etiq === null) {
                    self::aplicarEtiquetaCuentaLinea($lineas[$li], '#'.$id, '', 'contable');
                    continue;
                }
                self::aplicarEtiquetaCuentaLinea(
                    $lineas[$li],
                    (string) $etiq['codigo'],
                    (string) $etiq['nombre'],
                    (string) $etiq['tipo'],
                );
            }
            $asientos[$ai]['lineas'] = $lineas;
        }

        return $asientos;
    }

    /**
     * @param  list<array<string, mixed>>  $cuentasRequeridas
     * @param  array<string, mixed>  $configContable
     * @return list<array<string, mixed>>
     */
    public static function enriquecerCuentasRequeridas(
        array $cuentasRequeridas,
        int $empresaId,
        array $configContable,
    ): array {
        $ids = [];
        foreach ($cuentasRequeridas as $c) {
            $id = self::intOrNull($c['cuenta_id'] ?? null);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        $mapEtiquetas = self::mapEtiquetasCuentaPorIds(array_keys($ids), $empresaId, $configContable);

        foreach ($cuentasRequeridas as &$c) {
            $id = self::intOrNull($c['cuenta_id'] ?? null);
            if ($id === null) {
                $c['cuenta_codigo'] = '';
                $c['cuenta_nombre'] = '';
                $c['cuenta_label'] = '—';
                continue;
            }
            $etiq = $mapEtiquetas[$id] ?? null;
            if ($etiq === null) {
                $c['cuenta_codigo'] = '#'.$id;
                $c['cuenta_nombre'] = '';
                $c['cuenta_label'] = '#'.$id;
                continue;
            }
            $c['cuenta_codigo'] = (string) $etiq['codigo'];
            $c['cuenta_nombre'] = (string) $etiq['nombre'];
            $c['cuenta_label'] = $etiq['codigo'] !== '' && $etiq['nombre'] !== ''
                ? trim($etiq['codigo'].' — '.$etiq['nombre'])
                : ($etiq['nombre'] !== '' ? $etiq['nombre'] : ($etiq['codigo'] !== '' ? $etiq['codigo'] : '#'.$id));
        }
        unset($c);

        return $cuentasRequeridas;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $configContable
     * @return array<int, array{codigo:string,nombre:string,tipo:string}>
     */
    private static function mapEtiquetasCuentaPorIds(array $ids, int $empresaId, array $configContable): array
    {
        $map = [];
        foreach (['cuenta_ventas', 'cuenta_iva', 'cuenta_ventas_kiosco', 'cuenta_fondo_fijo_maquinas', 'cuenta_diferencia_caja'] as $base) {
            $id = self::intOrNull($configContable[$base.'_id'] ?? null);
            if ($id !== null) {
                $map[$id] = [
                    'codigo' => trim((string) ($configContable[$base.'_codigo'] ?? '')),
                    'nombre' => trim((string) ($configContable[$base.'_nombre'] ?? '')),
                    'tipo' => 'contable',
                ];
            }
        }

        $ids = array_values(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0,
        ));

        if ($ids === []) {
            return $map;
        }

        $faltantes = array_values(array_diff($ids, array_keys($map)));

        if ($faltantes !== []) {
            $cajas = Cuentacaja::query()
                ->with(['cuentacontables:id,codigo,nombre,empresa_id'])
                ->whereIn('id', $faltantes)
                ->get(['id', 'codigo', 'nombre', 'cuentacontable_id']);

            foreach ($cajas as $caja) {
                $cont = $caja->cuentacontables;
                if ($cont !== null) {
                    $map[(int) $caja->id] = [
                        'codigo' => trim((string) $cont->codigo),
                        'nombre' => trim((string) $cont->nombre),
                        'tipo' => 'contable',
                    ];
                    continue;
                }
                $map[(int) $caja->id] = [
                    'codigo' => trim((string) $caja->codigo),
                    'nombre' => trim((string) $caja->nombre),
                    'tipo' => 'caja',
                ];
            }
        }

        $faltantes = array_values(array_diff($ids, array_keys($map)));
        if ($faltantes !== [] && $empresaId > 0) {
            $contables = Cuentacontable::query()
                ->whereIn('id', $faltantes)
                ->where('empresa_id', $empresaId)
                ->get(['id', 'codigo', 'nombre']);

            foreach ($contables as $cont) {
                $map[(int) $cont->id] = [
                    'codigo' => trim((string) $cont->codigo),
                    'nombre' => trim((string) $cont->nombre),
                    'tipo' => 'contable',
                ];
            }
        }

        return $map;
    }

    /**
     * Total de comandas 100 % efectivo (ajuste insumos / compensación contable).
     *
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function totalEfectivoNoFacturadoProceso(array $movimientos): float
    {
        return CierreJornadaProcesoFacturaComandasSupport::totalComandas(
            self::movimientosCompensacionEfectivoNoFacturado($movimientos),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array<string, mixed>|null
     */
    private static function asientoConsolidadoCompensacionEfectivoNoFacturado(
        array $movimientos,
        int $empresaId,
        array $configContable,
    ): ?array {
        $total = self::totalEfectivoNoFacturadoProceso($movimientos);
        if ($total <= 0.0001) {
            return null;
        }

        $advertenciasAsiento = [];
        $efectivoId = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);
        $fondoFijoId = (int) ($configContable['cuenta_fondo_fijo_maquinas_id'] ?? 0);

        if ($efectivoId === null || $efectivoId <= 0) {
            $advertenciasAsiento[] = 'Hay efectivo Waitry no facturado ('.round($total, 2).') pero falta cuenta caja efectivo.';

            return null;
        }
        if ($fondoFijoId <= 0) {
            $advertenciasAsiento[] = 'Hay efectivo Waitry no facturado ('.round($total, 2).') pero falta cuenta fondo fijo máquinas en config.';

            return null;
        }

        $lineas = [
            self::lineaDebe('Efectivo Waitry no incluido en factura del proceso', $efectivoId, $total),
            self::lineaHaber('Compensación fondo fijo máquinas — efectivo no facturado', $fondoFijoId, $total),
        ];

        $asiento = self::armarAsientoConsolidado(
            0,
            'compensacion_efectivo_no_facturado',
            'Asiento de compensación',
            $lineas,
            ['total' => $total],
            true,
        );
        $asiento['advertencias_asiento'] = $advertenciasAsiento;

        return $asiento;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return array<string, mixed>|null
     */
    private static function asientoConsolidadoSinFacturarQr(
        array $movimientos,
        int $empresaId,
        array $configContable,
    ): ?array {
        $preview = self::generarPreviewFacturaProceso($movimientos, $empresaId, $configContable);
        if ($preview['asiento'] === null) {
            return null;
        }

        return self::armarAsientoConsolidado(
            1,
            'sin_facturar_qr',
            '1 — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
            $preview['asiento']['lineas'] ?? [],
            [
                'cantidad_comandas' => $preview['cantidad_comandas'],
                'total' => $preview['total'],
            ],
            true,
        );
    }

    /**
     * Asiento 2: facturación Anita jornada completa (excl. TOTEM), debe por cobranzas ERP.
     *
     * @param  array<string, mixed>  $configContable
     * @param  array<string, mixed>  $contextoCuadro
     * @return array<string, mixed>|null
     */
    private static function asientoConsolidadoFacturadoAnitaJornada(
        int $empresaId,
        array $configContable,
        array $contextoCuadro = [],
    ): ?array {
        $datos = self::resolverDatosAsientoAnitaJornada($empresaId, $contextoCuadro);
        if (($datos['cantidad_emisiones'] ?? 0) <= 0 || abs((float) ($datos['total'] ?? 0)) <= 0.0001) {
            return null;
        }

        $cuentaVentas = (int) ($configContable['cuenta_ventas_id'] ?? 0);
        $cuentaIva = (int) ($configContable['cuenta_iva_id'] ?? 0);
        $cuentaVentasKiosco = self::cuentaVentasKioscoId($configContable);

        $lineas = [];
        foreach ($datos['debe_por_cuenta'] as $ln) {
            $monto = round((float) ($ln['debe'] ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }
            $concepto = (string) ($ln['concepto'] ?? 'Medio de cobro');
            $cuentaId = (int) ($ln['cuenta_id'] ?? 0);
            if ($monto > 0.0001) {
                $lineas[] = self::lineaDebe($concepto, $cuentaId, $monto);
            } else {
                $lineas[] = self::lineaHaber($concepto.' (NC)', $cuentaId, abs($monto));
            }
        }

        $cuentaDifCaja = (int) ($configContable['cuenta_diferencia_caja_id'] ?? 0);
        $debeDifCaja = round((float) ($datos['debe_diferencia_caja'] ?? 0), 2);
        if (abs($debeDifCaja) > 0.0001) {
            if ($cuentaDifCaja > 0) {
                if ($debeDifCaja > 0.0001) {
                    $lineas[] = self::lineaDebe(
                        'Diferencia de caja — invitaciones ($0,01)',
                        $cuentaDifCaja,
                        $debeDifCaja,
                    );
                } else {
                    $lineas[] = self::lineaHaber(
                        'Diferencia de caja — invitaciones NC ($0,01)',
                        $cuentaDifCaja,
                        abs($debeDifCaja),
                    );
                }
            }
        }

        $lineas = array_merge(
            $lineas,
            self::lineasHaberVentasConsolidado(
                (float) ($datos['ventas_gravadas'] ?? 0),
                (float) ($datos['ventas_kiosco'] ?? 0),
                (float) ($datos['iva_normal'] ?? 0),
                (float) ($datos['iva_cigarrillos'] ?? 0),
                $cuentaVentas,
                $cuentaVentasKiosco,
                $cuentaIva,
            ),
        );

        $asiento = self::armarAsientoConsolidado(
            0,
            'ventas_medio_real',
            '2 — Facturación Anita jornada (excl. TOTEM) — ventas / IVA / kiosco',
            $lineas,
            [
                'cantidad_facturas' => (int) ($datos['cantidad_emisiones'] ?? 0),
                'cantidad_notas_credito' => (int) ($datos['cantidad_notas_credito'] ?? 0),
                'cantidad_invitaciones' => (int) ($datos['cantidad_invitaciones'] ?? 0),
                'total' => round((float) ($datos['total'] ?? 0), 2),
                'impuesto_interno_total' => round((float) ($datos['impuesto_interno_total'] ?? 0), 2),
                'facturas_con_impuesto_interno' => (int) ($datos['facturas_con_impuesto_interno'] ?? 0),
            ],
        );
        $asiento['advertencias_asiento'] = $datos['advertencias'] ?? [];

        return $asiento;
    }

    /**
     * @param  array<string, mixed>  $contextoCuadro
     * @return array<string, mixed>
     */
    private static function resolverDatosAsientoAnitaJornada(int $empresaId, array $contextoCuadro): array
    {
        if (isset($contextoCuadro['datos_asiento_anita']) && is_array($contextoCuadro['datos_asiento_anita'])) {
            return $contextoCuadro['datos_asiento_anita'];
        }

        $fechaJornada = (string) ($contextoCuadro['fecha_jornada'] ?? '');
        if ($fechaJornada === '') {
            return CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem(0, '');
        }

        return CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresaId, $fechaJornada);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $configContable
     * @return list<array<string, mixed>>
     */
    private static function asientosConsolidadosTotem(
        array $movimientos,
        int $empresaId,
        array $configContable,
    ): array {
        $movs = array_values(array_filter(
            $movimientos,
            fn (array $m) => ($m['grupo'] ?? '') === CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM
                && round((float) ($m['total'] ?? 0), 2) > 0.0001,
        ));
        if ($movs === []) {
            return [];
        }

        $cuentaVentas = (int) ($configContable['cuenta_ventas_id'] ?? 0);
        $cuentaIva = (int) ($configContable['cuenta_iva_id'] ?? 0);
        $cuentaVentasKiosco = self::cuentaVentasKioscoId($configContable);
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $totemId = (int) ($totem['id'] ?? 0);

        $totalFacturado = 0.;
        $ventasGravadas = 0.;
        $ventasKiosco = 0.;
        $ivaNormal = 0.;
        $ivaCigarrillos = 0.;
        $impuestoInternoTotal = 0.;
        $conImpuestoInterno = 0;
        /** @var array<int, array{concepto:string,cuenta_id:int,debe:float}> */
        $debePuentePorCuenta = [];

        foreach ($movs as $mov) {
            $total = round((float) ($mov['total'] ?? 0), 2);
            $impuestoInterno = round((float) ($mov['impuesto_interno'] ?? 0), 2);
            $base = self::desglosarBaseIva($total, $impuestoInterno);
            $totalFacturado += $total;
            $impuestoInternoTotal += $impuestoInterno;
            if ($impuestoInterno > 0.0001) {
                $ventasKiosco += round($base['gravado'] + $impuestoInterno, 2);
                $ivaCigarrillos += $base['iva'];
                $conImpuestoInterno++;
            } else {
                $ventasGravadas += $base['gravado'];
                $ivaNormal += $base['iva'];
            }

            foreach (self::mediosPuenteTotem($mov, $total, $empresaId) as $medio) {
                $cuentaId = (int) ($medio['cuentacaja_id'] ?? 0);
                $monto = round((float) ($medio['monto'] ?? 0), 2);
                if ($monto <= 0.0001) {
                    continue;
                }
                if (! isset($debePuentePorCuenta[$cuentaId])) {
                    $debePuentePorCuenta[$cuentaId] = [
                        'concepto' => 'Medio real vs TOTEM — '.$medio['label'],
                        'cuenta_id' => $cuentaId,
                        'debe' => 0.,
                    ];
                }
                $debePuentePorCuenta[$cuentaId]['debe'] = round($debePuentePorCuenta[$cuentaId]['debe'] + $monto, 2);
            }
        }

        $lineasPrincipal = [
            self::lineaDebe('TOTEM (puente cobro tótem)', $totemId, round($totalFacturado, 2)),
        ];
        $lineasPrincipal = array_merge(
            $lineasPrincipal,
            self::lineasHaberVentasConsolidado(
                $ventasGravadas,
                $ventasKiosco,
                $ivaNormal,
                $ivaCigarrillos,
                $cuentaVentas,
                $cuentaVentasKiosco,
                $cuentaIva,
            ),
        );

        $lineasPuente = [];
        foreach ($debePuentePorCuenta as $ln) {
            $lineasPuente[] = self::lineaDebe($ln['concepto'], $ln['cuenta_id'], $ln['debe']);
        }
        $lineasPuente[] = self::lineaHaber('Contra TOTEM (puente a cero)', $totemId, round($totalFacturado, 2));

        $meta = [
            'cantidad_facturas' => count($movs),
            'total' => round($totalFacturado, 2),
            'impuesto_interno_total' => round($impuestoInternoTotal, 2),
            'facturas_con_impuesto_interno' => $conImpuestoInterno,
        ];

        return [
            self::armarAsientoConsolidado(
                0,
                'totem_ventas_iva',
                '3 — TOTEM → ventas / IVA / kiosco',
                $lineasPrincipal,
                $meta,
            ),
            self::armarAsientoConsolidado(
                0,
                'totem_puente',
                '4 — Puente TOTEM (medio real → TOTEM)',
                $lineasPuente,
                $meta,
            ),
        ];
    }

    /**
     * @return array{gravado:float,iva:float,neto_venta:float}
     */
    private static function desglosarBaseIva(float $total, float $impuestoInterno): array
    {
        $netoVentas = round(max(0., $total - $impuestoInterno), 2);
        $gravado = round($netoVentas / (1. + self::TASA_IVA_DEFAULT / 100.), 2);
        $iva = round($netoVentas - $gravado, 2);

        return [
            'gravado' => $gravado,
            'iva' => $iva,
            'neto_venta' => $netoVentas,
        ];
    }

    /**
     * Solo se factura lo pendiente con medio QR o MP (Posnet), tras redistribución.
     *
     * @param  array<string, mixed>  $mov
     */
    private static function debeFacturarSinFacturar(array $mov): bool
    {
        $clavesFacturables = CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar();
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan)) {
            foreach ($plan as $p) {
                if (in_array($p['clave'] ?? '', $clavesFacturables, true)
                    && (float) ($p['monto'] ?? 0) > 0.0001) {
                    return true;
                }
            }

            return false;
        }

        $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_waitry_clave'] ?? '');

        return in_array($clave, $clavesFacturables, true)
            || $clave === ''
            || $clave === CierreJornadaProcesoMedioSupport::CLAVE_OTRO;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function mediosPlanificadosFacturado(array $mov, int $empresaId): array
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            return self::resolverMediosDesdePlan($plan, $empresaId);
        }

        $clave = (string) ($mov['medio_pago_planificado'] ?? $mov['medio_anita_clave'] ?? '');
        $total = round((float) ($mov['total'] ?? 0), 2);

        return self::unMedio($clave, $total, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function mediosPlanificadosSinFacturar(array $mov, int $empresaId): array
    {
        $clavesFacturables = CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar();
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            $filtrados = array_values(array_filter(
                $plan,
                fn (array $p) => in_array($p['clave'] ?? '', $clavesFacturables, true)
                    && (float) ($p['monto'] ?? 0) > 0.0001,
            ));
            if ($filtrados !== []) {
                return self::resolverMediosDesdePlan($filtrados, $empresaId);
            }

            return [];
        }

        $total = round((float) ($mov['total'] ?? 0), 2);
        $claveDefault = (string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_QR);
        if (! in_array($claveDefault, $clavesFacturables, true)) {
            $claveDefault = CierreJornadaProcesoMedioSupport::CLAVE_QR;
        }

        return self::unMedio($claveDefault, $total, $empresaId);
    }

    /**
     * @param  list<array{clave:string,monto:float}>  $plan
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function resolverMediosDesdePlan(array $plan, int $empresaId): array
    {
        $out = [];
        foreach ($plan as $p) {
            $clave = (string) ($p['clave'] ?? '');
            $monto = round((float) ($p['monto'] ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            foreach (self::unMedio($clave, $monto, $empresaId) as $m) {
                $out[] = $m;
            }
        }

        return $out;
    }

    /**
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function unMedio(string $clave, float $monto, int $empresaId): array
    {
        $cuenta = self::cuentacajaPorClave($clave, $empresaId);

        return [[
            'clave' => $clave,
            'cuentacaja_id' => (int) ($cuenta['id'] ?? 0),
            'label' => (string) ($cuenta['label'] ?? CierreJornadaProcesoMedioSupport::etiquetaClave($clave)),
            'monto' => round($monto, 2),
        ]];
    }

    /**
     * @return array{id:int,label:string}
     */
    private static function cuentacajaPorClave(string $clave, int $empresaId): array
    {
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
            $id = GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId);

            return ['id' => $id ?? 0, 'label' => 'Efectivo'];
        }
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_QR) {
            $id = WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN,
                $empresaId,
            );

            return ['id' => $id ?? 0, 'label' => 'QR (Totalcoin)'];
        }
        if ($clave === CierreJornadaProcesoMedioSupport::CLAVE_MP) {
            $id = WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO,
                $empresaId,
            );

            return ['id' => $id ?? 0, 'label' => 'Mercado Pago'];
        }

        return ['id' => 0, 'label' => CierreJornadaProcesoMedioSupport::etiquetaClave($clave)];
    }

    /**
     * @param  list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>  $medios
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoFacturadoMedioReal(
        int $numero,
        array $mov,
        array $medios,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaVentasKiosco,
        float $impuestoInterno,
    ): array {
        $lineas = [];
        foreach ($medios as $m) {
            $lineas[] = self::lineaDebe(
                'Medio de cobro — '.$m['label'],
                $m['cuentacaja_id'],
                $m['monto'],
            );
        }
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaVentasKiosco, $impuestoInterno));

        return self::armarAsiento(
            $numero,
            'Facturado — medio real',
            $mov,
            $lineas,
        );
    }

    /**
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoTotemPrincipal(
        int $numero,
        array $mov,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaVentasKiosco,
        float $impuestoInterno,
        int $empresaId,
    ): array {
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $total = round((float) ($mov['total'] ?? 0), 2);
        $lineas = [
            self::lineaDebe('TOTEM (puente cobro tótem)', (int) ($totem['id'] ?? 0), $total),
        ];
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaVentasKiosco, $impuestoInterno));

        return self::armarAsiento($numero, 'Facturado — asiento TOTEM → ventas/IVA', $mov, $lineas);
    }

    /**
     * Puente TOTEM → medio real (QR/efectivo según redistribución). Haber TOTEM cancela el puente duplicado.
     *
     * @return array<string, mixed>
     */
    private static function asientoTotemPuente(
        int $numero,
        array $mov,
        float $total,
        int $empresaId,
    ): array {
        $totem = GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId);
        $medios = self::mediosPuenteTotem($mov, $total, $empresaId);
        $lineas = [];
        foreach ($medios as $m) {
            $lineas[] = self::lineaDebe('Medio real vs TOTEM — '.$m['label'], (int) $m['cuentacaja_id'], $m['monto']);
        }
        $lineas[] = self::lineaHaber('Contra TOTEM (puente)', (int) ($totem['id'] ?? 0), $total);

        return self::armarAsiento(
            $numero,
            'Facturado TOTEM — medio real vs puente TOTEM',
            $mov,
            $lineas,
        );
    }

    /**
     * Medios del puente TOTEM: redistribución planificada o medio Waitry real (QR por defecto).
     *
     * @return list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>
     */
    private static function mediosPuenteTotem(array $mov, float $total, int $empresaId): array
    {
        $plan = $mov['medios_pago_planificados'] ?? null;
        if (is_array($plan) && $plan !== []) {
            return self::resolverMediosDesdePlan($plan, $empresaId);
        }

        $claveReal = (string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_QR);

        return self::unMedio($claveReal, $total, $empresaId);
    }

    /**
     * @param  list<array{clave:string,cuentacaja_id:int,label:string,monto:float}>  $medios
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return array<string, mixed>
     */
    private static function asientoPendienteFacturacion(
        int $numero,
        array $mov,
        array $medios,
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaVentasKiosco,
        float $impuestoInterno,
        int $cuentaFondoFijo,
    ): array {
        $lineas = [];
        foreach ($medios as $m) {
            $lineas[] = self::lineaDebe('A facturar — '.$m['label'], $m['cuentacaja_id'], $m['monto']);
        }
        $lineas = array_merge($lineas, self::lineasHaberVentas($base, $cuentaVentas, $cuentaIva, $cuentaVentasKiosco, $impuestoInterno));
        if ($cuentaFondoFijo > 0) {
            $lineas[] = [
                'tipo' => 'info',
                'concepto' => 'Fondo fijo máquinas (referencia)',
                'cuenta_id' => $cuentaFondoFijo,
                'debe' => 0.,
                'haber' => 0.,
            ];
        }

        return self::armarAsiento(
            $numero,
            'Pendiente facturación QR',
            $mov,
            $lineas,
            true,
        );
    }

    /**
     * @param  array{gravado:float,iva:float,neto_venta:float}  $base
     * @return list<array<string, mixed>>
     */
    private static function lineasHaberVentas(
        array $base,
        int $cuentaVentas,
        int $cuentaIva,
        int $cuentaVentasKiosco,
        float $impuestoInterno,
    ): array {
        $cuentaKiosco = $cuentaVentasKiosco > 0 ? $cuentaVentasKiosco : $cuentaVentas;
        if ($impuestoInterno > 0.0001) {
            return self::lineasHaberVentasConsolidado(
                0.,
                round($base['gravado'] + $impuestoInterno, 2),
                0.,
                $base['iva'],
                $cuentaVentas,
                $cuentaKiosco,
                $cuentaIva,
            );
        }

        return self::lineasHaberVentasConsolidado(
            $base['gravado'],
            0.,
            $base['iva'],
            0.,
            $cuentaVentas,
            $cuentaKiosco,
            $cuentaIva,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lineasHaberVentasConsolidado(
        float $ventasGravadas,
        float $ventasKiosco,
        float $ivaNormal,
        float $ivaCigarrillos,
        int $cuentaVentas,
        int $cuentaVentasKiosco,
        int $cuentaIva,
    ): array {
        $lineas = [];
        if ($ventasGravadas > 0.0001) {
            $lineas[] = self::lineaHaber('Ventas gravadas', $cuentaVentas, $ventasGravadas);
        } elseif ($ventasGravadas < -0.0001) {
            $lineas[] = self::lineaDebe('Ventas gravadas (NC)', $cuentaVentas, abs($ventasGravadas));
        }
        if ($ventasKiosco > 0.0001) {
            $lineas[] = self::lineaHaber('Ventas kiosco (gravado + imp. interno)', $cuentaVentasKiosco, $ventasKiosco);
        } elseif ($ventasKiosco < -0.0001) {
            $lineas[] = self::lineaDebe('Ventas kiosco (gravado + imp. interno, NC)', $cuentaVentasKiosco, abs($ventasKiosco));
        }
        if ($ivaNormal > 0.0001) {
            $lineas[] = self::lineaHaber('IVA débito fiscal', $cuentaIva, $ivaNormal);
        } elseif ($ivaNormal < -0.0001) {
            $lineas[] = self::lineaDebe('IVA débito fiscal (NC)', $cuentaIva, abs($ivaNormal));
        }
        if ($ivaCigarrillos > 0.0001) {
            $lineas[] = self::lineaHaber(
                'IVA débito fiscal — cigarrillos / kiosco (imp. interno)',
                $cuentaIva,
                $ivaCigarrillos,
            );
        } elseif ($ivaCigarrillos < -0.0001) {
            $lineas[] = self::lineaDebe(
                'IVA débito fiscal — cigarrillos / kiosco (NC)',
                $cuentaIva,
                abs($ivaCigarrillos),
            );
        }

        return $lineas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaDebe(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => round($monto, 2),
            'haber' => 0.,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaHaber(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => 0.,
            'haber' => round($monto, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function armarAsientoConsolidado(
        int $numero,
        string $codigo,
        string $titulo,
        array $lineas,
        array $meta = [],
        bool $pendiente = false,
    ): array {
        $debe = 0.;
        $haber = 0.;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        return array_merge([
            'numero' => $numero,
            'codigo' => $codigo,
            'titulo' => $titulo,
            'pendiente_ejecucion' => $pendiente,
            'lineas' => $lineas,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
        ], $meta);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private static function armarAsiento(
        int $numero,
        string $titulo,
        array $mov,
        array $lineas,
        bool $pendiente = false,
    ): array {
        return [
            'numero' => $numero,
            'titulo' => $titulo,
            'pendiente_ejecucion' => $pendiente,
            'waitry_order_id' => $mov['waitry_order_id'] ?? null,
            'venta_codigo' => $mov['venta_codigo'] ?? '',
            'referencia' => trim((string) ($mov['display_id'] ?? '')),
            'total' => round((float) ($mov['total'] ?? 0), 2),
            'lineas' => $lineas,
        ];
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    private static function aplicarEtiquetaCuentaLinea(
        array &$ln,
        string $codigo,
        string $nombre,
        string $tipo,
    ): void {
        $ln['cuenta_codigo'] = trim($codigo);
        $ln['cuenta_nombre'] = trim($nombre);
        $ln['cuenta_label'] = $codigo !== '' && $nombre !== ''
            ? trim($codigo.' — '.$nombre)
            : ($nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : '—'));
        $ln['cuenta_tipo'] = $tipo;
    }

    /**
     * @param  array<string, mixed>  $configContable
     */
    private static function cuentaVentasKioscoId(array $configContable): int
    {
        return (int) ($configContable['cuenta_ventas_kiosco_id'] ?? 0);
    }

    private static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $id = (int) $v;

        return $id > 0 ? $id : null;
    }

    private static function cuentacajaExiste(int $cuentacajaId, int $empresaId): bool
    {
        if ($cuentacajaId <= 0) {
            return false;
        }

        return Cuentacaja::query()
            ->whereKey($cuentacajaId)
            ->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            })
            ->exists();
    }
}
