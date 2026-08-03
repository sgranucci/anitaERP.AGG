<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use RuntimeException;

/**
 * Cuentas contables del cierre rendición máquinas (slots).
 */
final class CierreRendicionMaquinaConfigSupport
{
    private const CLAVE_CAJA_PESOS = 'cierre_maquina.caja_pesos';

    private const CLAVE_TARJETAS = 'cierre_maquina.tarjetas';

    private const CLAVE_DOLARES = 'cierre_maquina.dolares';

    private const CLAVE_EUROS = 'cierre_maquina.euros';

    private const CLAVE_CAJA_TRANSITORIA = 'cierre_maquina.caja_transitoria';

    private const CLAVE_DIFERENCIA_CAJA = 'cierre_maquina.diferencia_caja';

    private const CLAVE_VENTAS_RULETA = 'cierre_maquina.ventas_ruleta';

    private const CLAVE_CANON_LOTERIA = 'cierre_maquina.canon_loteria';

    private const CLAVE_CONT_CANON_LOTERIA = 'cierre_maquina.cont_canon_loteria';

    private const CLAVE_CANON_HOSPITAL = 'cierre_maquina.canon_hospital';

    private const CLAVE_CONT_CANON_HOSPITAL = 'cierre_maquina.cont_canon_hospital';

    private const CLAVE_TICKET_PROM_DEBE = 'cierre_maquina.ticket_prom_debe';

    private const CLAVE_TICKET_PROM_HABER = 'cierre_maquina.ticket_prom_haber';

    private const CLAVE_GASTOS = 'cierre_maquina.gastos';

    private const CLAVE_VENTAS = 'cierre_maquina.ventas';

    private const CLAVE_TICKET_GASTRO = 'cierre_maquina.ticket_gastro';

    private const CLAVE_PODER_PUBLICO = 'cierre_maquina.poder_publico';

    private const CLAVE_IMPUESTO_ESP = 'cierre_maquina.impuesto_esp';

    private const CLAVE_FF_MAQUINA = 'cierre_maquina.ff_maquina';

    private const CLAVE_PARTIDA_PENDIENTE = 'cierre_maquina.partida_pendiente';

    private const CLAVE_CRIPTO = 'cierre_maquina.cripto';

    private const CLAVE_TOTALCOIN = 'cierre_maquina.totalcoin';

    private const CLAVE_MEP = 'cierre_maquina.mep';

    private const CLAVE_PAGO24 = 'cierre_maquina.pago24';

    /**
     * @return array<string, int>
     */
    public static function paraEmpresa(int $empresaId): array
    {
        return [
            'cuenta_caja_pesos_id' => self::resolverId($empresaId, self::CLAVE_CAJA_PESOS),
            'cuenta_tarjetas_id' => self::resolverId($empresaId, self::CLAVE_TARJETAS),
            'cuenta_dolares_id' => self::resolverId($empresaId, self::CLAVE_DOLARES),
            'cuenta_euros_id' => self::resolverId($empresaId, self::CLAVE_EUROS),
            'cuenta_caja_transitoria_id' => self::resolverId($empresaId, self::CLAVE_CAJA_TRANSITORIA),
            'cuenta_diferencia_caja_id' => self::resolverId($empresaId, self::CLAVE_DIFERENCIA_CAJA),
            'cuenta_ventas_ruleta_id' => self::resolverId($empresaId, self::CLAVE_VENTAS_RULETA),
            'cuenta_canon_loteria_id' => self::resolverId($empresaId, self::CLAVE_CANON_LOTERIA),
            'cuenta_cont_canon_loteria_id' => self::resolverId($empresaId, self::CLAVE_CONT_CANON_LOTERIA),
            'cuenta_canon_hospital_id' => self::resolverId($empresaId, self::CLAVE_CANON_HOSPITAL),
            'cuenta_cont_canon_hospital_id' => self::resolverId($empresaId, self::CLAVE_CONT_CANON_HOSPITAL),
            'cuenta_ticket_prom_debe_id' => self::resolverId($empresaId, self::CLAVE_TICKET_PROM_DEBE),
            'cuenta_ticket_prom_haber_id' => self::resolverId($empresaId, self::CLAVE_TICKET_PROM_HABER),
            'cuenta_gastos_id' => self::resolverId($empresaId, self::CLAVE_GASTOS),
            'cuenta_ventas_id' => self::resolverId($empresaId, self::CLAVE_VENTAS),
            'cuenta_ticket_gastro_id' => self::resolverId($empresaId, self::CLAVE_TICKET_GASTRO),
            'cuenta_poder_publico_id' => self::resolverId($empresaId, self::CLAVE_PODER_PUBLICO),
            'cuenta_impuesto_esp_id' => self::resolverId($empresaId, self::CLAVE_IMPUESTO_ESP),
            'cuenta_ff_maquina_id' => self::resolverId($empresaId, self::CLAVE_FF_MAQUINA),
            'cuenta_partida_pendiente_id' => self::resolverId($empresaId, self::CLAVE_PARTIDA_PENDIENTE),
            'cuenta_cripto_id' => self::resolverId($empresaId, self::CLAVE_CRIPTO),
            'cuenta_totalcoin_id' => self::resolverId($empresaId, self::CLAVE_TOTALCOIN),
            'cuenta_mep_id' => self::resolverId($empresaId, self::CLAVE_MEP),
            'cuenta_pago24_id' => self::resolverId($empresaId, self::CLAVE_PAGO24),
        ];
    }

    public static function puntoventaFsl(int $empresaId): int
    {
        /** @var array<int, int> $map */
        $map = config('rendicion_maquina_anita.cierre_rendicion_contable.puntoventa_por_empresa', []);
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
            'cuenta_caja_pesos_id' => 'Caja pesos',
            'cuenta_tarjetas_id' => 'Tarjetas',
            'cuenta_dolares_id' => 'Dólares',
            'cuenta_euros_id' => 'Euros',
            'cuenta_caja_transitoria_id' => 'Caja transitoria',
            'cuenta_diferencia_caja_id' => 'Diferencia de caja',
            'cuenta_ventas_ruleta_id' => 'Ventas ruleta',
            'cuenta_canon_loteria_id' => 'Canon lotería',
            'cuenta_cont_canon_loteria_id' => 'Contrib. canon lotería',
            'cuenta_canon_hospital_id' => 'Canon hospital',
            'cuenta_cont_canon_hospital_id' => 'Contrib. canon hospital',
            'cuenta_ticket_prom_debe_id' => 'Ticket promocional (debe)',
            'cuenta_ticket_prom_haber_id' => 'Ticket promocional (haber)',
            'cuenta_gastos_id' => 'Gastos',
            'cuenta_ventas_id' => 'Ventas máquinas',
            'cuenta_ticket_gastro_id' => 'Ticket gastronomía',
            'cuenta_poder_publico_id' => 'Poder público / pago diferido',
            'cuenta_impuesto_esp_id' => 'Impuesto especial',
            'cuenta_ff_maquina_id' => 'Fondo fijo máquinas',
            'cuenta_partida_pendiente_id' => 'Partida pendiente',
            'cuenta_cripto_id' => 'Cripto',
            'cuenta_totalcoin_id' => 'Totalcoin',
            'cuenta_mep_id' => 'MEP',
            'cuenta_pago24_id' => 'Pago 24',
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
                'Faltan cuentas automáticas de cierre máquinas para empresa #'.$empresaId.': '
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
