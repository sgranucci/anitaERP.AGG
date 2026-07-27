<?php

/*
|--------------------------------------------------------------------------
| Ingesta de facturas de proveedor por correo (canal Document AI)
|--------------------------------------------------------------------------
| Un comando programado lee la casilla configurada, extrae la OC del
| asunto/cuerpo del mail y corre el pipeline PDF+IA existente para dejar
| la precarga en la grilla con el PDF guardado en Facturas_scan.
|
| Driver: "imap" (webklex/php-imap, usuario + password). La interfaz
| MailboxLectorInterface permite sumar un driver "graph" a futuro.
*/

return [

    // Prende/apaga el schedule de ingesta (el comando manual siempre corre).
    'habilitada' => (bool) env('PRECARGA_MAIL_INGESTA_HABILITADA', false),

    // Driver de lectura de casilla: imap (futuro: graph).
    'driver' => env('PRECARGA_MAIL_DRIVER', 'imap'),

    'imap' => [
        'host' => env('PRECARGA_MAIL_HOST', 'outlook.office365.com'),
        'port' => (int) env('PRECARGA_MAIL_PORT', 993),
        // ssl | tls | starttls | false
        'encryption' => env('PRECARGA_MAIL_ENCRIPTACION', 'ssl'),
        'validate_cert' => (bool) env('PRECARGA_MAIL_VALIDAR_CERT', true),
        // Vacío en .env → reutiliza las credenciales SMTP del ERP
        'username' => env('PRECARGA_MAIL_USUARIO') ?: env('MAIL_USERNAME'),
        'password' => env('PRECARGA_MAIL_PASSWORD') ?: env('MAIL_PASSWORD'),
    ],

    // Carpeta de entrada y carpetas destino tras procesar.
    'carpeta' => env('PRECARGA_MAIL_CARPETA', 'INBOX'),
    'carpeta_procesados' => env('PRECARGA_MAIL_CARPETA_PROCESADOS', 'Facturas procesadas'),
    'carpeta_errores' => env('PRECARGA_MAIL_CARPETA_ERRORES', 'Facturas con error'),

    // Máximo de mensajes por corrida (evita corridas eternas tras vacaciones).
    'max_mensajes' => (int) env('PRECARGA_MAIL_MAX_MENSAJES', 25),

    // Intervalo del schedule en minutos.
    'intervalo_minutos' => max(1, (int) env('PRECARGA_MAIL_INTERVALO_MIN', 5)),

    'aviso_errores' => [
        'habilitado' => (bool) env('PRECARGA_MAIL_AVISO_ERRORES_HABILITADO', true),
        // Coma-separado para varios destinatarios.
        'destinatarios' => env('PRECARGA_MAIL_AVISO_ERRORES_A', 'sergiogranucci@gmail.com'),
    ],

    /*
    | Filtro de candidato (casilla personal / label Gmail).
    | Además de exigir PDF, el mail debe traer OC en asunto/cuerpo/nombre
    | o alguna palabra clave. Si no: se omite sin marcar leído ni mover.
    */
    'filtro_candidato' => [
        'habilitado' => (bool) env('PRECARGA_MAIL_FILTRO_CANDIDATO', true),
        // Coma-separado; match case-insensitive en asunto + cuerpo + nombres PDF + remitente.
        'palabras' => env(
            'PRECARGA_MAIL_FILTRO_PALABRAS',
            'factura,facturas,comprobante,orden de compra,o.c.,oc ,fc-,fa-,remito'
        ),
    ],

];
