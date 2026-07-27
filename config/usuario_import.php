<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dominio de email para carga masiva de usuarios
    |--------------------------------------------------------------------------
    |
    | Si el Excel no trae email, se genera login@dominio.
    | Acepta con o sin @ inicial (ej. grupoagg.com o @grupoagg.com).
    |
    */
    'dominio_email' => env('USUARIO_IMPORT_DOMINIO_EMAIL', '@grupoagg.com'),

    /*
    | Por defecto, si falta login/email en la fila se generan desde el nombre.
    | En pantalla se puede desactivar por importación.
    */
    'generar_login_si_falta' => (bool) env('USUARIO_IMPORT_GENERAR_LOGIN', true),
    'generar_email_si_falta' => (bool) env('USUARIO_IMPORT_GENERAR_EMAIL', true),
];
