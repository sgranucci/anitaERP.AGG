<?php

namespace App\Support\Ventas\Gastronomia;

use InvalidArgumentException;

/**
 * Redistribución medios Waitry ↔ Efectivo según porcentaje sobre total facturado (solo en memoria: medios_pago_planificados).
 *
 * 1) Waitry sin facturar (QR / Totalcoin / MP) → efectivo hasta el objetivo del % (pesos enteros),
 *    en orden ascendente de waitry_order_id (MP y QR mezclados; no hay fase MP separada de QR).
 * 2) Facturado TOTEM con medio Waitry redistribuible → efectivo solo si queda cupo tras (1).
 * 3) Facturado Anita con cobro real en efectivo → mismo medio que originó el traslado a efectivo en (1)+(2)
 *    (QR→QR, MP→MP), para que el total del medio en el asiento coincida con lo cobrado en Waitry.
 *    La compensación Anita consume cupo QR antes que MP.
 */
final class CierreJornadaProcesoRedistribucionSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<array<string, mixed>>  $facturasAnitaEfectivo  Facturas Anita jornada cobradas en efectivo (cuenta real)
     * @return array{
     *   movimientos: list<array<string, mixed>>,
     *   objetivo_importe: float,
     *   asignado_sin_facturar_a_efectivo: float,
     *   asignado_efectivo_por_medio_origen: array<string, float>,
     *   asignado_facturado_efectivo_a_qr: float,
     *   asignado_facturado_efectivo_a_mp: float,
     *   asignado_facturado_efectivo_compensacion: float,
     *   asignado_facturado_efectivo_por_medio: array<string, float>,
     *   ajustes: list<array<string, mixed>>
     * }
     */
    public static function aplicar(
        array $movimientos,
        float $totalFacturacion,
        float $porcentaje,
        array $facturasAnitaEfectivo = [],
    ): array {
        $objetivo = self::objetivoDesdePorcentaje($totalFacturacion, $porcentaje);
        $restanteSinFacturar = $objetivo;
        $ajustes = [];
        $asignadoSinFacturar = 0.0;
        $asignadoEfectivoPorMedioOrigen = self::cuposCompensacionVacios();

        $out = [];
        foreach ($movimientos as $mov) {
            $copia = $mov;
            $copia['medio_pago_planificado'] = $copia['medio_pago_planificado'] ?? null;
            $copia['medios_pago_planificados'] = $copia['medios_pago_planificados'] ?? null;
            $out[] = $copia;
        }

        usort($out, static fn (array $a, array $b) => ($a['waitry_order_id'] ?? 0) <=> ($b['waitry_order_id'] ?? 0));

        foreach ($out as $idx => &$mov) {
            $grupo = (string) ($mov['grupo'] ?? '');
            if ($grupo !== CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
                continue;
            }
            if ($restanteSinFacturar <= 0.0001) {
                break;
            }

            $medioOrigen = (string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_QR);
            if (! CierreJornadaProcesoMedioSupport::esMedioWaitryRedistribuibleAEfectivo($medioOrigen)) {
                continue;
            }

            $total = self::pesos((float) ($mov['total'] ?? 0));
            if ($total <= 0.0001) {
                continue;
            }

            if ($total <= $restanteSinFacturar + 0.0001) {
                $mov['medio_pago_planificado'] = CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $total],
                ];
                $asignadoSinFacturar += $total;
                $restanteSinFacturar = self::pesos($restanteSinFacturar - $total);
                self::acumularAsignadoEfectivoPorMedio($asignadoEfectivoPorMedioOrigen, $medioOrigen, $total);
                $ajustes[] = self::ajuste(
                    $mov,
                    self::tipoAjusteSinFacturarAEfectivo($medioOrigen, false),
                    $medioOrigen,
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    $total,
                );
            } else {
                [$montoEfectivo, $montoResto] = self::partesMixtoEfectivoMedio($total, $restanteSinFacturar);
                $mov['medio_pago_planificado'] = 'mixto';
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $montoEfectivo],
                    ['clave' => $medioOrigen, 'monto' => $montoResto],
                ];
                $asignadoSinFacturar += $montoEfectivo;
                $restanteSinFacturar = 0.;
                self::acumularAsignadoEfectivoPorMedio($asignadoEfectivoPorMedioOrigen, $medioOrigen, $montoEfectivo);
                $ajustes[] = self::ajuste(
                    $mov,
                    self::tipoAjusteSinFacturarAEfectivo($medioOrigen, true),
                    $medioOrigen,
                    'mixto',
                    $total,
                    $mov['medios_pago_planificados'],
                );
            }
            $out[$idx] = $mov;
        }
        unset($mov);

        foreach ($out as $idx => &$mov) {
            if ($restanteSinFacturar <= 0.0001) {
                break;
            }
            $grupo = (string) ($mov['grupo'] ?? '');
            if ($grupo !== CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM) {
                continue;
            }
            $medioOrigen = (string) ($mov['medio_waitry_clave'] ?? '');
            if (! CierreJornadaProcesoMedioSupport::esMedioWaitryRedistribuibleAEfectivo($medioOrigen)) {
                continue;
            }

            $total = self::pesos((float) ($mov['total'] ?? 0));
            if ($total <= 0.0001) {
                continue;
            }

            if ($total <= $restanteSinFacturar + 0.0001) {
                $mov['medio_pago_planificado'] = CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $total],
                ];
                $asignadoSinFacturar += $total;
                $restanteSinFacturar = self::pesos($restanteSinFacturar - $total);
                self::acumularAsignadoEfectivoPorMedio($asignadoEfectivoPorMedioOrigen, $medioOrigen, $total);
                $ajustes[] = self::ajuste(
                    $mov,
                    self::tipoAjusteTotemAEfectivo($medioOrigen, false),
                    $medioOrigen,
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    $total,
                );
            } else {
                [$montoEfectivo, $montoResto] = self::partesMixtoEfectivoMedio($total, $restanteSinFacturar);
                $mov['medio_pago_planificado'] = 'mixto';
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $montoEfectivo],
                    ['clave' => $medioOrigen, 'monto' => $montoResto],
                ];
                $asignadoSinFacturar += $montoEfectivo;
                $restanteSinFacturar = 0.;
                self::acumularAsignadoEfectivoPorMedio($asignadoEfectivoPorMedioOrigen, $medioOrigen, $montoEfectivo);
                $ajustes[] = self::ajuste(
                    $mov,
                    self::tipoAjusteTotemAEfectivo($medioOrigen, true),
                    $medioOrigen,
                    'mixto',
                    $total,
                    $mov['medios_pago_planificados'],
                );
            }
            $out[$idx] = $mov;
        }
        unset($mov);

        $cuposRestantes = $asignadoEfectivoPorMedioOrigen;
        $compensadoPorMedio = self::cuposCompensacionVacios();
        $asignadoFacturadoTotal = 0.0;

        $anitaCompensacion = self::armarPoolCompensacionAnita($out, $facturasAnitaEfectivo);

        foreach ($anitaCompensacion as $idx => &$mov) {
            if (self::totalCupoCompensacion($cuposRestantes) <= 0.0001) {
                break;
            }
            if ((string) ($mov['medio_anita_clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                continue;
            }

            $total = self::pesos((float) ($mov['total'] ?? 0));
            if ($total <= 0.0001) {
                continue;
            }

            [$planificados, $compensadoEnFactura] = self::planificarCompensacionAnita($total, $cuposRestantes);
            $montoCompensado = self::pesos(
                ($compensadoEnFactura[CierreJornadaProcesoMedioSupport::CLAVE_QR] ?? 0.0)
                + ($compensadoEnFactura[CierreJornadaProcesoMedioSupport::CLAVE_MP] ?? 0.0),
            );
            if ($montoCompensado <= 0.0001) {
                continue;
            }

            $soloUnMedio = count($planificados) === 1;
            $mov['medio_pago_planificado'] = $soloUnMedio
                ? (string) $planificados[0]['clave']
                : 'mixto';
            $mov['medios_pago_planificados'] = $planificados;
            // Marca durable: este movimiento lleva una compensación efectivo->QR/MP de la
            // redistribución. El overlay se apoya en esta bandera (no en medio_anita_clave,
            // que clasificar() re-deriva desde la cuentacaja real y podría borrar el 'efectivo').
            $mov['anita_compensacion_redistribucion'] = true;

            foreach ($compensadoEnFactura as $medio => $monto) {
                $compensadoPorMedio[$medio] = self::pesos(($compensadoPorMedio[$medio] ?? 0.0) + $monto);
            }
            $asignadoFacturadoTotal += $montoCompensado;

            $ajustes[] = self::ajuste(
                $mov,
                self::tipoAjusteCompensacionAnita($planificados),
                CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                $soloUnMedio ? (string) $planificados[0]['clave'] : 'mixto',
                $total,
                $soloUnMedio ? null : $planificados,
            );
            $anitaCompensacion[$idx] = $mov;
        }
        unset($mov);

        $out = self::fusionarCompensacionAnitaEnMovimientos($out, $anitaCompensacion);

        $asignadoSinFacturar = self::pesos($asignadoSinFacturar);
        $asignadoFacturadoTotal = self::pesos($asignadoFacturadoTotal);
        $compensadoQr = self::pesos($compensadoPorMedio[CierreJornadaProcesoMedioSupport::CLAVE_QR] ?? 0.0);
        $compensadoMp = self::pesos($compensadoPorMedio[CierreJornadaProcesoMedioSupport::CLAVE_MP] ?? 0.0);
        $origenQr = self::pesos($asignadoEfectivoPorMedioOrigen[CierreJornadaProcesoMedioSupport::CLAVE_QR] ?? 0.0);
        $origenMp = self::pesos($asignadoEfectivoPorMedioOrigen[CierreJornadaProcesoMedioSupport::CLAVE_MP] ?? 0.0);

        return [
            'movimientos' => $out,
            'objetivo_importe' => $objetivo,
            'porcentaje' => round($porcentaje, 4),
            'asignado_sin_facturar_a_efectivo' => $asignadoSinFacturar,
            'asignado_efectivo_por_medio_origen' => [
                CierreJornadaProcesoMedioSupport::CLAVE_QR => $origenQr,
                CierreJornadaProcesoMedioSupport::CLAVE_MP => $origenMp,
            ],
            'asignado_facturado_efectivo_a_qr' => $compensadoQr,
            'asignado_facturado_efectivo_a_mp' => $compensadoMp,
            'asignado_facturado_efectivo_compensacion' => $asignadoFacturadoTotal,
            'asignado_facturado_efectivo_por_medio' => [
                CierreJornadaProcesoMedioSupport::CLAVE_QR => $compensadoQr,
                CierreJornadaProcesoMedioSupport::CLAVE_MP => $compensadoMp,
            ],
            'cuadre_qr_z_ok' => self::cuadreCompensacionOk(
                $asignadoSinFacturar,
                $asignadoFacturadoTotal,
                $origenQr,
                $compensadoQr,
                $origenMp,
                $compensadoMp,
            ),
            'ajustes' => $ajustes,
        ];
    }

    public static function objetivoDesdePorcentaje(float $totalFacturacion, float $porcentaje): float
    {
        return self::pesos(max(0., $totalFacturacion) * max(0., $porcentaje) / 100.);
    }

    /**
     * Total Waitry sin facturar elegible para pasar a efectivo (QR/Totalcoin + MP/Posnet).
     *
     * @param  list<array<string, mixed>>  $movimientos  Clasificación sin redistribución aplicada
     */
    public static function totalSinFacturarRecodificable(array $movimientos): float
    {
        $total = 0.0;

        foreach ($movimientos as $mov) {
            if (($mov['grupo'] ?? '') !== CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR) {
                continue;
            }

            $medioOrigen = (string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_QR);
            if (! CierreJornadaProcesoMedioSupport::esMedioWaitryRedistribuibleAEfectivo($medioOrigen)) {
                continue;
            }

            $importe = self::pesos((float) ($mov['total'] ?? 0));
            if ($importe <= 0.0001) {
                continue;
            }

            $total += $importe;
        }

        return self::pesos($total);
    }

    /**
     * Porcentaje máximo sobre facturado Anita sin exceder lo recodificable en Waitry sin facturar.
     */
    public static function porcentajeMaximoSobreFacturacion(float $totalFacturacion, float $totalRecodificable): float
    {
        if ($totalFacturacion <= 0.0001 || $totalRecodificable <= 0.0001) {
            return 0.0;
        }

        return round(min(100.0, $totalRecodificable / max(0., $totalFacturacion) * 100.), 4);
    }

    /**
     * % a aplicar al 3er asiento: objetivo de la empresa (p. ej. 25 %) limitado al disponible recodificable.
     */
    public static function porcentajeAplicar(float $objetivo, float $maximoRecodificacion): float
    {
        $objetivo = round(max(0., min(100., $objetivo)), 4);
        $maximo = round(max(0., min(100., $maximoRecodificacion)), 4);
        if ($objetivo <= 0.0001 || $maximo <= 0.0001) {
            return 0.0;
        }

        return round(min($objetivo, $maximo), 4);
    }

    /**
     * Impide que el objetivo del % supere el total Waitry sin facturar recodificable (QR/MP).
     *
     * @throws InvalidArgumentException
     */
    public static function validarPorcentajeNoExcedeRecodificable(
        float $totalFacturacion,
        float $porcentaje,
        float $totalRecodificable,
    ): void {
        if ($porcentaje <= 0.0001) {
            return;
        }

        $objetivo = self::objetivoDesdePorcentaje($totalFacturacion, $porcentaje);
        if ($objetivo <= $totalRecodificable + 0.0001) {
            return;
        }

        $maxPct = self::porcentajeMaximoSobreFacturacion($totalFacturacion, $totalRecodificable);

        throw new InvalidArgumentException(sprintf(
            'El %.4g%% implica recodificar $ %s a efectivo, pero Waitry sin facturar recodificable (QR/Totalcoin + MP) suma $ %s. '
            .'Use como máximo %.4g%% para no dejar negativo el pendiente QR/MP a facturar.',
            $porcentaje,
            number_format($objetivo, 0, ',', '.'),
            number_format($totalRecodificable, 0, ',', '.'),
            $maxPct,
        ));
    }

    /**
     * Importes en pesos sin centavos (redondeo half-up).
     */
    public static function pesos(float $monto): float
    {
        return round($monto, 0);
    }

    /**
     * Reparte una comanda entre efectivo (cupo restante) y el medio original, todo en pesos enteros.
     *
     * @return array{0: float, 1: float} [montoEfectivo, montoMedioOrigen]
     */
    public static function partesMixtoEfectivoMedio(float $totalComanda, float $restanteEfectivo): array
    {
        $total = self::pesos($totalComanda);
        $montoEfectivo = self::pesos(min(max(0., $restanteEfectivo), $total));
        $montoMedio = $total - $montoEfectivo;

        return [$montoEfectivo, $montoMedio];
    }

    /** @deprecated Use partesMixtoEfectivoMedio */
    public static function partesMixtoEfectivoQr(float $totalComanda, float $restanteEfectivo): array
    {
        return self::partesMixtoEfectivoMedio($totalComanda, $restanteEfectivo);
    }

    /**
     * @return array<string, float>
     */
    private static function cuposCompensacionVacios(): array
    {
        return [
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 0.0,
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 0.0,
        ];
    }

    /**
     * @param  array<string, float>  $cupos
     */
    private static function acumularAsignadoEfectivoPorMedio(array &$cupos, string $medioOrigen, float $montoEfectivo): void
    {
        if ($montoEfectivo <= 0.0001) {
            return;
        }
        if (! isset($cupos[$medioOrigen])) {
            $cupos[$medioOrigen] = 0.0;
        }
        $cupos[$medioOrigen] = self::pesos($cupos[$medioOrigen] + $montoEfectivo);
    }

    /**
     * @param  array<string, float>  $cuposRestantes
     * @return array{0: list<array{clave:string,monto:float}>, 1: array<string, float>}
     */
    private static function planificarCompensacionAnita(float $totalFactura, array &$cuposRestantes): array
    {
        $total = self::pesos($totalFactura);
        $planificados = [];
        $compensado = self::cuposCompensacionVacios();
        $restante = $total;

        foreach (CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar() as $medioDestino) {
            $cupo = self::pesos((float) ($cuposRestantes[$medioDestino] ?? 0.0));
            if ($cupo <= 0.0001 || $restante <= 0.0001) {
                continue;
            }
            $monto = self::pesos(min($restante, $cupo));
            if ($monto <= 0.0001) {
                continue;
            }
            $planificados[] = ['clave' => $medioDestino, 'monto' => $monto];
            $compensado[$medioDestino] = self::pesos(($compensado[$medioDestino] ?? 0.0) + $monto);
            $cuposRestantes[$medioDestino] = self::pesos($cupo - $monto);
            $restante = self::pesos($restante - $monto);
        }

        if ($restante > 0.0001) {
            $planificados[] = [
                'clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'monto' => $restante,
            ];
        }

        return [$planificados, $compensado];
    }

    /**
     * @param  list<array{clave:string,monto:float}>  $planificados
     */
    private static function tipoAjusteCompensacionAnita(array $planificados): string
    {
        $destinos = array_values(array_filter(
            $planificados,
            fn (array $p) => (string) ($p['clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
        ));

        if (count($destinos) === 1 && count($planificados) === 1) {
            return match ((string) $destinos[0]['clave']) {
                CierreJornadaProcesoMedioSupport::CLAVE_QR => 'facturado_efectivo_a_qr',
                CierreJornadaProcesoMedioSupport::CLAVE_MP => 'facturado_efectivo_a_mp',
                default => 'facturado_efectivo_mixto',
            };
        }

        return 'facturado_efectivo_mixto';
    }

    /**
     * @param  array<string, float>  $cupos
     */
    private static function totalCupoCompensacion(array $cupos): float
    {
        return self::pesos(array_sum(array_map('floatval', $cupos)));
    }

    private static function cuadreCompensacionOk(
        float $asignadoEfectivoTotal,
        float $compensadoTotal,
        float $origenQr,
        float $compensadoQr,
        float $origenMp,
        float $compensadoMp,
    ): bool {
        if (abs($asignadoEfectivoTotal - $compensadoTotal) > 0.0001) {
            return false;
        }

        return abs($origenQr - $compensadoQr) <= 0.0001
            && abs($origenMp - $compensadoMp) <= 0.0001;
    }

    private static function tipoAjusteSinFacturarAEfectivo(string $medioOrigen, bool $mixto): string
    {
        $prefijo = $medioOrigen === CierreJornadaProcesoMedioSupport::CLAVE_MP
            ? 'sin_facturar_mp'
            : 'sin_facturar_qr';

        return $prefijo.($mixto ? '_mixto' : '_a_efectivo');
    }

    private static function tipoAjusteTotemAEfectivo(string $medioOrigen, bool $mixto): string
    {
        $prefijo = $medioOrigen === CierreJornadaProcesoMedioSupport::CLAVE_MP
            ? 'facturado_totem_mp'
            : 'facturado_totem_qr';

        return $prefijo.($mixto ? '_mixto' : '_a_efectivo');
    }

    /**
     * Facturas Anita en efectivo: emisiones jornada + facturadas en tramo Waitry (comportamiento original).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<array<string, mixed>>  $facturasAnitaEfectivo
     * @return list<array<string, mixed>>
     */
    private static function armarPoolCompensacionAnita(array $movimientos, array $facturasAnitaEfectivo): array
    {
        $pool = [];
        $indicesPorVenta = [];

        foreach ($facturasAnitaEfectivo as $mov) {
            $copia = $mov;
            $copia['medio_pago_planificado'] = $copia['medio_pago_planificado'] ?? null;
            $copia['medios_pago_planificados'] = $copia['medios_pago_planificados'] ?? null;
            $ventaId = (int) ($copia['venta_id'] ?? 0);
            if ($ventaId > 0) {
                $indicesPorVenta[$ventaId] = count($pool);
            }
            $pool[] = $copia;
        }

        foreach ($movimientos as $mov) {
            if ((string) ($mov['grupo'] ?? '') !== CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL) {
                continue;
            }
            if ((string) ($mov['medio_anita_clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                continue;
            }

            $ventaId = (int) ($mov['venta_id'] ?? 0);
            if ($ventaId > 0 && isset($indicesPorVenta[$ventaId])) {
                continue;
            }

            $copia = $mov;
            $copia['medio_pago_planificado'] = $copia['medio_pago_planificado'] ?? null;
            $copia['medios_pago_planificados'] = $copia['medios_pago_planificados'] ?? null;
            if ($ventaId > 0) {
                $indicesPorVenta[$ventaId] = count($pool);
            }
            $pool[] = $copia;
        }

        usort($pool, static fn (array $a, array $b) => ($a['waitry_order_id'] ?? 0) <=> ($b['waitry_order_id'] ?? 0));

        return $pool;
    }

    /**
     * Propaga medios planificados de Anita a movimientos Waitry (misma venta) o agrega filas solo-Anita.
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<array<string, mixed>>  $anitaCompensacion
     * @return list<array<string, mixed>>
     */
    private static function fusionarCompensacionAnitaEnMovimientos(array $movimientos, array $anitaCompensacion): array
    {
        if ($anitaCompensacion === []) {
            return $movimientos;
        }

        $porVenta = [];
        $porWaitry = [];
        foreach ($anitaCompensacion as $mov) {
            if (empty($mov['medios_pago_planificados'])) {
                continue;
            }
            $ventaId = (int) ($mov['venta_id'] ?? 0);
            if ($ventaId > 0) {
                $porVenta[$ventaId] = $mov;
            }
            $waitryId = (int) ($mov['waitry_order_id'] ?? 0);
            if ($waitryId > 0) {
                $porWaitry[$waitryId] = $mov;
            }
        }

        $fusionados = [];
        foreach ($movimientos as $idx => $mov) {
            $anita = null;
            $ventaId = (int) ($mov['venta_id'] ?? 0);
            if ($ventaId > 0 && isset($porVenta[$ventaId])) {
                $anita = $porVenta[$ventaId];
                $fusionados['v:'.$ventaId] = true;
            } else {
                $waitryId = (int) ($mov['waitry_order_id'] ?? 0);
                if ($waitryId > 0 && isset($porWaitry[$waitryId])) {
                    $anita = $porWaitry[$waitryId];
                    $fusionados['w:'.$waitryId] = true;
                }
            }

            if ($anita === null) {
                continue;
            }

            $movimientos[$idx]['medio_pago_planificado'] = $anita['medio_pago_planificado'] ?? null;
            $movimientos[$idx]['medios_pago_planificados'] = $anita['medios_pago_planificados'];
            $movimientos[$idx]['medio_anita_clave'] = CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
            // La compensación quedó fusionada sobre este movimiento: propagar la marca durable
            // para que el overlay la aplique aunque clasificar() cambie luego medio_anita_clave.
            $movimientos[$idx]['anita_compensacion_redistribucion'] = true;
        }

        foreach ($anitaCompensacion as $mov) {
            if (empty($mov['medios_pago_planificados'])) {
                continue;
            }
            $ventaId = (int) ($mov['venta_id'] ?? 0);
            $waitryId = (int) ($mov['waitry_order_id'] ?? 0);
            if ($ventaId > 0 && isset($fusionados['v:'.$ventaId])) {
                continue;
            }
            if ($waitryId > 0 && isset($fusionados['w:'.$waitryId])) {
                continue;
            }
            $movimientos[] = $mov;
        }

        return $movimientos;
    }

    /**
     * @param  array<string, mixed>  $mov
     * @param  list<array{clave:string,monto:float}>|null  $detalleMixto
     * @return array<string, mixed>
     */
    private static function ajuste(
        array $mov,
        string $tipo,
        string $desde,
        string $hacia,
        float $monto,
        ?array $detalleMixto = null,
    ): array {
        return [
            'tipo' => $tipo,
            'waitry_order_id' => $mov['waitry_order_id'] ?? null,
            'venta_codigo' => $mov['venta_codigo'] ?? '',
            'desde' => $desde,
            'hacia' => $hacia,
            'monto' => self::pesos($monto),
            'detalle_mixto' => $detalleMixto,
        ];
    }
}
