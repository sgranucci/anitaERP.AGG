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

    'tipotransaccion_caja_id' => (int) env('FACTURACION_LOCAL_TIPO_CAJA_ID', 1),

    'tipotransaccion_caja_devolucion_id' => (int) env('FACTURACION_LOCAL_TIPO_CAJA_DEVOLUCION_ID', 3),

    'moneda_id' => (int) env('FACTURACION_LOCAL_MONEDA_ID', 1),

    'cliente_contado_id' => (int) env('FACTURACION_LOCAL_CLIENTE_CONTADO_ID', 1),

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

    'permitir_caea' => false,

    'genera_contabilidad_cobranza' => (bool) env('FACTURACION_LOCAL_GENERA_CONTABILIDAD_COBRANZA', false),
];
