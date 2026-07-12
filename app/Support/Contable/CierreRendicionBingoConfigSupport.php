<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * Cuentas contables del cierre bingo (impcont 451–460 vía cuentas automáticas).
 */
final class CierreRendicionBingoConfigSupport
{
    /**
     * @return array<string, int>
     */
    public static function paraEmpresa(int $empresaId): array
    {
        return [
            'cuenta_premio53_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_PREMIO53),
            'cuenta_efectivo_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_EFECTIVO),
            'cuenta_pozo_bingo_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_POZO_BINGO),
            'cuenta_pantalla_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_PANTALLA),
            'cuenta_otros_premios_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_OTROS_PREMIOS),
            'cuenta_diferencia_caja_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_DIFERENCIA_CAJA),
            'cuenta_ventas_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_VENTAS),
            'cuenta_pozo58_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_POZO58),
            'cuenta_pago_hospital_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_PAGO_HOSPITAL),
            'cuenta_cont_hospital_id' => self::resolverId($empresaId, CuentaAutomaticaClaves::CIERRE_BINGO_CONT_HOSPITAL),
        ];
    }

    public static function puntoventaFbi(int $empresaId): int
    {
        /** @var array<int, int> $map */
        $map = config('bingo.cierre_rendicion_contable.puntoventa_por_empresa', []);
        $pv = (int) ($map[$empresaId] ?? $map[(string) $empresaId] ?? 0);

        return $pv > 0 ? $pv : 39;
    }

    /**
     * @param  array<string, int>  $cfg
     * @return list<string>
     */
    public static function faltantes(array $cfg): array
    {
        $labels = [
            'cuenta_premio53_id' => 'Premio 53% (521050001)',
            'cuenta_efectivo_id' => 'Efectivo (111010001)',
            'cuenta_pozo_bingo_id' => 'Pozo bingo (211010006)',
            'cuenta_pantalla_id' => 'Premio pantalla (521040006)',
            'cuenta_otros_premios_id' => 'Otros premios (521040001)',
            'cuenta_diferencia_caja_id' => 'Diferencia de caja (521280004)',
            'cuenta_ventas_id' => 'Deudores venta bingo (411010001)',
            'cuenta_pozo58_id' => 'Pozo 58% (521030001)',
            'cuenta_pago_hospital_id' => 'Pago hospital (521020002)',
            'cuenta_cont_hospital_id' => 'Contrib. hospital (215010003)',
        ];

        $faltantes = [];
        foreach ($labels as $key => $label) {
            if ((int) ($cfg[$key] ?? 0) <= 0) {
                $faltantes[] = $label;
            }
        }

        return $faltantes;
    }

    /**
     * @param  array<string, int>  $cfg
     */
    public static function exigirCompleta(array $cfg, int $empresaId): void
    {
        $faltantes = self::faltantes($cfg);
        if ($faltantes !== []) {
            throw new RuntimeException(
                'Faltan cuentas automáticas de cierre bingo para empresa #'.$empresaId.': '
                .implode('; ', $faltantes),
            );
        }
    }

    public static function resolverCuentacontableIdPorCodigo(int $empresaId, int $codigoCuenta): int
    {
        if ($codigoCuenta <= 0) {
            return 0;
        }

        return (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', (string) $codigoCuenta)
            ->value('id') ?? 0);
    }

    private static function resolverId(int $empresaId, string $clave): int
    {
        return (int) (CuentaAutomaticaResolver::resolverId($empresaId, $clave) ?? 0);
    }
}
