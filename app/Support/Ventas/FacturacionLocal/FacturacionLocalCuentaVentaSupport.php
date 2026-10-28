<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Cuenta de ingreso de un comprobante emitido en un punto de venta de local.
 * Manda Cuentas automáticas (facturacion_local.ventas). Si está vacía, la del local.
 * El calzado del maestro queda en F-VENTAS y no se usa en estos comprobantes.
 */
final class FacturacionLocalCuentaVentaSupport
{
    /**
     * La NC varias imputa el neto a L-notas de crédito, no a L-VENTAS.
     * La contrapartida es la cuenta de valores, no el efectivo del local.
     */
    public const FLAG_IMPUTAR_NOTAS_CREDITO = 'imputar_cuenta_notas_credito_local';

    public const FLAG_CONTRAPARTIDA_CUENTA = 'contrapartida_cuentacontable_id';

    public static function cuentaIdParaPuntoventa(int $empresaId, int $puntoventaId): int
    {
        if ($empresaId <= 0 || $puntoventaId <= 0) {
            return 0;
        }

        $localId = FacturacionLocalEmisionVinculoSupport::mapaLocalPorPuntoventa()[$puntoventaId] ?? 0;
        if ($localId <= 0) {
            return 0;
        }

        $automatica = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::FACTURACION_LOCAL_VENTAS
        ) ?? 0);
        if ($automatica > 0) {
            return $automatica;
        }

        return (int) (LocalVenta::query()->whereKey($localId)->value('cuentacontable_venta_id') ?: 0);
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     * @return list<array<string, mixed>>
     */
    public static function aplicarSiCorresponde(array $dataFactura, int $empresaId, int $puntoventaId): array
    {
        $cuentaId = self::cuentaIdParaPuntoventa($empresaId, $puntoventaId);
        if ($cuentaId <= 0) {
            return $dataFactura;
        }

        foreach ($dataFactura as &$item) {
            $item['cuentacontable_id'] = $cuentaId;
        }
        unset($item);

        return $dataFactura;
    }

    public static function cuentaNotasCreditoId(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::FACTURACION_LOCAL_NOTAS_CREDITO
        ) ?? 0);
    }

    public static function cuentaValoresId(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::FACTURACION_LOCAL_VALORES
        ) ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $dataFactura
     * @return list<array<string, mixed>>
     */
    public static function aplicarNotasCredito(array $dataFactura, int $empresaId): array
    {
        $cuentaId = self::cuentaNotasCreditoId($empresaId);
        if ($cuentaId <= 0) {
            return $dataFactura;
        }

        foreach ($dataFactura as &$item) {
            $item['cuentacontable_id'] = $cuentaId;
        }
        unset($item);

        return $dataFactura;
    }
}
