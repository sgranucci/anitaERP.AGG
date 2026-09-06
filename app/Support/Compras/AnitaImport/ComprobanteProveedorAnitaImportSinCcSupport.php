<?php

namespace App\Support\Compras\AnitaImport;

/**
 * Ajustes de stats del import Anita cuando se pide documento sin cuenta corriente.
 */
final class ComprobanteProveedorAnitaImportSinCcSupport
{
    /**
     * @param  array<string, mixed>  $stats
     * @param  list<array<string, mixed>>  $pares
     * @return array<string, mixed>
     */
    public static function aplicarDryRun(array $stats, array $pares, bool $sinCuentaCorriente): array
    {
        $stats['sin_cuenta_corriente'] = $sinCuentaCorriente;
        if ($sinCuentaCorriente) {
            $stats['aplicaciones'] = 0;
            $stats['aplicaciones_pago_sintetico'] = 0;
            $stats['aplicaciones_omitidas'] = count($pares);
            $stats['cc'] = 0;
            $stats['adelantos_a_crear_documento'] = (int) ($stats['adelantos_a_crear'] ?? 0);
        } else {
            $stats['aplicaciones'] = count($pares);
            $stats['aplicaciones_pago_sintetico'] = count(array_filter(
                $pares,
                static fn (array $p) => ! empty($p['credito_es_pago'])
            ));
        }

        return $stats;
    }
}
