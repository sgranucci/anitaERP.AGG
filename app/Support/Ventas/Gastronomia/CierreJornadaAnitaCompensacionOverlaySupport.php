<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;

/**
 * Refleja en cuadro y asiento 2 la compensación planificada (efectivo → QR/MP) sin persistir cobranza.
 */
final class CierreJornadaAnitaCompensacionOverlaySupport
{
    /**
     * @param  array<string, mixed>  $totalesAnita
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>
     */
    public static function aplicarTotalesAnita(array $totalesAnita, array $movimientos, int $empresaId): array
    {
        $compensaciones = self::compensacionesPorVenta($movimientos);
        if ($compensaciones === []) {
            return $totalesAnita;
        }

        $fila = is_array($totalesAnita['anita_jornada'] ?? null)
            ? $totalesAnita['anita_jornada']
            : $totalesAnita;

        $fila = self::aplicarTrasladosEnFilaCuadro($fila, $compensaciones, $empresaId);
        if (($fila['etiqueta'] ?? '') !== '') {
            $fila['etiqueta'] = (string) $fila['etiqueta'].' · compensación planificada';
        }
        $totalesAnita['anita_jornada'] = $fila;

        return $totalesAnita;
    }

    /**
     * @param  array<string, mixed>  $datosAsiento
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>
     */
    public static function aplicarDatosAsiento(array $datosAsiento, array $movimientos, int $empresaId): array
    {
        $compensaciones = self::compensacionesPorVenta($movimientos);
        if ($compensaciones === []) {
            return $datosAsiento;
        }

        /** @var array<int, array{concepto:string,cuenta_id:int,debe:float}> $debePorCuenta */
        $debePorCuenta = $datosAsiento['debe_por_cuenta'] ?? [];

        foreach ($compensaciones as $comp) {
            foreach ($comp['traslados'] as $traslado) {
                $monto = round((float) ($traslado['monto'] ?? 0), 2);
                if ($monto <= 0.0001) {
                    continue;
                }
                $desdeId = self::cuentacajaIdPorClaveMedio(
                    (string) ($traslado['desde'] ?? CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO),
                    $empresaId,
                );
                $haciaId = self::cuentacajaIdPorClaveMedio((string) ($traslado['hacia'] ?? ''), $empresaId);
                if ($desdeId !== null && $desdeId > 0) {
                    $debePorCuenta = self::ajustarDebeCuenta($debePorCuenta, $desdeId, -$monto);
                }
                if ($haciaId !== null && $haciaId > 0) {
                    $label = self::labelCuentaMedio((string) $traslado['hacia']);
                    $debePorCuenta = self::ajustarDebeCuenta($debePorCuenta, $haciaId, $monto, $label);
                }
            }
        }

        $datosAsiento['debe_por_cuenta'] = self::filtrarDebePorCuentaCero($debePorCuenta);

        return $datosAsiento;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array{venta_id:int,traslados:list<array{desde:string,hacia:string,monto:float}>}>
     */
    public static function compensacionesPorVenta(array $movimientos): array
    {
        $porVenta = [];

        foreach ($movimientos as $mov) {
            $plan = $mov['medios_pago_planificados'] ?? null;
            if (! is_array($plan) || $plan === []) {
                continue;
            }
            // Identificar la compensación por la marca durable que puso la redistribución.
            // Antes se filtraba por medio_anita_clave == 'efectivo', pero clasificar() re-deriva
            // esa clave desde la cuentacaja real y borra el 'efectivo' que había puesto la fusión;
            // eso hacía que se perdieran traslados y el asiento MP quedara por debajo del Z.
            if (empty($mov['anita_compensacion_redistribucion'])) {
                continue;
            }

            $ventaId = (int) ($mov['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                continue;
            }

            $traslados = self::trasladosDesdePlan($plan);
            if ($traslados === []) {
                continue;
            }

            $porVenta[$ventaId] = [
                'venta_id' => $ventaId,
                'traslados' => $traslados,
            ];
        }

        return array_values($porVenta);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<array{venta_id:int,traslados:list<array{desde:string,hacia:string,monto:float}>}>  $compensaciones
     * @return array<string, mixed>
     */
    private static function aplicarTrasladosEnFilaCuadro(array $fila, array $compensaciones, int $empresaId): array
    {
        if (! isset($fila['por_cuenta']) || ! is_array($fila['por_cuenta'])) {
            $fila['por_cuenta'] = [];
        }

        foreach ($compensaciones as $comp) {
            foreach ($comp['traslados'] as $traslado) {
                $monto = round((float) ($traslado['monto'] ?? 0), 2);
                if ($monto <= 0.0001) {
                    continue;
                }
                $hacia = (string) ($traslado['hacia'] ?? '');
                $efectivoId = self::cuentacajaIdPorClaveMedio(
                    CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                    $empresaId,
                );
                $haciaId = self::cuentacajaIdPorClaveMedio($hacia, $empresaId);
                if ($efectivoId === null || $efectivoId <= 0 || $haciaId === null || $haciaId <= 0) {
                    continue;
                }

                $col = CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio($hacia);

                $fila['efectivo'] = round((float) ($fila['efectivo'] ?? 0) - $monto, 2);
                $fila[$col] = round((float) ($fila[$col] ?? 0) + $monto, 2);

                $fila['por_cuenta'][$efectivoId] = round((float) ($fila['por_cuenta'][$efectivoId] ?? 0) - $monto, 2);
                $fila['por_cuenta'][$haciaId] = round((float) ($fila['por_cuenta'][$haciaId] ?? 0) + $monto, 2);
            }
        }

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
     * @param  list<array{clave:string,monto:float}>  $plan
     * @return list<array{desde:string,hacia:string,monto:float}>
     */
    private static function trasladosDesdePlan(array $plan): array
    {
        $out = [];
        foreach ($plan as $parte) {
            $clave = (string) ($parte['clave'] ?? '');
            $monto = round((float) ($parte['monto'] ?? 0), 2);
            if ($monto <= 0.0001 || $clave === CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO) {
                continue;
            }
            if (! in_array($clave, CierreJornadaProcesoMedioSupport::clavesMedioFacturableSinFacturar(), true)) {
                continue;
            }
            $out[] = [
                'desde' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO,
                'hacia' => $clave,
                'monto' => $monto,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{concepto:string,cuenta_id:int,debe:float}>  $debePorCuenta
     * @return array<int, array{concepto:string,cuenta_id:int,debe:float}>
     */
    private static function ajustarDebeCuenta(
        array $debePorCuenta,
        int $cuentaId,
        float $delta,
        ?string $conceptoDefault = null,
    ): array {
        if (abs($delta) <= 0.0001) {
            return $debePorCuenta;
        }

        if (! isset($debePorCuenta[$cuentaId])) {
            $debePorCuenta[$cuentaId] = [
                'concepto' => 'Medio de cobro — '.($conceptoDefault ?? '#'.$cuentaId),
                'cuenta_id' => $cuentaId,
                'debe' => 0.,
            ];
        }

        $debePorCuenta[$cuentaId]['debe'] = round((float) $debePorCuenta[$cuentaId]['debe'] + $delta, 2);

        return $debePorCuenta;
    }

    /**
     * @param  array<int, array{concepto:string,cuenta_id:int,debe:float}>  $debePorCuenta
     * @return array<int, array{concepto:string,cuenta_id:int,debe:float}>
     */
    private static function filtrarDebePorCuentaCero(array $debePorCuenta): array
    {
        $out = [];
        foreach ($debePorCuenta as $ccId => $ln) {
            if (abs((float) ($ln['debe'] ?? 0)) <= 0.0001) {
                continue;
            }
            $out[(int) $ccId] = $ln;
        }

        return $out;
    }

    private static function cuentacajaIdPorClaveMedio(string $clave, int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId),
            CierreJornadaProcesoMedioSupport::CLAVE_QR => WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_TOTALCOIN,
                $empresaId,
            ),
            CierreJornadaProcesoMedioSupport::CLAVE_MP => WaitryMedioPagoCuentacajaSupport::cuentacajaIdPorTipo(
                WaitryMedioPagoCuentacajaSupport::TIPO_MERCADOPAGO,
                $empresaId,
            ),
            default => null,
        };
    }

    private static function labelCuentaMedio(string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'QR (Totalcoin)',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'Mercado Pago',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'Efectivo',
            default => CierreJornadaProcesoMedioSupport::etiquetaClave($clave),
        };
    }
}
