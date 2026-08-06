<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filtro por oficina de compras (usuario)
    |--------------------------------------------------------------------------
    |
    | Si es true, el listado y la comprobación de acceso por requisición
    | restringen según oficinacompra_id del usuario, y en estado EN_COMPRAS
    | solo puede intervenir quien coincida en oficina.
    |
    */
    'filtro_oficina_compras_activo' => filter_var(
        env('REQUISICION_FILTRO_OFICINA_COMPRAS_ACTIVO', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Tablero seguimiento aprobación
    |--------------------------------------------------------------------------
    |
    | Horas en el nivel actual (o desde creación si aún no hay envío) a partir
    | de las cuales la fila se marca con alerta de demora.
    |
    */
    'seguimiento_aprobacion_alerta_horas' => (int) env('REQUISICION_SEGUIMIENTO_ALERTA_HORAS', 48),

    /*
    | Numeración Anita: shared numabm código 21 (a-reqmae.c / compras / referencia 1).
    | Misma lógica que recepción COM: max(ERP, reqmae, numabm) + hueco libre.
    */
    'anita' => [
        'sistema_compras' => env('REQUISICION_ANITA_SISTEMA', 'compras'),
        'sistema_shared' => env('REQUISICION_ANITA_SISTEMA_SHARED', 'shared'),
        'tabla_cabecera' => 'reqmae',
        'numerador' => [
            'tabla' => 'numabm',
            'codigo' => (int) env('REQUISICION_ANITA_NUMA_CODIGO', 21),
            'programa' => env('REQUISICION_ANITA_NUMA_PROGRAMA', 'a-reqmae.c'),
            'sistema_abm' => env('REQUISICION_ANITA_NUMA_SISTEMA', 'compras'),
            'referencia' => env('REQUISICION_ANITA_NUMA_REFERENCIA', '1'),
        ],
        // false: solo actualizar numabm al asignar número en ERP (como COM por defecto)
        'reservar_numerador_anita' => filter_var(
            env('REQUISICION_ANITA_RESERVAR_NUMERADOR', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        // numabm shared referencia 2: reqv_nro_interno (a-reqmae.c lee_numabm 2L)
        'numerador_linea_interno' => [
            'referencia' => env('REQUISICION_ANITA_NUMA_REF_LINEA', '2'),
            'programa' => env('REQUISICION_ANITA_NUMA_PROGRAMA', 'a-reqmae.c'),
            'sistema_abm' => env('REQUISICION_ANITA_NUMA_SISTEMA', 'compras'),
        ],
        // Si false, no escribe reqmae/reqmov/reqmref al grabar (solo ERP)
        'sync_activo' => filter_var(
            env('REQUISICION_ANITA_SYNC_ACTIVO', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
