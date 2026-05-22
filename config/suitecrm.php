<?php

/**
 * Integración SuiteCRM 8 — notas de cuentas (Accounts) enlazadas al CRUD de clientes Anita.
 *
 * Credenciales de BD: archivo legacy de SuiteCRM (config.php).
 */
return [
    /**
     * Master switch: sin esto no se muestra la solapa ni se accede a la API.
     */
    'habilitado' => (static function (): bool {
        $env = env('SUITECRM_HABILITADO', false);
        if (is_bool($env)) {
            return $env;
        }

        return filter_var($env, FILTER_VALIDATE_BOOLEAN)
            || (string) $env === '1'
            || strtolower(trim((string) $env)) === 'true';
    })(),

    /**
     * Ruta al config.php legacy de SuiteCRM (dbconfig).
     */
    'legacy_config_path' => env(
        'SUITECRM_LEGACY_CONFIG_PATH',
        '/var/www/html/suitcrm8/public/legacy/config.php'
    ),

    /**
     * Usuario SuiteCRM (UUID en tabla users) para created_by / modified_user_id al grabar desde Anita.
     */
    'default_user_id' => env('SUITECRM_DEFAULT_USER_ID', '1'),

    /**
     * Nombre del rol en acl_roles (SuiteCRM) cuyas notas son de visualización restringida.
     * Ej.: usuario mgomez con rol "Supervisor".
     */
    'supervisor_rol_nombre' => env('SUITECRM_SUPERVISOR_ROL_NOMBRE', 'Supervisor'),
];
