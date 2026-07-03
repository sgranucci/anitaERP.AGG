<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Cláusulas WHERE para escritura OC en Anita (clave PEP / X / sucursal 0 / nro).
 */
final class OrdencompraAnitaWhereSupport
{
    /** @return array{tipo: string, letra: string, sucursal: int, nro: int} */
    public static function claveDesdeConfig(?int $numeroOc = null): array
    {
        $cfg = config('ordencompra_anita.escritura');

        return [
            'tipo' => (string) ($cfg['oc_tipo'] ?? 'PEP'),
            'letra' => (string) ($cfg['oc_letra'] ?? 'X'),
            'sucursal' => (int) ($cfg['oc_sucursal'] ?? 0),
            'nro' => (int) $numeroOc,
        ];
    }

    public static function claveDesdeOrdencompra(\App\Models\Compras\Ordencompra $oc): array
    {
        return self::claveDesdeConfig((int) $oc->numeroordencompra);
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function pendmaep(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE penmp_tipo='{$tipo}' AND penmp_letra='{$letra}'"
            .' AND penmp_sucursal='.(int) $clave['sucursal']
            .' AND penmp_nro='.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function pendmovp(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE penvp_tipo='{$tipo}' AND penvp_letra='{$letra}'"
            .' AND penvp_sucursal='.(int) $clave['sucursal']
            .' AND penvp_nro='.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function movpresup(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE movp_tipo='{$tipo}' AND movp_letra='{$letra}'"
            .' AND movp_sucursal='.(int) $clave['sucursal']
            .' AND movp_nro='.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function occuota(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE occ_tipo='{$tipo}' AND occ_letra='{$letra}'"
            .' AND occ_sucursal='.(int) $clave['sucursal']
            .' AND occ_nro='.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function ocfpagocuota(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE ocfp_tipo='{$tipo}' AND ocfp_letra='{$letra}'"
            .' AND ocfp_sucursal='.(int) $clave['sucursal']
            .' AND ocfp_nro='.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function ocvley(array $clave): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));

        return " WHERE ocvl_tipo='{$tipo}' AND ocvl_letra='{$letra}'"
            .' AND ocvl_sucursal='.(int) $clave['sucursal']
            .' AND ocvl_nro='.(int) $clave['nro'];
    }

    public static function legcompraPorNumeroOc(int $numeroOc): string
    {
        return ' WHERE legc_id='.(int) $numeroOc;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function pendfecha(array $clave, string $proveedor6): string
    {
        $tipo = addslashes(trim($clave['tipo']));
        $letra = addslashes(trim($clave['letra']));
        $prov = addslashes(str_pad(trim($proveedor6), 6, '0', STR_PAD_LEFT));

        return " WHERE penpf_proveedor='{$prov}' AND penpf_tipo='{$tipo}' AND penpf_letra='{$letra}'"
            .' AND penpf_sucursal='.(int) $clave['sucursal']
            .' AND penpf_nro='.(int) $clave['nro'];
    }

    public static function pendmaepPorNumero(int $numeroOc): string
    {
        return ' WHERE penmp_nro='.(int) $numeroOc;
    }
}
