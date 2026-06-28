<?php

namespace App\Support\Caja;

use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;

/**
 * Arma líneas contables de cheques para ingreso/egreso (preview AJAX y grabación).
 */
final class IngresoEgresoChequeAsientoSupport
{
    /**
     * @param  array<int, object>  $datosEmitidos
     * @param  array<int, object>  $datosRecibidos
     * @param  array<int, object>  $datosReemplazos
     */
    public static function agregarLineasCheques(
        array &$asiento,
        array $datosEmitidos,
        array $datosRecibidos,
        array $datosReemplazos,
        int $signo,
        int $empresaId,
        string $fechaOperacion,
        CuentacajaRepositoryInterface $cuentacajaRepository,
        CuentacontableRepositoryInterface $cuentacontableRepository
    ): void {
        foreach ($datosEmitidos as $cheque) {
            $monto = (float) ($cheque->montos ?? 0);
            if ($monto <= 0) {
                continue;
            }

            $cuentacajaId = (int) ($cheque->cuentacaja_ids ?? 0);
            $fechaPago = (string) ($cheque->fechapagos ?? $fechaOperacion);
            $cuentacontableId = ChequePropioImputacionSupport::resolverCuentacontableIdEmitido(
                $empresaId,
                $cuentacajaId,
                $fechaOperacion,
                $fechaPago,
                $cuentacajaRepository,
                $cuentacontableRepository
            );

            if ($cuentacontableId === null) {
                continue;
            }

            $d_h = $signo > 0 ? 'H' : 'D';
            self::agregaCuenta(
                $asiento,
                $cuentacontableId,
                (int) ($cheque->moneda_ids ?? 1),
                (float) ($cheque->cotizaciones ?? 1),
                $d_h,
                $monto,
                $cuentacontableRepository
            );
        }

        $valoresId = ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
            $empresaId,
            $cuentacontableRepository
        );

        foreach ($datosRecibidos as $cheque) {
            $monto = (float) ($cheque->montos ?? 0);
            if ($monto <= 0 || $valoresId === null) {
                continue;
            }

            $d_h = $signo > 0 ? 'D' : 'H';
            self::agregaCuenta(
                $asiento,
                $valoresId,
                (int) ($cheque->moneda_ids ?? 1),
                (float) ($cheque->cotizaciones ?? 1),
                $d_h,
                $monto,
                $cuentacontableRepository
            );
        }

        foreach ($datosReemplazos as $par) {
            $montoAnulado = (float) ($par->monto_anulado ?? 0);
            $montoReemplazo = (float) ($par->monto_reemplazo ?? 0);
            if ($montoAnulado <= 0 && $montoReemplazo <= 0) {
                continue;
            }

            $origenAnulado = strtoupper((string) ($par->origen_anulado ?? 'R'));
            $origenReemplazo = strtoupper((string) ($par->origen_reemplazo ?? 'E'));

            if ($montoAnulado > 0) {
                self::imputarReemplazoLinea(
                    $asiento,
                    $origenAnulado,
                    $par,
                    $montoAnulado,
                    $signo,
                    $empresaId,
                    $fechaOperacion,
                    true,
                    $cuentacajaRepository,
                    $cuentacontableRepository
                );
            }

            if ($montoReemplazo > 0) {
                self::imputarReemplazoLinea(
                    $asiento,
                    $origenReemplazo,
                    $par,
                    $montoReemplazo,
                    $signo,
                    $empresaId,
                    $fechaOperacion,
                    false,
                    $cuentacajaRepository,
                    $cuentacontableRepository
                );
            }
        }
    }

    private static function imputarReemplazoLinea(
        array &$asiento,
        string $origen,
        object $par,
        float $monto,
        int $signo,
        int $empresaId,
        string $fechaOperacion,
        bool $esAnulacion,
        CuentacajaRepositoryInterface $cuentacajaRepository,
        CuentacontableRepositoryInterface $cuentacontableRepository
    ): void {
        if ($origen === 'E') {
            $cuentacajaId = (int) ($par->cuentacaja_reemplazo_ids ?? $par->cuentacaja_ids ?? 0);
            $fechaPago = (string) ($par->fechapago_reemplazo ?? $par->fechapagos ?? $fechaOperacion);
            $cuentacontableId = ChequePropioImputacionSupport::resolverCuentacontableIdEmitido(
                $empresaId,
                $cuentacajaId,
                $fechaOperacion,
                $fechaPago,
                $cuentacajaRepository,
                $cuentacontableRepository
            );
        } else {
            $cuentacontableId = ChequePropioImputacionSupport::resolverCuentacontableIdValoresADepositar(
                $empresaId,
                $cuentacontableRepository
            );
        }

        if ($cuentacontableId === null) {
            return;
        }

        $monedaId = (int) ($par->moneda_ids ?? $par->moneda_reemplazo_ids ?? 1);
        $cotizacion = (float) ($par->cotizaciones ?? $par->cotizacion_reemplazo ?? 1);

        if ($origen === 'E') {
            $d_h = ($signo > 0) ? ($esAnulacion ? 'D' : 'H') : ($esAnulacion ? 'H' : 'D');
        } else {
            $d_h = ($signo > 0) ? ($esAnulacion ? 'H' : 'D') : ($esAnulacion ? 'D' : 'H');
        }

        self::agregaCuenta($asiento, $cuentacontableId, $monedaId, $cotizacion, $d_h, $monto, $cuentacontableRepository);
    }

    private static function agregaCuenta(
        array &$asiento,
        int $cuentacontableId,
        int $monedaId,
        float $cotizacion,
        string $d_h,
        float $monto,
        CuentacontableRepositoryInterface $cuentacontableRepository
    ): void {
        $debe = $d_h === 'D' ? $monto : '';
        $haber = $d_h === 'H' ? $monto : '';

        for ($i = 0, $flExiste = false; $i < count($asiento) && ! $flExiste; $i++) {
            if ((int) $asiento[$i]['cuentacontable_id'] === $cuentacontableId
                && (int) $asiento[$i]['moneda_id'] === $monedaId
                && (float) $asiento[$i]['cotizacion'] === (float) $cotizacion) {
                $flExiste = true;
            }
        }

        if (! $flExiste) {
            $cuentacontable = $cuentacontableRepository->find($cuentacontableId);
            if ($cuentacontable === null) {
                return;
            }

            $asiento[] = [
                'cuentacontable_id' => $cuentacontableId,
                'codigo' => $cuentacontable->codigo,
                'nombre' => $cuentacontable->nombre,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'centrocosto_id' => 0,
                'debe' => $debe,
                'haber' => $haber,
                'd_h' => $d_h,
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];

            return;
        }

        if ($debe !== '') {
            $asiento[$i]['debe'] = (float) ($asiento[$i]['debe'] ?: 0) + $debe;
        }
        if ($haber !== '') {
            $asiento[$i]['haber'] = (float) ($asiento[$i]['haber'] ?: 0) + $haber;
        }
    }
}
