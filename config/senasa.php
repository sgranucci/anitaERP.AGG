<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificado sanitario WEB (SIGCER / solicitudCertCarnicos)
    |--------------------------------------------------------------------------
    | Valores tomados del generador Anita certsan.fc (establecimiento 1154).
    */

    'establecimiento' => (int) env('SENASA_ESTABLECIMIENTO', 1154),

    'pais_destino' => (int) env('SENASA_PAIS_DESTINO', 9),

    'version_solicitud' => env('SENASA_VERSION_SOLICITUD', '1.0.1'),

    'tipo_transporte' => env('SENASA_TIPO_TRANSPORTE', 'TE'),

    'termoproceso_temperatura' => (float) env('SENASA_TERMOPROCESO_TEMPERATURA', 75),

    'termoproceso_tiempo' => (float) env('SENASA_TERMOPROCESO_TIEMPO', 20),

    'rol_establecimiento' => env('SENASA_ROL_ESTABLECIMIENTO', 'ELABORADOR'),

    'atributo_calidad' => env('SENASA_ATRIBUTO_CALIDAD', '2'),

    'cod_envase_secundario' => env('SENASA_COD_ENVASE_SECUNDARIO', '7'),

    'origen_default' => env('SENASA_ORIGEN_DEFAULT', 'CAPITAL FEDERAL'),

    'procedencia_default' => env('SENASA_PROCEDENCIA_DEFAULT', 'CAPITAL FEDERAL'),

    /*
    | Directorio relativo a storage/app para los XML generados.
    */
    'xml_storage_path' => env('SENASA_XML_STORAGE_PATH', 'senasa/certificados'),

    /*
    | Si true, además del ERP consulta Anita pendmae/pendmov para completar pedidos faltantes.
    */
    'fallback_anita_pedido' => (bool) env('SENASA_FALLBACK_ANITA_PEDIDO', true),

    /*
    | Días hacia atrás para buscar el último amparo (certart.certa_cert_terc) en Anita
    | cuando el producto es de otro establecimiento.
    */
    'origen_dias_busqueda' => (int) env('SENASA_ORIGEN_DIAS_BUSQUEDA', 45),

    /*
    | Numeración como p-certsan.c (Anita). true = SER + numabm + CSI/CSP.
    | false = correlativo propio del ERP (cuando se deje de numerar en Anita).
    */
    'numeracion_anita' => [
        'habilitada' => filter_var(env('SENASA_NUMERACION_ANITA', true), FILTER_VALIDATE_BOOLEAN),
        'sistema_ventas' => env('SENASA_ANITA_SISTEMA_VENTAS', 'ventas'),
        'sistema_shared' => env('SENASA_ANITA_SISTEMA_SHARED', 'shared'),
        'numabm_programa' => env('SENASA_ANITA_NUMA_PROGRAMA', 'p-certsan.c'),
        'numabm_sistema' => env('SENASA_ANITA_NUMA_SISTEMA', 'ventas'),
        'tope_por_serie' => (int) env('SENASA_ANITA_TOPE_SERIE', 10000),
    ],

];
