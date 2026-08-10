<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Escritura Anita (create/update/delete)
    |--------------------------------------------------------------------------
    | Temporal: mientras el pago no se haga desde Ingreso/Egreso en ERP.
    | Poner false para dejar de replicar CUD a che_ban.
    */
    'anita_escritura' => filter_var(env('SOLICITUDPAGO_ANITA_ESCRITURA', true), FILTER_VALIDATE_BOOLEAN),

    'anita_sistema' => env('SOLICITUDPAGO_ANITA_SISTEMA', 'che_ban'),

    /*
    | Días de anticipación del job de cuotas (Anita p-controlsolpm: +6).
    */
    'cuotas_dias_anticipacion' => (int) env('SOLICITUDPAGO_CUOTAS_DIAS_ANTICIPACION', 6),

    /*
    | Schedule solicitudpago:generar-cuotas (Kernel).
    | Dejar habilitado=false hasta poner en marcha el cron en el servidor.
    | Horarios: lista CSV HH:MM (ej. 08:00,14:00,18:00).
    */
    'generar_cuotas' => [
        'habilitado' => filter_var(env('SOLICITUDPAGO_GENERAR_CUOTAS_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'horarios' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SOLICITUDPAGO_GENERAR_CUOTAS_HORARIOS', '08:00,14:00,18:00'))
        ))),
    ],

    /*
    | Sync diario Anita→ERP (faltantes + estados). Temporal mientras se paguen SP en Anita.
    | Apagar (habilitado=false) cuando el circuito de pago quede 100% en ERP.
    */
    'sync_anita' => [
        'habilitado' => filter_var(env('SOLICITUDPAGO_SYNC_ANITA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('SOLICITUDPAGO_SYNC_ANITA_HORA', '06:45'),
    ],

    /*
    | Disparar árbol de aprobación al generar SP hija por cuota (Anita llama SOLPM_procesa_arbol).
    | Default false: la hija nace AUTORIZADA como en p-controlsolpm.
    */
    'arbol_al_generar_cuota' => filter_var(env('SOLICITUDPAGO_ARBOL_AL_GENERAR_CUOTA', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Disparar árbol al crear SP manual en estado EMITIDA.
    */
    'arbol_al_crear' => filter_var(env('SOLICITUDPAGO_ARBOL_AL_CREAR', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Archivos adjuntos: mount compartido Anita (/scan/compras/sol_files).
    | Disco: SOLP-{codigo}.{nombre}. No copiar al storage del ERP.
    */
    'archivos' => [
        'disk' => env('SOLICITUDPAGO_ARCHIVOS_DISK', 'solicitudpago_scan'),
        'root' => env('SOLICITUDPAGO_ARCHIVOS_ROOT', '/scan/compras/sol_files'),
        'prefijo' => env('SOLICITUDPAGO_ARCHIVOS_PREFIJO', 'SOLP-'),
    ],
];
