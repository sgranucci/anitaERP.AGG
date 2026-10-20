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
 *
 * Ferli (verificado 19/oct/2026):
 * - pendmaep sin penmp_ccosto, penmp_ccosto_dest, penmp_empresa, penmp_legajo,
 *   penmp_es_anticipo ni campos de aprobación/usuario.
 * - pendmovp sin penvp_ccosto / penvp_empresa / penvp_nro_interno.
 */
final class OrdencompraAnitaEsquemaSupport
{
    public static function esCrown(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    public static function esFerli(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    /** Sin columnas de centro de costo / empresa / legajo en cabecera Anita. */
    public static function pendmaepSinCcostoEmpresa(): bool
    {
        return self::esFerli();
    }

    public static function camposPendmaepLectura(): string
    {
        $base = [
            'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
            'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
            'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_razon_susp', 'penmp_cod_mon', 'penmp_cotizacion',
            'penmp_fecha_ing', 'penmp_hora_ing', 'penmp_estado', 'penmp_requisicion',
        ];

        if (! self::pendmaepSinCcostoEmpresa()) {
            $base[] = 'penmp_ccosto';
            $base[] = 'penmp_legajo';
            $base[] = 'penmp_empresa';
            $base[] = 'penmp_usuario_ini';
            $base[] = 'penmp_estado_aprob';
            $base[] = 'penmp_fecha_aprob';
            $base[] = 'penmp_hora_aprob';
            $base[] = 'penmp_usu_aprob';
        }

        if (! self::esCrown() && ! self::pendmaepSinCcostoEmpresa()) {
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

        if (! self::esCrown() && ! self::esFerli()) {
            $base[] = 'penvp_ccosto';
            $base[] = 'penvp_empresa';
            $base[] = 'penvp_nro_interno';
        }

        return implode(', ', $base);
    }

    /** Campos mínimos para precarga PDF+IA (leeOrdenCompra). */
    public static function camposPendmaepPrecarga(): string
    {
        $campos = [
            'penmp_proveedor',
            'penmp_tipo',
            'penmp_letra',
            'penmp_sucursal',
            'penmp_nro',
        ];
        if (! self::pendmaepSinCcostoEmpresa()) {
            $campos[] = 'penmp_ccosto';
            if (! self::esCrown()) {
                $campos[] = 'penmp_ccosto_dest';
            }
        }

        return implode(', ', $campos);
    }

    /** Ítems para precarga: Ferli/Crown sin join a stkmae ni penvp_ccosto. */
    public static function camposPendmovpPrecarga(): array
    {
        if (self::esFerli() || self::esCrown()) {
            return [
                'tabla' => 'pendmovp',
                'campos' => 'penvp_articulo, penvp_cantidad',
                'join_stkmae' => false,
            ];
        }

        return [
            'tabla' => 'pendmovp,stkmae',
            'campos' => 'penvp_articulo, penvp_cantidad, penvp_ccosto, stkm_tipo_articulo, stkm_agrupacion',
            'join_stkmae' => true,
        ];
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
