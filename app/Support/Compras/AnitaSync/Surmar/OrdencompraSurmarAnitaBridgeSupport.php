<?php

namespace App\Support\Compras\AnitaSync\Surmar;

/**
 * Parámetros de bridge HTTP para listados Anita Surmar (compras).
 */
final class OrdencompraSurmarAnitaBridgeSupport
{
    /**
     * @return array{sistema: string, path_sistema: string}
     */
    public static function parametrosBridge(): array
    {
        $path = rtrim((string) config(
            'ordencompra_anita_surmar.path_sistema',
            config('anita.surmar_path', '/usr2/surmar')
        ), '/');
        $sistema = trim((string) config('ordencompra_anita_surmar.sistema_compras', 'compras'));

        return [
            'sistema' => $sistema !== '' ? $sistema : 'compras',
            'path_sistema' => $path !== '' ? $path : '/usr2/surmar',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePayload(array $payload): array
    {
        return array_merge(self::parametrosBridge(), $payload);
    }

    public static function camposCabecera(): string
    {
        return implode(', ', [
            'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
            'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
            'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_razon_susp', 'penmp_cod_mon', 'penmp_cotizacion',
            'penmp_fecha_ing', 'penmp_hora_ing', 'penmp_estado', 'penmp_leyenda', 'penmp_requisicion',
        ]);
    }

    public static function camposLinea(): string
    {
        return implode(', ', [
            'penvp_proveedor', 'penvp_tipo', 'penvp_letra', 'penvp_sucursal', 'penvp_nro', 'penvp_orden',
            'penvp_articulo', 'penvp_desc', 'penvp_agrupacion', 'penvp_unidad_med', 'penvp_cantidad',
            'penvp_cantentr', 'penvp_cantfact', 'penvp_precio', 'penvp_dto_art', 'penvp_deposito',
            'penvp_tipo_iva', 'penvp_fecha', 'penvp_incl_imp', 'penvp_cod_mon', 'penvp_partida',
            'penvp_fecha_ent', 'penvp_ccosto', 'penvp_requisicion', 'penvp_empresa', 'penvp_nro_interno',
            'penvp_lote_transf', 'penvp_peso_unit', 'penvp_estado',
        ]);
    }
}
