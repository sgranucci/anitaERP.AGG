<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Variantes de esquema Anita `promae` / tablas hijas según instalación.
 *
 * - AGG: columnas extendidas (cta_me, cc_default, ag_perc_*, fe_ini_excl*, etc.) + promadic/proexcl/propago/servicios.
 * - Surmar/Bierzo (`PROVEEDOR_FILTRO_EMPRESA`): lectura reducida; sin hijas AGG.
 * - Interforming: base Surmar sin campos BSAS; sin columnas AGG; sin promadic/proexcl.
 * - Calzados Ferli: 50 columnas hasta prom_concepto; sin promadic/proexcl/propago/servicios; proley sí.
 * - Crown (/usr2/casino, verificado 17/oct/2026): 46 columnas base (como Interforming + prom_cod_ret_suss);
 *   sin cta_me/cc/concepto/ag_perc/excl IB extendidas; sin promadic/proexcl/servicios;
 *   sí propago (sin prop_offset), proley, proibr, procasino↔sala ERP.
 */
final class ProveedorAnitaEsquemaSupport
{
    public const VARIANTE_AGG = 'agg';

    public const VARIANTE_SURMAR = 'surmar';

    public const VARIANTE_INTERFORMING = 'interforming';

    public const VARIANTE_FERLI = 'ferli';

    public const VARIANTE_CROWN = 'crown';

    /**
     * Columnas reales de compras.promae en /usr2/ferli (syscolumns, 21/sep/2026).
     *
     * @var list<string>
     */
    public const COLUMNAS_PROMAE_FERLI = [
        'prom_proveedor',
        'prom_nombre',
        'prom_contacto',
        'prom_direccion',
        'prom_localidad',
        'prom_cod_postal',
        'prom_provincia',
        'prom_telefono',
        'prom_cuit',
        'prom_cond_iva',
        'prom_letra',
        'prom_cond_pago',
        'prom_cta_contable',
        'prom_credito',
        'prom_dias_atraso',
        'prom_nro_interno',
        'prom_agente_ret',
        'prom_cond_gan',
        'prom_incl_impuesto',
        'prom_cond_compra',
        'prom_cond_entrega',
        'prom_tipo_empresa',
        'prom_prov_vario',
        'prom_retiene_iva',
        'prom_cod_retgan',
        'prom_cod_retiva',
        'prom_a_nombre_de',
        'prom_ret_suss',
        'prom_ret_ibr',
        'prom_nro_ret_ibr',
        'prom_nro_reemp_ib',
        'prom_excl_retiva',
        'prom_pais',
        'prom_fecha_alta',
        'prom_estado_pro',
        'prom_fantasia',
        'prom_regimen',
        'prom_fecha_excl',
        'prom_excl_retgan',
        'prom_fecha_exclrg',
        'prom_cod_localidad',
        'prom_tipo_emp_alfa',
        'prom_e_mail',
        'prom_fax',
        'prom_fecha_boletin',
        'prom_cod_ret_suss',
        'prom_cta_cont_me',
        'prom_cta_default',
        'prom_cc_default',
        'prom_concepto',
    ];

    /**
     * Crown /usr2/casino: mismas 46 columnas que Interforming + prom_cod_ret_suss;
     * sin cta_me / default / concepto (verificado bridge 17/oct/2026).
     *
     * @var list<string>
     */
    public const COLUMNAS_PROMAE_CROWN = [
        'prom_proveedor',
        'prom_nombre',
        'prom_contacto',
        'prom_direccion',
        'prom_localidad',
        'prom_cod_postal',
        'prom_provincia',
        'prom_telefono',
        'prom_cuit',
        'prom_cond_iva',
        'prom_letra',
        'prom_cond_pago',
        'prom_cta_contable',
        'prom_credito',
        'prom_dias_atraso',
        'prom_nro_interno',
        'prom_agente_ret',
        'prom_cond_gan',
        'prom_incl_impuesto',
        'prom_cond_compra',
        'prom_cond_entrega',
        'prom_tipo_empresa',
        'prom_prov_vario',
        'prom_retiene_iva',
        'prom_cod_retgan',
        'prom_cod_retiva',
        'prom_a_nombre_de',
        'prom_ret_suss',
        'prom_ret_ibr',
        'prom_nro_ret_ibr',
        'prom_nro_reemp_ib',
        'prom_excl_retiva',
        'prom_pais',
        'prom_fecha_alta',
        'prom_estado_pro',
        'prom_fantasia',
        'prom_regimen',
        'prom_fecha_excl',
        'prom_excl_retgan',
        'prom_fecha_exclrg',
        'prom_cod_localidad',
        'prom_tipo_emp_alfa',
        'prom_e_mail',
        'prom_fax',
        'prom_fecha_boletin',
        'prom_cod_ret_suss',
    ];

    public static function variante(): string
    {
        if (EntornoEmpresaSupport::esCrown()) {
            return self::VARIANTE_CROWN;
        }

        if (EntornoEmpresaSupport::esInterforming()) {
            return self::VARIANTE_INTERFORMING;
        }

        if (EntornoEmpresaSupport::esFerli()) {
            return self::VARIANTE_FERLI;
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

    public static function esFerli(): bool
    {
        return self::variante() === self::VARIANTE_FERLI;
    }

    public static function esCrown(): bool
    {
        return self::variante() === self::VARIANTE_CROWN;
    }

    /**
     * Leer promadic / proexcl / servicios con layout AGG.
     */
    public static function leeTablasHijasAgg(): bool
    {
        return self::esEsquemaAgg();
    }

    /**
     * Escritura de hijas AGG (promadic, proexcl, servicios).
     * Ferli y Crown no tienen esas tablas.
     */
    public static function escribeTablasHijasAgg(): bool
    {
        return ! self::esFerli() && ! self::esCrown();
    }

    /** propago existe en AGG y Crown (Crown sin prop_offset). */
    public static function leePropago(): bool
    {
        return self::esEsquemaAgg() || self::esCrown();
    }

    public static function escribePropago(): bool
    {
        return self::escribeTablasHijasAgg() || self::esCrown();
    }

    public static function propagoTieneOffset(): bool
    {
        return self::esEsquemaAgg();
    }

    public static function leeProcasino(): bool
    {
        return self::esCrown();
    }

    public static function escribeProcasino(): bool
    {
        return self::esCrown();
    }

    /**
     * Columnas de promae que el ABM puede escribir. Null = todas (AGG y el resto).
     *
     * @return list<string>|null
     */
    public static function columnasPromaePermitidasEnEscritura(): ?array
    {
        return match (self::variante()) {
            self::VARIANTE_FERLI => self::COLUMNAS_PROMAE_FERLI,
            self::VARIANTE_CROWN => self::COLUMNAS_PROMAE_CROWN,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function columnasPromaeOmitidasEnEscritura(): array
    {
        if (self::esFerli()) {
            return [
                'prom_descuento',
                'prom_fecha_exclib',
                'prom_excl_retib',
                'prom_fe_ini_excl',
                'prom_fe_ini_exclrg',
                'prom_fe_ini_exclib',
                'prom_ag_perc_ib',
                'prom_ag_perc_iva',
            ];
        }

        if (self::esCrown()) {
            return [
                'prom_cta_cont_me',
                'prom_cta_default',
                'prom_cc_default',
                'prom_concepto',
                'prom_descuento',
                'prom_fecha_exclib',
                'prom_excl_retib',
                'prom_fe_ini_excl',
                'prom_fe_ini_exclrg',
                'prom_fe_ini_exclib',
                'prom_ag_perc_ib',
                'prom_ag_perc_iva',
            ];
        }

        return [];
    }

    /**
     * Campos de `promae` para importar/actualizar cabecera en el ERP.
     */
    public static function camposPromaeLectura(): string
    {
        return match (self::variante()) {
            self::VARIANTE_INTERFORMING => self::camposPromaeInterforming(),
            self::VARIANTE_FERLI => self::camposPromaeFerli(),
            self::VARIANTE_SURMAR => self::camposPromaeSurmar(),
            self::VARIANTE_CROWN => self::camposPromaeCrown(),
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

    private static function camposPromaeFerli(): string
    {
        return implode(",\n", self::COLUMNAS_PROMAE_FERLI);
    }

    private static function camposPromaeCrown(): string
    {
        return implode(",\n", self::COLUMNAS_PROMAE_CROWN);
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
