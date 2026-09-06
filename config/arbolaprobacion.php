<?php

// Constantes de arbol de aprobacion / Mis aprobaciones
// Base absoluta de enlaces en mails (debe incluir esquema http:// o https://).

return [
    'ip_link' => env('ARBOLAPROBACION_IP_LINK', 'http://10.20.30.210'),

    /*
    |--------------------------------------------------------------------------
    | Modelo de seguridad (canales de aprobación) — contrato vigente
    |--------------------------------------------------------------------------
    |
    | 1) Bandeja autenticada (canónico in-app)
    |    - Rutas bajo mis-aprobaciones/* con middleware auth + permisos slug.
    |    - Aprobar/rechazar exige sesión y ownership (firmante o reemplazo).
    |    - Observación queda etiquetada [vía bandeja] para auditoría.
    |
    | 2) Portal por hash (mail / enlace público)
    |    - Rutas arbolaprobacion/aprobar|rechazar/{tipo}/{id}/{hash}.
    |    - El hash (bcrypt normalizado) es la credencial del paso; no requiere login.
    |    - First-wins: un firmante del nivel cierra; el resto queda Sin efecto.
    |    - Observación queda etiquetada [vía enlace].
    |
    | 3) Otros módulos (asiento / salida / TM / ingreso)
    |    - Entran a Mis aprobaciones vía UserTaskBandejaService.
    |    - La acción real sigue en el servicio de dominio con su propio permiso.
    |    - No usan hash de árbol; usan sesión + regla de negocio del módulo.
    |
    | Regla de producto: el mail AVISA; la bandeja es el hogar de trabajo.
    | Ambos canales son válidos y quedan auditables por la etiqueta de vía.
    |
    */
    'canales' => [
        'bandeja' => 'sesion+permiso',
        'enlace' => 'hash-portal',
        'dominio' => 'sesion+permiso-modulo',
    ],

    // Cron: recordatorios individuales (ABM recordatorio=S).
    'recordatorio_hora' => env('ARBOLAPROBACION_RECORDATORIO_HORA', '09:00'),

    // Cron: digest matutino por firmante (resumen de pendientes).
    'digest' => [
        'habilitado' => filter_var(env('ARBOLAPROBACION_DIGEST_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'hora' => env('ARBOLAPROBACION_DIGEST_HORA', '08:30'),
    ],

    // Cron: restaura reemplazos con vence_el vencido (día siguiente al último día inclusive).
    'reemplazo_firmante_vencidos' => [
        'hora' => env('ARBOLAPROBACION_REEMPLAZO_VENCIDOS_HORA', '00:05'),
    ],

    // Bulk approve desde Mis aprobaciones (reglas estrictas).
    'bulk' => [
        'max_items' => (int) env('ARBOLAPROBACION_BULK_MAX', 20),
        'monto_max_arbol' => (float) env('ARBOLAPROBACION_BULK_MONTO_MAX', 100000),
        // Fuentes permitidas además del árbol (mismo tipo obligatorio solo en árbol).
        'fuentes' => ['arbol', 'indumentaria', 'salida_bienes', 'asiento', 'transferencia', 'ingreso_proveedor'],
    ],

    /*
    | Fan-out Slack/Teams: canónico en config/anita_notificacion.php (webhooks).
    | Se mantienen estas claves por compatibilidad con .env viejos; el servicio
    | lee anita_notificacion primero y cae acá solo si el master está apagado allá.
    | Default: inactivo.
    */
    'webhooks' => [
        'habilitado' => filter_var(env('ARBOLAPROBACION_WEBHOOKS_HABILITADO', false), FILTER_VALIDATE_BOOLEAN),
        'slack_url' => env('ARBOLAPROBACION_SLACK_WEBHOOK_URL', ''),
        'teams_url' => env('ARBOLAPROBACION_TEAMS_WEBHOOK_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Artículos (tipo AR) — opt-in
    |--------------------------------------------------------------------------
    | Flag: articulo.aprobacion_alta.habilitado / ARTICULO_APROBACION_ALTA.
    | Entra a Mis aprobaciones vía movimientos de árbol (fuente arbol), no
    | requiere FUENTE_ARTICULO aparte. Tabla user_task persistida: diferida.
    */
];
