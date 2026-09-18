<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Evita duplicar en SICORE las retenciones que ya están en Anita (retmov/retimov)
 * cuando también existen en pagoproveedor_retencion (ERP).
 *
 * El ERP solo completa OPs que Anita no trajo (pagos nativos aún no reflejados en retmov).
 */
final class SicoreErpComplementoSupport
{
    /**
     * @param  list<array<string, mixed>>  $erp
     * @param  list<array<string, mixed>>  $yaPresentes
     * @return list<array<string, mixed>>
     */
    public static function soloNuevos(array $erp, array $yaPresentes): array
    {
        $claves = self::claves($yaPresentes);
        $out = [];
        foreach ($erp as $reg) {
            if (self::estaEnClaves($reg, $claves)) {
                continue;
            }
            $out[] = $reg;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, true>
     */
    public static function claves(array $registros): array
    {
        $out = [];
        foreach ($registros as $reg) {
            foreach (self::clavesDeRegistro($reg) as $clave) {
                $out[$clave] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $claves
     */
    public static function estaEnClaves(array $reg, array $claves): bool
    {
        foreach (self::clavesDeRegistro($reg) as $clave) {
            if (isset($claves[$clave])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function clavesDeRegistro(array $reg): array
    {
        $nroComp = (int) ($reg['nro_comp'] ?? 0);
        $nroCert = (int) ($reg['nro_cert'] ?? 0);
        $importe = number_format(abs((float) ($reg['importe'] ?? 0)), 2, '.', '');
        $out = [];
        if ($nroComp > 0 && $nroCert > 0) {
            $out[] = 'c:'.$nroComp.'|t:'.$nroCert;
        }
        if ($nroComp > 0 && $importe !== '0.00') {
            $out[] = 'c:'.$nroComp.'|i:'.$importe;
        }

        return $out;
    }
}
