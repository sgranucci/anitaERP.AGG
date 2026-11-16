<?php

return [
    /*
    | Web Push (PWA / navegador). En iOS requiere app agregada a pantalla de inicio (Safari 16.4+).
    | Generar claves: php -r "require 'vendor/autoload.php'; print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
    */
    'habilitado' => filter_var(env('WEB_PUSH_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'vapid' => [
        'subject' => env('WEB_PUSH_VAPID_SUBJECT', 'mailto:soporte@anitaerp.local'),
        'public_key' => env('WEB_PUSH_VAPID_PUBLIC_KEY', ''),
        'private_key' => env('WEB_PUSH_VAPID_PRIVATE_KEY', ''),
    ],

    'ttl' => max(60, (int) env('WEB_PUSH_TTL', 86400)),
];
