<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Variantes de lectura Anita reqmae / reqmov según instalación.
 *
 * Crown (/usr2/casino, verificado 17/oct/2026):
 * - reqmae sin urgencia/cond_pago/cont_directa (columnas AGG).
 * - reqmov sin precio_ori / motivo_ahorro.
 * - sin tablas reqmref ni aprobcomp.
 * - casino = prefijo 2 dígitos del nro (casino.h CASINO_trae_casino).
 */
final class RequisicionAnitaEsquemaSupport
{
    public static function esCrown(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    public static function camposReqmaeLectura(): string
    {
        if (self::esCrown()) {
            return 'reqm_nro, reqm_fecha, reqm_fecha_ent, reqm_deposito, reqm_ccosto, reqm_estado, reqm_leyenda, reqm_deposito_alfa, reqm_empresa, reqm_proveedor, reqm_cod_mon, reqm_ccosto_dest, reqm_usuario, reqm_fecha_ing, reqm_hora_ing';
        }

        return 'reqm_nro, reqm_fecha, reqm_fecha_ent, reqm_ccosto, reqm_estado, reqm_leyenda, reqm_empresa, reqm_proveedor, reqm_cod_mon, reqm_ccosto_dest, reqm_cond_pago, reqm_es_urgente, reqm_mot_urgencia, reqm_cont_directa, reqm_usuario, reqm_fecha_ing, reqm_hora_ing';
    }

    public static function camposReqmovLectura(): string
    {
        if (self::esCrown()) {
            return 'reqv_nro, reqv_nro_orden, reqv_articulo, reqv_desc, reqv_cantidad, reqv_precio, reqv_fecha_ent, reqv_ccosto, reqv_cant_unid, reqv_nro_interno, reqv_proveedor, reqv_deposito';
        }

        return 'reqv_nro, reqv_nro_orden, reqv_articulo, reqv_desc, reqv_cantidad, reqv_precio, reqv_fecha_ent, reqv_ccosto, reqv_cant_unid, reqv_nro_interno, reqv_precio_ori, reqv_motivo_ahorro, reqv_proveedor';
    }

    public static function leeReqmref(): bool
    {
        return ! self::esCrown();
    }

    public static function leeAprobcomp(): bool
    {
        return ! self::esCrown();
    }

    /** Crown: import solo lectura; no reescribir penmp/OC en Anita. */
    public static function escribeVinculosOcEnAnita(): bool
    {
        return ! self::esCrown();
    }
}
