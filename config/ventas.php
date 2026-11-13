<?php

return [

    /*
    | Auditor diario deuda vs ficha CC clientes (ventas:auditar-deuda-ficha-cliente).
    | Un mail por empresa; el asunto lleva el nombre de la empresa.
    | Sin diferencias: mail verde «todo cuadrado». Con diferencias: detalle cliente a cliente.
    | Además guarda el delta conjunto = deudores (Activo, sin medios de cobro) +
    | anticipos COA (`cliente.ANTICIPO_DE_CLIENTES`) y avisa si esa suma cambia.
    | Un pago de más (COA) no mueve el conjunto: baja en una cuenta y sube en la otra.
    | No graba ajustes: solo informa. La verdad operativa es la deuda.
    | empresas vacío = todas. En Crown: solo la empresa 1.
    */
    'deuda_ficha_auditor' => [
        'habilitada' => filter_var(env('VENTAS_DEUDA_FICHA_AUDITOR_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('VENTAS_DEUDA_FICHA_AUDITOR_HORA', '07:55'),
        'email' => env(
            'VENTAS_DEUDA_FICHA_AUDITOR_EMAIL',
            env('MAIL_FROM_ADDRESS', '')
        ),
        'tolerancia' => (float) env('VENTAS_DEUDA_FICHA_AUDITOR_TOLERANCIA', 0.05),
        'tolerancia_delta_mayor' => (float) env('VENTAS_DEUDA_FICHA_AUDITOR_TOLERANCIA_DELTA', 1),
        'expresion' => env('VENTAS_DEUDA_FICHA_AUDITOR_EXPRESION', 'origen'),
        'limite_filas_mail' => max(10, (int) env('VENTAS_DEUDA_FICHA_AUDITOR_LIMITE_MAIL', 120)),
        // true = también manda el verde cuando cuadra
        'mail_siempre' => filter_var(env('VENTAS_DEUDA_FICHA_AUDITOR_MAIL_SIEMPRE', true), FILTER_VALIDATE_BOOLEAN),
        // null = todas. Lista de ids = solo esas empresas.
        'empresas' => (static function () {
            $raw = env('VENTAS_DEUDA_FICHA_AUDITOR_EMPRESAS');
            if ($raw === null || trim((string) $raw) === '') {
                return null;
            }

            return array_values(array_unique(array_filter(array_map(
                static fn ($id) => (int) $id,
                explode(',', (string) $raw)
            ), static fn (int $id) => $id > 0)));
        })(),
    ],

];
