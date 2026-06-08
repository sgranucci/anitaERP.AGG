<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Claves WHERE Anita para recepmae, recepmov y recpunica (COM).
 */
class RecepcionProveedorAnitaWhereSupport
{
    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmae(string $codigoProveedor, array $clave): string
    {
        $codigoProveedor = str_pad(substr(trim($codigoProveedor), 0, 6), 6, ' ', STR_PAD_RIGHT);

        return " WHERE recm_proveedor = '".addslashes($codigoProveedor)."'"
            ." AND recm_tipo = '".addslashes($clave['tipo'])."'"
            ." AND recm_letra = '".addslashes($clave['letra'])."'"
            .' AND recm_sucursal = '.(int) $clave['sucursal']
            .' AND recm_nro = '.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmovCabecera(array $clave): string
    {
        return " WHERE recv_tipo = '".addslashes($clave['tipo'])."'"
            ." AND recv_letra = '".addslashes($clave['letra'])."'"
            .' AND recv_sucursal = '.(int) $clave['sucursal']
            .' AND recv_nro = '.(int) $clave['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmovLinea(array $clave, int $orden): string
    {
        return self::recepmovCabecera($clave).' AND recv_orden = '.(int) $orden;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recpunicaCabecera(array $clave): string
    {
        return " WHERE recpu_tipo = '".addslashes($clave['tipo'])."'"
            ." AND recpu_letra = '".addslashes($clave['letra'])."'"
            .' AND recpu_sucursal = '.(int) $clave['sucursal']
            .' AND recpu_nro = '.(int) $clave['nro'];
    }

    public static function codigoProveedorAnita(Recepcion_Proveedor $recepcion): string
    {
        $recepcion->loadMissing('proveedores');
        $proveedor = $recepcion->proveedores;

        return str_pad(substr((string) ($proveedor->codigo ?? $proveedor->id ?? ''), 0, 6), 6, ' ', STR_PAD_RIGHT);
    }
}
