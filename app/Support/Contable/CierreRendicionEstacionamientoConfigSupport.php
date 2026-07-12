<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * Resuelve cuentas contables para el cierre contable de rendiciones estacionamiento.
 */
final class CierreRendicionEstacionamientoConfigSupport
{
    /**
     * @return array{
     *   cuenta_ventas_id: int,
     *   cuenta_diferencia_caja_id: int,
     *   cuenta_iva_debito_id: int,
     *   cuenta_iva_credito_id: int
     * }
     */
    public static function paraEmpresa(int $empresaId): array
    {
        $ivaDebitoId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL,
        ) ?? 0);
        if ($ivaDebitoId <= 0) {
            $ivaDebitoId = self::resolverCuentaPorCodigosConfig(
                $empresaId,
                'iva_ventas.conciliacion.cuentas_iva_debito_por_empresa',
                214010009,
            );
        }

        $ivaCreditoId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::VENTAS_IVA_CREDITO_FISCAL,
        ) ?? 0);
        if ($ivaCreditoId <= 0) {
            $ivaCreditoId = self::resolverCuentaPorCodigosConfig(
                $empresaId,
                'iva_ventas.conciliacion.cuentas_iva_credito_por_empresa',
                114010011,
            );
        }

        return [
            'cuenta_ventas_id' => (int) (CuentaAutomaticaResolver::resolverId(
                $empresaId,
                CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_VENTAS,
            ) ?? 0),
            'cuenta_diferencia_caja_id' => (int) (CuentaAutomaticaResolver::resolverId(
                $empresaId,
                CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA,
            ) ?? 0),
            'cuenta_iva_debito_id' => $ivaDebitoId,
            'cuenta_iva_credito_id' => $ivaCreditoId,
        ];
    }

    /**
     * @return list<string>
     */
    public static function faltantes(array $cfg, int $empresaId): array
    {
        $faltantes = [];
        if ((int) ($cfg['cuenta_ventas_id'] ?? 0) <= 0) {
            $faltantes[] = 'Ventas estacionamiento (cuentas automáticas)';
        }
        if ((int) ($cfg['cuenta_diferencia_caja_id'] ?? 0) <= 0) {
            $faltantes[] = 'Diferencia de caja estacionamiento (cuentas automáticas)';
        }
        if ((int) ($cfg['cuenta_iva_debito_id'] ?? 0) <= 0) {
            $faltantes[] = 'IVA débito fiscal (cuentas automáticas — Ventas IVA fiscal)';
        }
        if ((int) ($cfg['cuenta_iva_credito_id'] ?? 0) <= 0) {
            $faltantes[] = 'IVA crédito fiscal (cuentas automáticas — Ventas IVA fiscal)';
        }

        return $faltantes;
    }

    private static function resolverCuentaPorCodigosConfig(
        int $empresaId,
        string $configKey,
        ?int $fallbackCodigo = null,
    ): int {
        /** @var array<int, list<int>> $map */
        $map = config($configKey, []);
        $codigos = $map[$empresaId] ?? $map[(string) $empresaId] ?? [];
        if ($codigos === [] && $fallbackCodigo !== null) {
            $codigos = [$fallbackCodigo];
        }

        foreach ($codigos as $codigo) {
            $id = (int) (Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', (string) $codigo)
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if ($fallbackCodigo !== null) {
            $id = (int) (Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', (string) $fallbackCodigo)
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public static function exigirCompleta(array $cfg, int $empresaId): void
    {
        $faltantes = self::faltantes($cfg, $empresaId);
        if ($faltantes !== []) {
            throw new RuntimeException(
                'Configure las cuentas contables antes de cerrar: '.implode('; ', $faltantes).'.',
            );
        }
    }
}
