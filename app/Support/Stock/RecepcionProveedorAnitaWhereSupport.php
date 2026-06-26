<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Claves WHERE Anita para recepmae, recepmov y recpunica (COM).
 */
class RecepcionProveedorAnitaWhereSupport
{
    public const TERMINAL_ERP = 'ERP';

    /** Terminal Anita desktop / legacy (no tocar en bridge ERP). */
    public const TERMINAL_REF = 'REF';

    /**
     * Cabeceras que no debe modificar ni borrar el bridge ERP (Anita desktop u otro origen).
     */
    public static function esTerminalProtegidoAnita(?string $terminal): bool
    {
        $t = trim((string) $terminal);

        return $t === '' || $t === self::TERMINAL_REF || strcasecmp($t, self::TERMINAL_REF) === 0;
    }

    public static function esTerminalErp(?string $terminal): bool
    {
        return trim((string) $terminal) === self::TERMINAL_ERP;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmae(string $codigoProveedor, array $clave): string
    {
        $codigoProveedor = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        return self::recepmaePorClave($clave)
            ." AND recm_proveedor = '".addslashes($codigoProveedor)."'";
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmaePorClave(array $clave): string
    {
        return " WHERE recm_tipo = '".addslashes($clave['tipo'])."'"
            ." AND recm_letra = '".addslashes($clave['letra'])."'"
            .' AND recm_sucursal = '.(int) $clave['sucursal']
            .' AND recm_nro = '.(int) $clave['nro'];
    }

    public static function filtroTerminalErp(string $columna): string
    {
        return ' AND '.$columna.' = '.self::terminalErpSql();
    }

    public static function terminalErpSql(): string
    {
        return "'".addslashes(self::TERMINAL_ERP)."'";
    }

    public static function terminalRefSql(): string
    {
        return "'".addslashes(self::TERMINAL_REF)."'";
    }

    /** Cabecera COM grabada desde anitaERP (no tocar recepciones hechas en Anita). */
    public static function recepmaeSoloErp(string $codigoProveedor, array $clave): string
    {
        return self::recepmae($codigoProveedor, $clave).self::filtroTerminalErp('recm_terminal');
    }

    public static function recepmaeDocumentoErp(int $documentoId): string
    {
        return ' WHERE recm_documentoid = '.(int) $documentoId.self::filtroTerminalErp('recm_terminal');
    }

    /**
     * Cabeceras vinculadas al documento ERP (auditoría): terminal ERP, REF o vacío (Anita desktop).
     */
    public static function recepmaeDocumentoErpORef(int $documentoId): string
    {
        return ' WHERE recm_documentoid = '.(int) $documentoId
            .' AND (recm_terminal = '.self::terminalErpSql()
            .' OR recm_terminal = '.self::terminalRefSql()
            ." OR TRIM(recm_terminal) = ''"
            .' OR recm_terminal IS NULL)';
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function recepmovProveedorCabecera(string $codigoProveedor, array $clave): string
    {
        $codigoProveedor = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        return self::recepmovCabecera($clave)
            ." AND recv_proveedor = '".addslashes($codigoProveedor)."'";
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveCom */
    public static function stkmovCabeceraSoloErp(array $claveCom): string
    {
        return self::stkmovCabecera($claveCom).self::filtroTerminalErp('stkv_terminal');
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

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveCom */
    public static function stkmovCabecera(array $claveCom): string
    {
        return " WHERE stkv_tipo = '".addslashes($claveCom['tipo'])."'"
            ." AND stkv_letra = '".addslashes($claveCom['letra'])."'"
            .' AND stkv_sucursal = '.(int) $claveCom['sucursal']
            .' AND stkv_nro = '.(int) $claveCom['nro'];
    }

    public static function aplicpedCom(string $codigoProveedor, array $claveCom): string
    {
        $codigoProveedor = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($codigoProveedor);

        return " WHERE aplp_proveedor = '".addslashes($codigoProveedor)."'"
            ." AND aplp_tipo = '".addslashes($claveCom['tipo'])."'"
            ." AND aplp_letra = '".addslashes($claveCom['letra'])."'"
            .' AND aplp_sucursal = '.(int) $claveCom['sucursal']
            .' AND aplp_nro = '.(int) $claveCom['nro'];
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $claveOc */
    public static function pendmovpLinea(
        array $claveOc,
        int $numeroOc,
        int $nroInterno,
        int $penvpOrden,
        string $sku
    ): string {
        $base = " WHERE
            penvp_tipo='".addslashes($claveOc['tipo'])."' and
            penvp_letra='".addslashes($claveOc['letra'])."' and
            penvp_sucursal=".(int) $claveOc['sucursal']." and
            penvp_nro=".(int) $numeroOc;

        if ($nroInterno > 0) {
            return $base." and penvp_nro_interno={$nroInterno}";
        }

        $skuAnita = RecepcionProveedorAnitaEscrituraSupport::skuAnita13($sku);

        return $base." and
            penvp_orden={$penvpOrden} and
            penvp_articulo='".addslashes($skuAnita)."'";
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

        return RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6(
            (string) ($proveedor->codigo ?? $proveedor->id ?? '')
        );
    }
}
