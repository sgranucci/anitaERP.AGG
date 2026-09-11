<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Esquema Anita de listas de precio de proveedor (`listapmae` / `listapmov`).
 *
 * Interforming (verificado 11/sep/2026 contra syscolumns de compras):
 * - listapmae: lispm_nro, lispm_proveedor, lispm_fecha, lispm_cond_entrega,
 *   lispm_cond_pago, lispm_cond_compra, lispm_nombre_prov
 * - listapmov: listpv_nro, listpv_nro_orden, listpv_fecha, listpv_articulo,
 *   listpv_precio, listpv_proveedor
 *
 * AGG / otros: mismas tablas con columnas extra (estado, nombre lista, moneda,
 * usuario, descripción, cód. art. proveedor, descuento).
 */
final class ListaprecioProveedorAnitaEsquemaSupport
{
    public const TABLA_CABECERA = 'listapmae';

    public const TABLA_MOVIMIENTO = 'listapmov';

    public const KEY_CABECERA = 'lispm_nro';

    /**
     * @return list<string>
     */
    public static function camposCabecera(): array
    {
        if (EntornoEmpresaSupport::esInterforming()) {
            return [
                'lispm_nro',
                'lispm_proveedor',
                'lispm_fecha',
                'lispm_cond_entrega',
                'lispm_cond_pago',
                'lispm_cond_compra',
                'lispm_nombre_prov',
            ];
        }

        return [
            'lispm_nro',
            'lispm_proveedor',
            'lispm_fecha',
            'lispm_cond_entrega',
            'lispm_cond_pago',
            'lispm_cond_compra',
            'lispm_nombre_prov',
            'lispm_estado',
            'lispm_nombre_lista',
            'lispm_cod_mon',
            'lispm_usuario',
        ];
    }

    /**
     * @return list<string>
     */
    public static function camposMovimiento(): array
    {
        if (EntornoEmpresaSupport::esInterforming()) {
            return [
                'listpv_nro',
                'listpv_nro_orden',
                'listpv_fecha',
                'listpv_articulo',
                'listpv_precio',
                'listpv_proveedor',
            ];
        }

        return [
            'listpv_nro',
            'listpv_nro_orden',
            'listpv_fecha',
            'listpv_articulo',
            'listpv_precio',
            'listpv_proveedor',
            'lispv_desc',
            'lispv_art_prov',
            'lispv_descuento',
        ];
    }

    public static function sqlCamposCabecera(): string
    {
        return implode(",\n                ", self::camposCabecera());
    }

    public static function sqlCamposMovimiento(): string
    {
        return implode(",\n                    ", self::camposMovimiento());
    }
}
