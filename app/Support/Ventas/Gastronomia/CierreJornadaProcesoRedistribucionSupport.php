<?php

namespace App\Support\Ventas\Gastronomia;

/**
 * Redistribución QR ↔ Efectivo según porcentaje sobre total facturado.
 *
 * 1) Cuentas Waitry sin facturar (QR): pasan a efectivo hasta alcanzar el importe objetivo.
 * 2) Facturas Anita TOTEM con medio real Waitry QR: parte a efectivo (mismo cupo; puente TOTEM).
 * 3) Facturas Anita en efectivo (medio real): pasan a QR hasta el mismo importe objetivo.
 */
final class CierreJornadaProcesoRedistribucionSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *   movimientos: list<array<string, mixed>>,
     *   objetivo_importe: float,
     *   asignado_sin_facturar_a_efectivo: float,
     *   asignado_facturado_efectivo_a_qr: float,
     *   ajustes: list<array<string, mixed>>
     * }
     */
    public static function aplicar(array $movimientos, float $totalFacturacion, float $porcentaje): array
    {
        $objetivo = round(max(0., $totalFacturacion) * max(0., $porcentaje) / 100., 2);
        $restanteSinFacturar = $objetivo;
        $restanteFacturado = $objetivo;
        $ajustes = [];
        $asignadoSinFacturar = 0.0;
        $asignadoFacturado = 0.0;

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

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            if ($total <= $restanteSinFacturar + 0.0001) {
                $mov['medio_pago_planificado'] = CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $total],
                ];
                $asignadoSinFacturar += $total;
                $restanteSinFacturar = round($restanteSinFacturar - $total, 2);
                $ajustes[] = self::ajuste(
                    $mov,
                    'sin_facturar_qr_a_efectivo',
                    CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    $total,
                );
            } else {
                $montoEfectivo = round($restanteSinFacturar, 2);
                $montoQr = round($total - $montoEfectivo, 2);
                $mov['medio_pago_planificado'] = 'mixto';
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $montoEfectivo],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => $montoQr],
                ];
                $asignadoSinFacturar += $montoEfectivo;
                $restanteSinFacturar = 0.;
                $ajustes[] = self::ajuste(
                    $mov,
                    'sin_facturar_qr_mixto',
                    CierreJornadaProcesoMedioSupport::CLAVE_QR,
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
            if ((string) ($mov['medio_waitry_clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_QR) {
                continue;
            }

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            if ($total <= $restanteSinFacturar + 0.0001) {
                $mov['medio_pago_planificado'] = CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO;
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $total],
                ];
                $asignadoSinFacturar += $total;
                $restanteSinFacturar = round($restanteSinFacturar - $total, 2);
                $ajustes[] = self::ajuste(
                    $mov,
                    'facturado_totem_qr_a_efectivo',
                    CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    $total,
                );
            } else {
                $montoEfectivo = round($restanteSinFacturar, 2);
                $montoQr = round($total - $montoEfectivo, 2);
                $mov['medio_pago_planificado'] = 'mixto';
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $montoEfectivo],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => $montoQr],
                ];
                $asignadoSinFacturar += $montoEfectivo;
                $restanteSinFacturar = 0.;
                $ajustes[] = self::ajuste(
                    $mov,
                    'facturado_totem_qr_mixto',
                    CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    'mixto',
                    $total,
                    $mov['medios_pago_planificados'],
                );
            }
            $out[$idx] = $mov;
        }
        unset($mov);

        foreach ($out as $idx => &$mov) {
            if ($restanteFacturado <= 0.0001) {
                break;
            }
            $grupo = (string) ($mov['grupo'] ?? '');
            if ($grupo !== CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL) {
                continue;
            }
            if ((string) ($mov['medio_anita_clave'] ?? '') !== CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                continue;
            }

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            if ($total <= $restanteFacturado + 0.0001) {
                $mov['medio_pago_planificado'] = CierreJornadaProcesoMedioSupport::CLAVE_QR;
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => $total],
                ];
                $asignadoFacturado += $total;
                $restanteFacturado = round($restanteFacturado - $total, 2);
                $ajustes[] = self::ajuste(
                    $mov,
                    'facturado_efectivo_a_qr',
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    $total,
                );
            } else {
                $montoQr = round($restanteFacturado, 2);
                $montoEfectivo = round($total - $montoQr, 2);
                $mov['medio_pago_planificado'] = 'mixto';
                $mov['medios_pago_planificados'] = [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => $montoQr],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => $montoEfectivo],
                ];
                $asignadoFacturado += $montoQr;
                $restanteFacturado = 0.;
                $ajustes[] = self::ajuste(
                    $mov,
                    'facturado_efectivo_mixto',
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    'mixto',
                    $total,
                    $mov['medios_pago_planificados'],
                );
            }
            $out[$idx] = $mov;
        }
        unset($mov);

        return [
            'movimientos' => $out,
            'objetivo_importe' => $objetivo,
            'porcentaje' => round($porcentaje, 4),
            'asignado_sin_facturar_a_efectivo' => round($asignadoSinFacturar, 2),
            'asignado_facturado_efectivo_a_qr' => round($asignadoFacturado, 2),
            'ajustes' => $ajustes,
        ];
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
            'monto' => round($monto, 2),
            'detalle_mixto' => $detalleMixto,
        ];
    }
}
