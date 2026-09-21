<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facturación Local (Ferli mostrador multi-local)
    |--------------------------------------------------------------------------
    | Sin failover CAEA. Canal LOCAL. Turno sin jornada.
    */

    'canal_codigo' => env('FACTURACION_LOCAL_CANAL_CODIGO', 'LOCAL'),

    'usocuentacaja_nombre' => env('FACTURACION_LOCAL_USO_CUENTACAJA', 'Local'),

    // Opcional: forzar id de usocuentacaja (si vacío resuelve por nombre).
    'usocuentacaja_id' => env('FACTURACION_LOCAL_USO_CUENTACAJA_ID'),

    'tipotransaccion_fac_id' => (int) env('FACTURACION_LOCAL_TIPO_FAC_ID', 1),

    'tipotransaccion_nc_id' => (int) env('FACTURACION_LOCAL_TIPO_NC_ID', 2),

    // IDs de tabla tipotransaccion_caja (Cobranza / Devolución), no de tipotransaccion FAC/NC.
    'tipotransaccion_caja_id' => (int) env('FACTURACION_LOCAL_TIPO_CAJA_ID', 12),

    'tipotransaccion_caja_devolucion_id' => (int) env('FACTURACION_LOCAL_TIPO_CAJA_DEVOLUCION_ID', 14),

    'moneda_id' => (int) env('FACTURACION_LOCAL_MONEDA_ID', 1),

    'cliente_contado_id' => (int) env('FACTURACION_LOCAL_CLIENTE_CONTADO_ID', 1),

    // Cliente RI interno (shell) para Factura A eventual: datos van en venta, no se crea cliente.
    'cliente_ri_id' => (int) env('FACTURACION_LOCAL_CLIENTE_RI_ID', 0),

    // Límites AFIP identificación (actualizar por .env; no hardcode legacy 1999)
    'limite_efectivo' => (float) env('FACTURACION_LOCAL_LIMITE_EFECTIVO', 200000),

    'limite_resto' => (float) env('FACTURACION_LOCAL_LIMITE_RESTO', 400000),

    'anita_servidor_default' => env('FACTURACION_LOCAL_ANITA_SERVIDOR', 'LOCAL_IP'),

    'anita_ifx_server_default' => env('FACTURACION_LOCAL_ANITA_IFX', 'IFX_SERVER_LOCAL'),

    /*
    | Mapeo stkp_lista Anita Local → listaprecio.codigo ERP.
    | Formato env: 5:11,6:12,50:13
    */
    'lista_precio_mapeo' => (static function (): array {
        $raw = trim((string) env('FACTURACION_LOCAL_LISTA_PRECIO_MAPEO', '5:11,6:12,50:13'));
        $out = [];
        foreach (explode(',', $raw) as $par) {
            $par = trim($par);
            if ($par === '' || ! str_contains($par, ':')) {
                continue;
            }
            [$origen, $destino] = array_map('trim', explode(':', $par, 2));
            if ($origen === '' || $destino === '') {
                continue;
            }
            $out[$origen] = $destino;
        }

        return $out !== [] ? $out : ['5' => '11', '6' => '12', '50' => '13'];
    })(),

    /*
    | Nombres al crear cabeceras ERP faltantes (código ERP => nombre).
    | Formato env: 11:WEB,12:OFERTA WEB,13:LUGANO
    */
    'lista_precio_nombres_erp' => (static function (): array {
        $raw = trim((string) env('FACTURACION_LOCAL_LISTA_PRECIO_NOMBRES', '11:WEB,12:OFERTA WEB,13:LUGANO'));
        $out = [];
        foreach (explode(',', $raw) as $par) {
            $par = trim($par);
            if ($par === '' || ! str_contains($par, ':')) {
                continue;
            }
            [$codigo, $nombre] = array_map('trim', explode(':', $par, 2));
            if ($codigo === '' || $nombre === '') {
                continue;
            }
            $out[$codigo] = $nombre;
        }

        return $out !== [] ? $out : [
            '11' => 'WEB',
            '12' => 'OFERTA WEB',
            '13' => 'LUGANO',
        ];
    })(),

    'precio_anita_sync_desde' => env('FACTURACION_LOCAL_PRECIO_ANITA_SYNC_DESDE', env('STOCK_PRECIO_ANITA_SYNC_DESDE', '20250101')),

    /*
    | Medio puente para cambios/devoluciones marketplace (Facturación Local Ferli).
    | Cuentacaja «Aplicación de crédito local» — no usar en gastronomía AGG.
    */
    'cambio_devolucion_puente_cuentacaja_codigo' => env(
        'FACTURACION_LOCAL_CAMBIO_DEVOLUCION_PUENTE_CODIGO',
        '1131009'
    ),

    'permitir_caea' => false,

    // Preview próximo número en POS (FECompUltimoAutorizado). Segundos.
    'preview_arca_soap_timeout' => max(5, (int) env('FACTURACION_LOCAL_PREVIEW_ARCA_SOAP_TIMEOUT', 10)),

    'genera_contabilidad_cobranza' => (bool) env('FACTURACION_LOCAL_GENERA_CONTABILIDAD_COBRANZA', false),

    /*
    | Reportes Local — valorización al costo (opcional en pantalla).
    | Fuente operativa: tabla facturacion_local_parametro (pantalla Parámetros).
    | Estos env solo siembran la migración y hacen de fallback si la BD no tiene fila.
    | Costo = precio venta fábrica × (1 − descuento%/100).
    | Listas fábrica por defecto: códigos 1–5. costo_listaprecio_codigo fuerza una sola lista.
    */
    'costo_descuento_pct' => (float) env('FACTURACION_LOCAL_COSTO_DESCUENTO_PCT', 67),

    'costo_listaprecio_codigo' => trim((string) env('FACTURACION_LOCAL_COSTO_LISTAPRECIO_CODIGO', '')),

    /** Fallback códigos listaprecio fábrica. Formato env: 1,2,3,4,5 */
    'costo_listas_fabrica_codigos' => (static function (): array {
        $raw = trim((string) env('FACTURACION_LOCAL_COSTO_LISTAS_FABRICA', '1,2,3,4,5'));
        $out = [];
        foreach (explode(',', $raw) as $codigo) {
            $codigo = trim($codigo);
            if ($codigo === '') {
                continue;
            }
            $out[] = $codigo;
        }

        return $out !== [] ? array_values(array_unique($out)) : ['1', '2', '3', '4', '5'];
    })(),
];
