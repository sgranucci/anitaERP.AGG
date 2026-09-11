<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Variantes de esquema Anita `promae` / tablas hijas según instalación.
 *
 * - AGG: columnas extendidas (cta_me, cc_default, ag_perc_*, fe_ini_excl*, etc.) + promadic/proexcl/propago.
 * - Surmar/Bierzo (`PROVEEDOR_FILTRO_EMPRESA`): lectura reducida; sin hijas AGG.
 * - Interforming: base Surmar sin campos BSAS (ret_ibr_bsas, emite_cert, nro_estab);
 *   sin columnas AGG; hijas vacías o no alineadas → no leer promadic ni exclusiones AGG.
 */
final class ProveedorAnitaEsquemaSupport
{
    public const VARIANTE_AGG = 'agg';

    public const VARIANTE_SURMAR = 'surmar';

    public const VARIANTE_INTERFORMING = 'interforming';

    public static function variante(): string
    {
        if (EntornoEmpresaSupport::esInterforming()) {
            return self::VARIANTE_INTERFORMING;
        }

        if (config('proveedor.filtro_empresa')) {
            return self::VARIANTE_SURMAR;
        }

        return self::VARIANTE_AGG;
    }

    public static function esEsquemaAgg(): bool
    {
        return self::variante() === self::VARIANTE_AGG;
    }

    public static function esInterforming(): bool
    {
        return self::variante() === self::VARIANTE_INTERFORMING;
    }

    /**
     * Leer promadic / proexcl / propago con layout AGG.
     */
    public static function leeTablasHijasAgg(): bool
    {
        return self::esEsquemaAgg();
    }

    /**
     * Campos de `promae` para importar/actualizar cabecera en el ERP.
     */
    public static function camposPromaeLectura(): string
    {
        return match (self::variante()) {
            self::VARIANTE_INTERFORMING => self::camposPromaeInterforming(),
            self::VARIANTE_SURMAR => self::camposPromaeSurmar(),
            default => self::camposPromaeAgg(),
        };
    }

    /**
     * Subconjunto de `promae` para preview de exclusiones / dry-run.
     */
    public static function camposPromaeExclusionPreview(): string
    {
        if (self::esEsquemaAgg()) {
            return '
				prom_proveedor,
				prom_nombre,
				prom_excl_retiva,
				prom_fecha_excl,
				prom_fe_ini_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_fe_ini_exclrg,
				prom_excl_retib,
				prom_fecha_exclib,
				prom_fe_ini_exclib
			';
        }

        return '
				prom_proveedor,
				prom_nombre,
				prom_excl_retiva,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg
			';
    }

    private static function camposPromaeInterforming(): string
    {
        // Verificado contra Anita /usr2/interforming: sin BSAS ni columnas AGG.
        return '
				prom_proveedor,
				prom_nombre,
				prom_contacto,
				prom_direccion,
				prom_localidad,
				prom_cod_postal,
				prom_provincia,
				prom_telefono,
				prom_cuit,
				prom_cond_iva,
				prom_letra,
				prom_cond_pago,
				prom_cta_contable,
				prom_credito,
				prom_dias_atraso,
				prom_nro_interno,
				prom_agente_ret,
				prom_cond_gan,
				prom_incl_impuesto,
				prom_cond_compra,
				prom_cond_entrega,
				prom_tipo_empresa,
				prom_prov_vario,
				prom_retiene_iva,
				prom_cod_retgan,
				prom_cod_retiva,
				prom_a_nombre_de,
				prom_ret_suss,
				prom_ret_ibr,
				prom_nro_ret_ibr,
				prom_nro_reemp_ib,
				prom_excl_retiva,
				prom_pais,
				prom_fecha_alta,
				prom_estado_pro,
				prom_fantasia,
				prom_regimen,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_cod_localidad,
				prom_tipo_emp_alfa,
				prom_e_mail,
				prom_fax,
				prom_fecha_boletin,
				prom_cod_ret_suss
			';
    }

    private static function camposPromaeSurmar(): string
    {
        return '
				prom_proveedor,
				prom_nombre,
				prom_contacto,
				prom_direccion,
				prom_localidad,
				prom_cod_postal,
				prom_provincia,
				prom_telefono,
				prom_cuit,
				prom_cond_iva,
				prom_letra,
				prom_cond_pago,
				prom_cta_contable,
				prom_credito,
				prom_dias_atraso,
				prom_nro_interno,
				prom_agente_ret,
				prom_cond_gan,
				prom_incl_impuesto,
				prom_cond_compra,
				prom_cond_entrega,
				prom_tipo_empresa,
				prom_prov_vario,
				prom_retiene_iva,
				prom_cod_retgan,
				prom_cod_retiva,
				prom_a_nombre_de,
				prom_ret_suss,
				prom_ret_ibr,
				prom_nro_ret_ibr,
				prom_nro_reemp_ib,
				prom_excl_retiva,
				prom_pais,
				prom_fecha_alta,
				prom_estado_pro,
				prom_fantasia,
				prom_regimen,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_cod_localidad,
				prom_tipo_emp_alfa,
				prom_e_mail,
				prom_fax,
				prom_fecha_boletin,
				prom_ret_ibr_bsas,
				prom_emite_cert,
				prom_nro_estab
			';
    }

    private static function camposPromaeAgg(): string
    {
        return '
				prom_proveedor ,
				prom_nombre,
				prom_contacto,
				prom_direccion,
				prom_localidad,
				prom_cod_postal,
				prom_provincia,
				prom_telefono,
				prom_cuit,
				prom_cond_iva,
				prom_letra,
				prom_cond_pago,
				prom_cta_contable,
				prom_credito,
				prom_dias_atraso,
				prom_nro_interno,
				prom_agente_ret,
				prom_cond_gan,
				prom_incl_impuesto,
				prom_cond_compra,
				prom_cond_entrega,
				prom_tipo_empresa,
				prom_prov_vario,
				prom_retiene_iva,
				prom_cod_retgan,
				prom_cod_retiva,
				prom_a_nombre_de,
				prom_ret_suss,
				prom_ret_ibr,
				prom_nro_ret_ibr,
				prom_nro_reemp_ib,
				prom_excl_retiva,
				prom_pais,
				prom_fecha_alta,
				prom_estado_pro,
				prom_fantasia,
				prom_regimen,
				prom_fecha_excl,
				prom_excl_retgan,
				prom_fecha_exclrg,
				prom_cod_localidad,
				prom_tipo_emp_alfa,
				prom_e_mail,
				prom_fax,
				prom_fecha_boletin,
				prom_cod_ret_suss,
				prom_cta_cont_me,
				prom_cta_default,
				prom_cc_default,
				prom_concepto,
				prom_descuento,
				prom_fecha_exclib,
				prom_excl_retib,
				prom_fe_ini_excl,
				prom_fe_ini_exclrg,
				prom_fe_ini_exclib,
				prom_ag_perc_ib,
				prom_ag_perc_iva
			';
    }
}
