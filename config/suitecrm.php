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

    /**
     * Envío semanal PDF «Auditoría de notas CRM» (Interforming: viernes 08:00).
     * Destinatarios gerenciales; incluye notas de supervisor.
     */
    'auditoria_semanal' => [
        'habilitada' => filter_var(env('SUITECRM_AUDITORIA_SEMANAL_HABILITADA', true), FILTER_VALIDATE_BOOLEAN),
        /** 0=domingo … 5=viernes (Laravel Schedule::weeklyOn) */
        'dia' => max(0, min(6, (int) env('SUITECRM_AUDITORIA_SEMANAL_DIA', 5))),
        'hora' => (string) env('SUITECRM_AUDITORIA_SEMANAL_HORA', '08:00'),
        /** Días inclusive hasta hoy (7 = últimos siete días). */
        'ventana_dias' => max(1, (int) env('SUITECRM_AUDITORIA_SEMANAL_VENTANA_DIAS', 7)),
        'emails' => (string) env(
            'SUITECRM_AUDITORIA_SEMANAL_EMAILS',
            'rmaceri@interforming.com.ar,fimaceri@interforming.com.ar,famaceri@pcomahue.com.ar,lmaceri@pcomahue.com.ar,mviviani@interforming.com.ar'
        ),
        'enviar_si_vacio' => filter_var(env('SUITECRM_AUDITORIA_SEMANAL_ENVIAR_SI_VACIO', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
