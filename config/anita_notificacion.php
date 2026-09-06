<?php

/*
|--------------------------------------------------------------------------
| Centro de avisos in-app (campanita) + fan-out opcional Slack/Teams
|--------------------------------------------------------------------------
|
| In-app: siempre disponible si existe la tabla anita_notificacion.
| Webhooks: APAGADOS por default. No activar hasta tener URLs de Workflows
| (Teams) / Incoming Webhook (Slack). Los Connectors clásicos de Teams
| (outlook.office.com/webhook/) ya no entregan mensajes.
|
*/

return [
    /*
    | Master switch del centro de avisos in-app.
    | false = crear()/avisar* no escriben (útil en staging o mientras se estabiliza).
    */
    'habilitado' => filter_var(env('ANITA_NOTIFICACION_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Productores fuera del árbol (tickets, legajos OC, etc.).
    | false = solo siguen avisando aprobación + digest (comportamiento mínimo).
    */
    'productores_extra' => filter_var(env('ANITA_NOTIFICACION_PRODUCTORES_EXTRA', true), FILTER_VALIDATE_BOOLEAN),

    'retencion' => [
        // Avisos ya leídos más viejos que N días se borran.
        'dias_leidas' => max(1, (int) env('ANITA_NOTIFICACION_RETENCION_DIAS_LEIDAS', 90)),
        // Avisos sin leer más viejos que N días también se borran (0 = no tocar no leídos).
        'dias_no_leidas' => max(0, (int) env('ANITA_NOTIFICACION_RETENCION_DIAS_NO_LEIDAS', 180)),
        'hora_purge' => env('ANITA_NOTIFICACION_PURGE_HORA', '03:45'),
        'habilitado' => filter_var(env('ANITA_NOTIFICACION_PURGE_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'webhooks' => [
        // Mantener en false hasta configurar canales reales.
        'habilitado' => filter_var(env('ANITA_NOTIFICACION_WEBHOOKS_HABILITADO', env('ARBOLAPROBACION_WEBHOOKS_HABILITADO', false)), FILTER_VALIDATE_BOOLEAN),
        // Fallback global (canal compartido = ruido; preferir por_usuario).
        'slack_url' => env('ANITA_NOTIFICACION_SLACK_WEBHOOK_URL', env('ARBOLAPROBACION_SLACK_WEBHOOK_URL', '')),
        'teams_url' => env('ANITA_NOTIFICACION_TEAMS_WEBHOOK_URL', env('ARBOLAPROBACION_TEAMS_WEBHOOK_URL', '')),
        /*
        | Override por usuario_id → canal propio (DM / privado).
        | También acepta JSON en ANITA_NOTIFICACION_WEBHOOKS_POR_USUARIO:
        | {"12":{"slack_url":"https://...","teams_url":"https://..."}}
        */
        'por_usuario' => [],
    ],
];
