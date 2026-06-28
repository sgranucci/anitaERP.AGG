<?php

namespace App\Support\Caja;

use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Imputación contable de cheques propios: banco directo vs cheques diferidos (posdatados).
 */
final class ChequePropioImputacionSupport
{
    public static function usaImputacionDiferidos(): bool
    {
        return (bool) config('caja.cheque_propio_imputacion_diferidos_habilitado');
    }

    public static function esPosdatado(string $fechaOperacion, string $fechaPago): bool
    {
        return strcmp($fechaPago, $fechaOperacion) > 0;
    }

    public static function resolverCuentacontableIdEmitido(
        int $empresaId,
        int $cuentacajaId,
        string $fechaOperacion,
        string $fechaPago,
        CuentacajaRepositoryInterface $cuentacajaRepository,
        CuentacontableRepositoryInterface $cuentacontableRepository
    ): ?int {
        $cuentacaja = $cuentacajaRepository->find($cuentacajaId);
        if ($cuentacaja === null) {
            return null;
        }

        if (self::usaImputacionDiferidos() && self::esPosdatado($fechaOperacion, $fechaPago)) {
            $id = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS);
            if ($id !== null && $id > 0) {
                return $id;
            }

            $codigo = (string) config('caja.cheques_diferidos_cuenta_codigo');
            $cuenta = $cuentacontableRepository->findPorCodigo($empresaId, $codigo);

            return $cuenta?->id;
        }

        return (int) ($cuentacaja->cuentacontable_id ?? 0) ?: null;
    }

    public static function resolverCuentacontableIdValoresADepositar(
        int $empresaId,
        CuentacontableRepositoryInterface $cuentacontableRepository
    ): ?int {
        $id = CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::CAJA_VALORES_A_DEPOSITAR);
        if ($id !== null && $id > 0) {
            return $id;
        }

        $mapaLegacy = config('cobranza.VALORES_A_DEPOSITAR');
        if (is_array($mapaLegacy) && isset($mapaLegacy[(string) $empresaId])) {
            $cuenta = $cuentacontableRepository->findPorCodigo($empresaId, (string) $mapaLegacy[(string) $empresaId]);
            if ($cuenta !== null) {
                return $cuenta->id;
            }
        }

        $codigo = (string) config('caja.valores_a_depositar_cuenta_codigo');
        $cuenta = $cuentacontableRepository->findPorCodigo($empresaId, $codigo);

        return $cuenta?->id;
    }

    /** Estado inicial al emitir cheque propio. */
    public static function estadoInicialEmitido(string $fechaOperacion, string $fechaPago): string
    {
        if (self::usaImputacionDiferidos() && self::esPosdatado($fechaOperacion, $fechaPago)) {
            return ' ';
        }

        return '*';
    }
}
