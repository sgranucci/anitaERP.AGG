<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grabación de navegación (bitácora de acceso)
    |--------------------------------------------------------------------------
    |
    | Desconectable sin redeploy de código: BITACORA_ACCESO_HABILITADO=false
    | + php artisan config:clear. La escritura es post-respuesta (terminate),
    | INSERT directo a MySQL — no usa la cola QUEUE_CONNECTION.
    |
    */

    'habilitado' => filter_var(env('BITACORA_ACCESO_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),

    /** Meses a retener filas antes de la purga agendada. */
    'retencion_meses' => max(1, (int) env('BITACORA_ACCESO_RETENCION_MESES', 12)),

    /** Capturar memoria pico PHP del request (memory_get_peak_usage). */
    'registrar_memoria' => filter_var(env('BITACORA_ACCESO_REGISTRAR_MEMORIA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Prefijos/paths exactos a excluir (ruido AJAX / infraestructura UI).
     * Comparación sobre el path relativo a APP_CARPETA (sin slash inicial).
     */
    'excluir_paths' => [
        'csrf-token',
        'ajax-sesion',
        'seguridad/barra-tareas',
        'seguridad/barra-tareas/menus',
        'seguridad/barra-tareas/anclar',
        'seguridad/barra-tareas/desanclar',
        'seguridad/barra-tareas/reordenar',
        'configuracion/auditoria-sesiones/favoritos',
        'configuracion/auditoria-sesiones/favoritos/anclar',
        'configuracion/auditoria-sesiones/favoritos/desanclar',
        'configuracion/auditoria-sesiones/buscar-registro',
    ],

    /** Si el path contiene alguno de estos fragmentos, no se registra. */
    'excluir_path_contiene' => [
        '/consulta',
        'consultausuario',
        'consultadeposito',
        'consultaarticulo',
        'consultaproveedor',
        'consultacliente',
        'consultacentrocosto',
        'consultaprovincia',
        'consultalocalidad',
        'consultalistaprecio',
        'leerunusuario',
        'leerusuario',
        'resolverusuario',
        '_debugbar',
        'telescope',
        'horizon',
    ],

    /** Extensiones / prefijos de assets por si alguna request llega a Laravel. */
    'excluir_extensiones' => ['js', 'css', 'map', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot'],

];
