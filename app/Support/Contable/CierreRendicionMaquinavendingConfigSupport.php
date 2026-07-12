<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * Resuelve cuentas contables para el cierre contable de rendiciones vending.
 */
final class CierreRendicionMaquinavendingConfigSupport
{
    /**
     * @return array{
     *   cuenta_ventas_id: int,
     *   cuenta_ventas_kiosco_id: int,
     *   cuenta_diferencia_caja_id: int,
     *   cuenta_iva_id: int
     * }
     */
    public static function paraEmpresa(int $empresaId): array
    {
        $ivaId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::VENTAS_IVA_DEBITO_FISCAL,
        ) ?? 0);
        if ($ivaId <= 0) {
            $ivaId = self::resolverCuentaPorCodigosConfig(
                $empresaId,
                'iva_ventas.conciliacion.cuentas_iva_debito_por_empresa',
                214010009,
            );
        }

        $ventasKioscoId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::CIERRE_WAITRY_VENTAS_KIOSCO,
        ) ?? 0);

        return [
            'cuenta_ventas_id' => (int) (CuentaAutomaticaResolver::resolverId(
                $empresaId,
                CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS,
            ) ?? 0),
            'cuenta_ventas_kiosco_id' => $ventasKioscoId,
            'cuenta_diferencia_caja_id' => (int) (CuentaAutomaticaResolver::resolverId(
                $empresaId,
                CuentaAutomaticaClaves::CIERRE_VENDING_DIFERENCIA_CAJA,
            ) ?? 0),
            'cuenta_iva_id' => $ivaId,
        ];
    }

    /**
     * @return list<string>
     */
    public static function faltantes(array $cfg, int $empresaId): array
    {
        $faltantes = [];
        if ((int) ($cfg['cuenta_ventas_id'] ?? 0) <= 0) {
            $faltantes[] = 'Ventas vending (cuentas automáticas)';
        }
        if ((int) ($cfg['cuenta_diferencia_caja_id'] ?? 0) <= 0) {
            $faltantes[] = 'Diferencia de caja vending (cuentas automáticas)';
        }
        if ((int) ($cfg['cuenta_iva_id'] ?? 0) <= 0) {
            $faltantes[] = 'IVA débito fiscal (cuentas automáticas — Ventas IVA fiscal)';
        }

        return $faltantes;
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
}
