<?php

return [

    /*
    | Auditor diario deuda vs ficha CC clientes (ventas:auditar-deuda-ficha-cliente).
    | Un mail por empresa; el asunto lleva el nombre de la empresa.
    | Sin diferencias: mail verde «todo cuadrado». Con diferencias: detalle cliente a cliente.
    | No graba ajustes: solo informa. La verdad operativa es la deuda.
    */
    'deuda_ficha_auditor' => [
        'habilitada' => filter_var(env('VENTAS_DEUDA_FICHA_AUDITOR_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('VENTAS_DEUDA_FICHA_AUDITOR_HORA', '07:55'),
        'email' => env(
            'VENTAS_DEUDA_FICHA_AUDITOR_EMAIL',
            env('MAIL_FROM_ADDRESS', '')
        ),
        'tolerancia' => (float) env('VENTAS_DEUDA_FICHA_AUDITOR_TOLERANCIA', 0.05),
        'expresion' => env('VENTAS_DEUDA_FICHA_AUDITOR_EXPRESION', 'origen'),
        'limite_filas_mail' => max(10, (int) env('VENTAS_DEUDA_FICHA_AUDITOR_LIMITE_MAIL', 120)),
        // true = también manda el verde cuando cuadra
        'mail_siempre' => filter_var(env('VENTAS_DEUDA_FICHA_AUDITOR_MAIL_SIEMPRE', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
