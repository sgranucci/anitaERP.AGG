<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Variantes de esquema Anita `climae` / tablas hijas según instalación.
 *
 * Crown (/usr2/casino, verificado 6/nov/2026 vía syscolumns bridge acncadmin):
 * - climae: 56 columnas; nro IIBB = clim_nro_ing_br (como AGG/Interforming).
 * - Sin clim_nro_ing_bruto ni campos Bierzo (abasto, coef, emite_cert/nc, dfexcl…).
 * - Sin clim_url / clim_hs_atencion2 (Interforming).
 * - Hijas: cliley sí; cliibr sí; climarch / movscli / stksuspcli / clicomi no.
 */
final class ClienteAnitaEsquemaSupport
{
    public const VARIANTE_AGG = 'agg';

    public const VARIANTE_BIERZO = 'bierzo';

    public const VARIANTE_INTERFORMING = 'interforming';

    public const VARIANTE_FERLI = 'ferli';

    public const VARIANTE_CROWN = 'crown';

    /**
     * Columnas reales de ventas.climae en /usr2/casino (Crown).
     *
     * @var list<string>
     */
    public const COLUMNAS_CLIMAE_CROWN = [
        'clim_cliente',
        'clim_nombre',
        'clim_contacto',
        'clim_direccion',
        'clim_localidad',
        'clim_cod_postal',
        'clim_provincia',
        'clim_telefono',
        'clim_cuit',
        'clim_cond_iva',
        'clim_porc_excen',
        'clim_letra',
        'clim_cond_venta',
        'clim_cta_contable',
        'clim_credito',
        'clim_dias_atraso',
        'clim_zonavta',
        'clim_subzona',
        'clim_zonamult',
        'clim_vendedor',
        'clim_cobrador',
        'clim_expreso',
        'clim_tipo_empresa',
        'clim_dir_cobranza',
        'clim_hs_cobranza',
        'clim_lugar_entrega',
        'clim_retiene_iva',
        'clim_lista_precio',
        'clim_descuento',
        'clim_nro_interno',
        'clim_fecha_interes',
        'clim_proveedor',
        'clim_minimo_fact',
        'clim_estado_cli',
        'clim_dias_cobranza',
        'clim_dias_atencion',
        'clim_hs_atencion',
        'clim_pais',
        'clim_perc_ing_br',
        'clim_nro_ing_br',
        'clim_dir_postal',
        'clim_loc_postal',
        'clim_cp_postal',
        'clim_fantasia',
        'clim_fecha_alta',
        'clim_ley_liberado',
        'clim_regimen',
        'clim_leyenda_fact',
        'clim_prov_postal',
        'clim_lugar_de_pago',
        'clim_excl_perc_iva',
        'clim_fe_excl_piva',
        'clim_dto_integrado',
        'clim_fecha_boletin',
        'clim_e_mail',
        'clim_fax',
    ];

    public static function variante(): string
    {
        if (EntornoEmpresaSupport::esCrown()) {
            return self::VARIANTE_CROWN;
        }

        if (EntornoEmpresaSupport::esElBierzo()) {
            return self::VARIANTE_BIERZO;
        }

        if (EntornoEmpresaSupport::esInterforming()) {
            return self::VARIANTE_INTERFORMING;
        }

        if (EntornoEmpresaSupport::esAgg()) {
            return self::VARIANTE_AGG;
        }

        return self::VARIANTE_FERLI;
    }

    public static function esCrown(): bool
    {
        return self::variante() === self::VARIANTE_CROWN;
    }

    public static function esBierzo(): bool
    {
        return self::variante() === self::VARIANTE_BIERZO;
    }

    public static function esInterforming(): bool
    {
        return self::variante() === self::VARIANTE_INTERFORMING;
    }

    /**
     * Columna Informix del nro. de ingresos brutos en climae.
     * Bierzo (y Ferli histórico) usan clim_nro_ing_bruto; el resto clim_nro_ing_br.
     */
    public static function campoNroIngBr(): string
    {
        return match (self::variante()) {
            self::VARIANTE_BIERZO, self::VARIANTE_FERLI => 'clim_nro_ing_bruto',
            default => 'clim_nro_ing_br',
        };
    }

    public static function escribeCliley(): bool
    {
        return true;
    }

    public static function escribeClimarch(): bool
    {
        return ! self::esCrown() && (bool) config('cliente.SINCRONIZA_CLIMA_ANITA', false);
    }

    public static function escribeMovscli(): bool
    {
        return self::esBierzo();
    }

    public static function escribeStksuspcli(): bool
    {
        return self::esBierzo();
    }

    public static function escribeClicomi(): bool
    {
        return self::variante() === self::VARIANTE_FERLI;
    }

    public static function escribeCliibr(): bool
    {
        return self::esInterforming();
    }

    public static function escribeCamposBierzo(): bool
    {
        return self::esBierzo();
    }

    public static function escribeCamposInterforming(): bool
    {
        return self::esInterforming();
    }

    /**
     * @return list<string>|null Null = no restringir (usar payload completo del repo).
     */
    public static function columnasClimaePermitidasEnEscritura(): ?array
    {
        return match (self::variante()) {
            self::VARIANTE_CROWN => self::COLUMNAS_CLIMAE_CROWN,
            default => null,
        };
    }
}
