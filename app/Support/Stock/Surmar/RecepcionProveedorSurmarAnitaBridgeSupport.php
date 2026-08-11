<?php

namespace App\Support\Stock\Surmar;

/**
 * Bridge HTTP Anita Surmar para recepmae/recepmov.
 * Textos/char con posible "|" van al final del SELECT (regla bridge CSV).
 */
final class RecepcionProveedorSurmarAnitaBridgeSupport
{
    /**
     * @return array{sistema: string, path_sistema: string}
     */
    public static function parametrosBridge(): array
    {
        $path = rtrim((string) config(
            'recepcion_anita_surmar.path_sistema',
            config('anita.surmar_path', '/usr2/surmar')
        ), '/');
        $sistema = trim((string) config('recepcion_anita_surmar.sistema_compras', 'compras'));

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
        return implode(',', [
            'recm_proveedor',
            'recm_tipo',
            'recm_letra',
            'recm_sucursal',
            'recm_nro',
            'recm_fecha',
            'recm_estado',
            'recm_fe_ult_act',
            'recm_empresa',
            'recm_tipo_fac',
            'recm_letra_fac',
            'recm_sucursal_fac',
            'recm_nro_fac',
            'recm_ref_tipo',
            'recm_ref_letra',
            'recm_ref_sucursal',
            'recm_ref_nro',
            'recm_cond_entrega',
            // char / posibles "|" al final
            'recm_usuario',
            'recm_terminal',
            'recm_observacion',
            'recm_cod_remito',
        ]);
    }

    public static function camposLinea(): string
    {
        return implode(',', [
            'recv_proveedor',
            'recv_tipo',
            'recv_letra',
            'recv_sucursal',
            'recv_nro',
            'recv_orden',
            'recv_articulo',
            'recv_agrupacion',
            'recv_unidad_medida',
            'recv_cantidad',
            'recv_cantrech',
            'recv_cantfact',
            'recv_precio',
            'recv_dto_art',
            'recv_deposito',
            'recv_tipo_iva',
            'recv_fecha',
            'recv_incl_impuesto',
            'recv_cod_mon',
            'recv_partida',
            'recv_ccosto',
            'recv_fl_cerrada',
            'recv_empresa',
            'recv_cotizacion',
            'recv_fecha_vto',
            'recv_peso_unitario',
            'recv_nro_interno',
            'recv_tropa',
            'recv_temp_ingreso',
            'recv_camara',
            'recv_total_peso',
            'recv_nro_establ',
            // char / posibles "|" al final
            'recv_destino',
            'recv_desc',
            'recv_motivo_rech',
            'recv_lote_proveed',
            'recv_certificado',
        ]);
    }

    public static function tipo(): string
    {
        return strtoupper(trim((string) config('recepcion_anita_surmar.tipo', 'COM'))) ?: 'COM';
    }

    public static function letra(): string
    {
        return strtoupper(trim((string) config('recepcion_anita_surmar.letra', 'D'))) ?: 'D';
    }
}
