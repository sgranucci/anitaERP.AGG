<?php

namespace App\Support\Caja;

/**
 * auxpag CHP (pago.c): axp_sucursal = nro de cheque, axp_sucursal_cob = empresa.
 */
final class ChequePropioAuxpagAnitaMapper
{
    /**
     * @return array{axp_sucursal: string, axp_sucursal_cob: string}
     */
    public static function sucursales(int $nroCheque, int $empresaAnita): array
    {
        return [
            'axp_sucursal' => (string) $nroCheque,
            'axp_sucursal_cob' => (string) ($empresaAnita > 0 ? $empresaAnita : 1),
        ];
    }

    /**
     * @param  array<string, mixed>|object  $anita
     * @return list<string>
     */
    public static function discrepancias(int $nroCheque, int $empresaAnita, array|object $anita): array
    {
        $a = is_array($anita) ? $anita : get_object_vars($anita);
        $esp = self::sucursales($nroCheque, $empresaAnita);
        $problemas = [];
        if ((string) ((int) ($a['axp_sucursal'] ?? 0)) !== $esp['axp_sucursal']) {
            $problemas[] = 'axp_sucursal Anita '.($a['axp_sucursal'] ?? '?').' ≠ nro cheque '.$esp['axp_sucursal'];
        }
        if ((string) ((int) ($a['axp_sucursal_cob'] ?? 0)) !== $esp['axp_sucursal_cob']) {
            $problemas[] = 'axp_sucursal_cob Anita '.($a['axp_sucursal_cob'] ?? '?').' ≠ empresa '.$esp['axp_sucursal_cob'];
        }

        return $problemas;
    }
}
