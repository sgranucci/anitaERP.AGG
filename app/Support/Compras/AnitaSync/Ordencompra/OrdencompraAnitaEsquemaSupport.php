<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Variantes de lectura Anita pendmaep / pendmovp / tablas relacionadas.
 *
 * Crown (/usr2/casino, verificado 17/oct/2026):
 * - pendmaep sin penmp_es_anticipo ni penmp_ccosto_dest.
 * - pendmovp sin penvp_ccosto, penvp_empresa ni penvp_nro_interno.
 * - sin tablas occuota, ocfpagocuota, legcompra, pendfecha.
 * - ocvley y movpresup existen (movpresup casi vacío en el período).
 * - penmp_sucursal = casino Anita (sala ERP).
 */
final class OrdencompraAnitaEsquemaSupport
{
    public static function esCrown(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    public static function camposPendmaepLectura(): string
    {
        $base = [
            'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
            'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
            'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_razon_susp', 'penmp_cod_mon', 'penmp_cotizacion',
            'penmp_fecha_ing', 'penmp_hora_ing', 'penmp_estado', 'penmp_requisicion',
            'penmp_ccosto', 'penmp_legajo', 'penmp_empresa', 'penmp_usuario_ini', 'penmp_estado_aprob',
            'penmp_fecha_aprob', 'penmp_hora_aprob', 'penmp_usu_aprob',
        ];

        if (! self::esCrown()) {
            $base[] = 'penmp_ccosto_dest';
            $base[] = 'penmp_es_anticipo';
        }

        // Último: si la leyenda trae "|" el UNLOAD parte la fila; el resto ya quedó leído.
        $base[] = 'penmp_leyenda';

        return implode(', ', $base);
    }

    public static function camposPendmovpLectura(): string
    {
        $base = [
            'penvp_proveedor', 'penvp_tipo', 'penvp_letra', 'penvp_sucursal', 'penvp_nro', 'penvp_orden',
            'penvp_articulo', 'penvp_desc', 'penvp_agrupacion', 'penvp_unidad_med', 'penvp_cantidad',
            'penvp_cantentr', 'penvp_cantfact', 'penvp_precio', 'penvp_dto_art', 'penvp_deposito',
            'penvp_tipo_iva', 'penvp_fecha', 'penvp_incl_imp', 'penvp_cod_mon', 'penvp_partida',
            'penvp_fecha_ent', 'penvp_requisicion',
        ];

        if (! self::esCrown()) {
            $base[] = 'penvp_ccosto';
            $base[] = 'penvp_empresa';
            $base[] = 'penvp_nro_interno';
        }

        return implode(', ', $base);
    }

    public static function leeOccuota(): bool
    {
        return ! self::esCrown();
    }

    public static function leeLegcompra(): bool
    {
        return ! self::esCrown();
    }

    public static function leeMovpresup(): bool
    {
        // Crown tiene la tabla pero sin nro_interno en líneas; no aporta FK útiles.
        return ! self::esCrown();
    }

    /** Crown: import solo lectura; no reescribir Anita. */
    public static function escribeEnAnita(): bool
    {
        return ! self::esCrown();
    }
}
