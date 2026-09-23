<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Esquema Anita pendmae / pendmov / comprob (exportación) para INTERFORMING.
 *
 * Verificado 23/sep/2026 contra systables/syscolumns /usr2/interforming (ventas):
 * - pendmae: 33 columnas (incluye penm_en_stock; sin campos Bierzo kg/caja reales).
 * - pendmov: 49 columnas (cantidades, fason, ubicación, estados aprobación/cierre).
 * - comprob: 25 columnas; exportación en comp_incoterm / comp_bultos / comp_peso_neto
 *   (no usa comp_cond_vta_exp de Ferli).
 */
final class PedidoAnitaEsquemaSupport
{
    public const TABLA_CABECERA = 'pendmae';

    public const TABLA_MOVIMIENTO = 'pendmov';

    public const TABLA_COMPROB = 'comprob';

    public const TIPOS_PEDIDO = ['PED', 'PEX'];

    /**
     * @return list<string>
     */
    public static function camposPendmaeLectura(): array
    {
        return [
            'penm_cliente',
            'penm_tipo',
            'penm_letra',
            'penm_sucursal',
            'penm_nro',
            'penm_ref_tipo',
            'penm_ref_letra',
            'penm_ref_sucursal',
            'penm_ref_nro',
            'penm_fecha',
            'penm_fecha_ent',
            'penm_cond_vta',
            'penm_deposito',
            'penm_vendedor',
            'penm_zonavta',
            'penm_entrega',
            'penm_dto',
            'penm_expreso',
            'penm_o_compra',
            'penm_razon_susp',
            'penm_cod_mon',
            'penm_cotizacion',
            'penm_fecha_ing',
            'penm_hora_ing',
            'penm_estado',
            'penm_leyenda',
            'penm_tipo_fact',
            'penm_letra_fact',
            'penm_sucursal_fact',
            'penm_nro_fact',
            'penm_dto_integrado',
            'penm_cod_entrega',
            'penm_en_stock',
        ];
    }

    /**
     * @return list<string>
     */
    public static function camposPendmovLectura(): array
    {
        return [
            'penv_cliente',
            'penv_tipo',
            'penv_letra',
            'penv_sucursal',
            'penv_nro',
            'penv_orden',
            'penv_articulo',
            'penv_desc',
            'penv_agrupacion',
            'penv_unidad_medida',
            'penv_cantidad',
            'penv_cantaentr',
            'penv_cantentr',
            'penv_cantfact',
            'penv_precio',
            'penv_dto_art',
            'penv_deposito',
            'penv_tipo_iva',
            'penv_fecha',
            'penv_incl_impuesto',
            'penv_cod_mon',
            'penv_vendedor',
            'penv_zonavta',
            'penv_zonamult',
            'penv_partida',
            'penv_fecha_ent',
            'penv_o_compra',
            'penv_pedido',
            'penv_desc_aux',
            'penv_cod_umd',
            'penv_cod_umd_alter',
            'penv_cant_alter',
            'penv_estado',
            'penv_usu_aprob',
            'penv_fecha_aprob',
            'penv_motivo_rech',
            'penv_nro_talon',
            'penv_dto_integrado',
            'penv_porc_fason',
            'penv_porc_fasonant',
            'penv_precio_fason',
            'penv_cod_mon_fason',
            'penv_ubicacion',
            'penv_detalle',
            'penv_estado_cierre',
            'penv_codigo_motivo',
            'penv_fecha_cierre',
            'penv_hora_cierre',
            'penv_usu_cierre',
        ];
    }

    /**
     * Columnas de comprob INTERFORMING (exportación incluida).
     *
     * @return list<string>
     */
    public static function camposComprobEscritura(): array
    {
        return [
            'comp_cliente',
            'comp_tipo',
            'comp_letra',
            'comp_sucursal',
            'comp_nro_fact',
            'comp_pedido',
            'comp_remito',
            'comp_fecha',
            'comp_fevto',
            'comp_cond_vta',
            'comp_entrega',
            'comp_dto',
            'comp_transporte',
            'comp_o_compra',
            'comp_leyenda',
            'comp_total',
            'comp_iva',
            'comp_no_insc',
            'comp_exento',
            'comp_gravado',
            'comp_dto_integrado',
            'comp_leyenda5',
            'comp_incoterm',
            'comp_bultos',
            'comp_peso_neto',
        ];
    }

    public static function sqlCamposPendmae(): string
    {
        return implode(",\n                ", self::camposPendmaeLectura());
    }

    public static function sqlCamposPendmov(): string
    {
        return implode(",\n                ", self::camposPendmovLectura());
    }

    public static function sqlCamposComprob(): string
    {
        return implode(",\n                            ", self::camposComprobEscritura());
    }

    public static function esInterforming(): bool
    {
        return EntornoEmpresaSupport::esInterforming();
    }

    public static function normalizarTipoPedido(?string $tipo): string
    {
        $tipo = strtoupper(trim((string) $tipo));

        return in_array($tipo, self::TIPOS_PEDIDO, true) ? $tipo : 'PED';
    }
}
