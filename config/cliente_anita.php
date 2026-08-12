<?php

/**
 * Importación Anita (Informix) → cliente del ERP.
 * Dirección: Anita es la fuente de verdad; el comando cliente:sincronizar-anita actualiza anitaERP.
 * Escritura ERP → Anita: ANITA_SYNC_CLIENTE_WRITE en .env (config app.anita_sync_cliente_write).
 *
 * Tabla origen: climae (sistema ventas).
 */
return [
    'tabla' => 'climae',

    'sistema' => env('CLIENTE_SYNC_ANITA_SISTEMA', 'ventas'),

    'key_field_anita' => 'clim_cliente',

    /**
     * Columnas del listado masivo (solo código para recorrer todos los clientes).
     */
    'campos_listado' => env('CLIENTE_SYNC_ANITA_CAMPOS_LISTADO')
        ?: 'clim_cliente as codigo, clim_cliente',

    /*
     * Alta cliente EL BIERZO: numerador t_comp CLI → numerador (num_ult_numero).
     * Si el código propuesto existe en ERP o climae, se incrementa hasta hallar uno libre.
     */
    'numeracion' => [
        'habilitada' => filter_var(env('CLIENTE_ANITA_NUMERACION_BIERZO', true), FILTER_VALIDATE_BOOLEAN),
        't_comp_clave' => env('CLIENTE_ANITA_T_COMP_CLAVE', 'CLI'),
        'sistema_t_comp' => env('CLIENTE_ANITA_SISTEMA_T_COMP', 'ventas'),
        'sistema_numerador' => env('CLIENTE_ANITA_SISTEMA_NUMERADOR', 'ventas'),
        'reintentos_bloqueo' => 6,
        'espera_reintento_ms' => 400,
    ],

    /*
     * EL BIERZO: clientes con clim_coef > 0 se replican también en Anita Villafranca (mismo patrón que venta).
     */
    'villafranca' => [
        'path_sistema' => env('CLIENTE_ANITA_VILLAFRANCA_PATH', '/usr2/villafranca'),
    ],

    /*
     * Fallback clim_zonamult si el bridge no devuelve zonamult (zonm_codjur → zonm_codigo).
     * Preferencia: lectura live de ventas.zonamult; este mapa solo si Anita no responde.
     */
    'zonamult_por_jurisdiccion' => [
        901 => 1,  // CABA
        902 => 2,  // PBA
        903 => 15, // Catamarca
        904 => 13, // Cordoba
        905 => 7,  // Corrientes
        906 => 6,  // Chaco
        907 => 5,  // Chubut
        908 => 16, // Entre Rios
        909 => 20, // Formosa
        910 => 24, // Jujuy
        911 => 9,  // La Pampa
        912 => 23, // La Rioja
        913 => 10, // Mendoza
        914 => 17, // Misiones
        915 => 4,  // Neuquen
        916 => 3,  // Rio Negro
        917 => 22, // Salta
        918 => 21, // San Juan
        919 => 11, // San Luis
        920 => 8,  // Santa Cruz
        921 => 14, // Santa Fe
        922 => 18, // Santiago del Estero
        923 => 12, // Tierra del Fuego
        924 => 19, // Tucuman
    ],

    /*
     * Si provincia.jurisdiccion está vacío/0 (maestro ERP incompleto), inferir jurisdicción AFIP
     * por nombre normalizado para clim_zonamult. Claves sin acentos, mayúsculas.
     */
    'jurisdiccion_por_nombre_provincia' => [
        'CAPITAL FEDERAL' => 901,
        'CABA' => 901,
        'CIUDAD AUTONOMA DE BUENOS AIRES' => 901,
        'CIUDAD DE BUENOS AIRES' => 901,
        'BUENOS AIRES' => 902,
        'CATAMARCA' => 903,
        'CORDOBA' => 904,
        'CORRIENTES' => 905,
        'CHACO' => 906,
        'CHUBUT' => 907,
        'ENTRE RIOS' => 908,
        'FORMOSA' => 909,
        'JUJUY' => 910,
        'LA PAMPA' => 911,
        'LA RIOJA' => 912,
        'MENDOZA' => 913,
        'MISIONES' => 914,
        'NEUQUEN' => 915,
        'RIO NEGRO' => 916,
        'SALTA' => 917,
        'SAN JUAN' => 918,
        'SAN LUIS' => 919,
        'SANTA CRUZ' => 920,
        'SANTA FE' => 921,
        'SANTIAGO DEL ESTERO' => 922,
        'TIERRA DEL FUEGO' => 923,
        'TUCUMAN' => 924,
    ],
];
