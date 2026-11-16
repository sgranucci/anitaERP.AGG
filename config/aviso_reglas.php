<?php

/*
| Motor de reglas de aviso (WHEN / IF / THEN).
| Interruptor fino: tabla aviso_regla_config.motor_habilitado (pantalla).
| Este flag es un kill-switch de entorno (false = nunca evalúa aunque la pantalla diga sí).
*/

return [
    'habilitado_entorno' => filter_var(env('AVISO_REGLAS_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),

    'log_ejecuciones' => filter_var(env('AVISO_REGLAS_LOG', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Campos del contexto que el diseñador ofrece (el emit debe mandarlos).
    | No son códigos de negocio hardcodeados: son claves del payload del evento.
    */
    'campos' => [
        'empresa_id' => ['etiqueta' => 'Empresa ID', 'tipo' => 'numero'],
        'entity_id' => ['etiqueta' => 'Entidad ID', 'tipo' => 'numero'],
        'monto' => ['etiqueta' => 'Monto', 'tipo' => 'numero'],
        'moneda_id' => ['etiqueta' => 'Moneda ID', 'tipo' => 'numero'],
        'tipo' => ['etiqueta' => 'Tipo / abreviatura', 'tipo' => 'texto'],
        'fuente' => ['etiqueta' => 'Fuente user_task', 'tipo' => 'texto'],
        'centrocosto_id' => ['etiqueta' => 'Centro de costo ID', 'tipo' => 'numero'],
        'proveedor_id' => ['etiqueta' => 'Proveedor ID', 'tipo' => 'numero'],
        'usuario_id' => ['etiqueta' => 'Usuario ID', 'tipo' => 'numero'],
        'dias_pendiente' => ['etiqueta' => 'Días pendiente', 'tipo' => 'numero'],
        'estado' => ['etiqueta' => 'Estado', 'tipo' => 'texto'],
    ],

    'operadores' => [
        'eq' => 'Igual a',
        'neq' => 'Distinto de',
        'gt' => 'Mayor que',
        'gte' => 'Mayor o igual',
        'lt' => 'Menor que',
        'lte' => 'Menor o igual',
        'in' => 'En lista (coma)',
        'not_in' => 'No en lista (coma)',
        'contains' => 'Contiene texto',
        'empty' => 'Vacío / nulo',
        'not_empty' => 'Con valor',
    ],

    'acciones' => [
        'enviar_aviso' => 'Enviar tipo de aviso (módulo/código)',
        'notificar_usuarios' => 'Campanita a usuarios (IDs)',
        'notificar_roles' => 'Campanita a usuarios de rol(es)',
        'webhook_allowlist' => 'Webhook allowlist (clave configurada)',
    ],
];
