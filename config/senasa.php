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

];
