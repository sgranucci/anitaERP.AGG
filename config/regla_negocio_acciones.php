<?php

/*
| Catálogo cerrado de acciones tipadas (THEN).
| Nueva acción = handler en código + entrada aquí. Sin script libre en UI.
*/

return [
    'catalogo' => [
        'noop' => [
            'etiqueta' => 'Sin efecto (solo auditar match)',
            'muta' => false,
        ],
        'deny' => [
            'etiqueta' => 'Denegar / bloquear operación',
            'muta' => false,
            'params' => ['mensaje' => 'texto'],
        ],
        'set_dato' => [
            'etiqueta' => 'Setear dato en la decisión',
            'muta' => false,
            'params' => ['clave' => 'texto', 'valor' => 'mixed'],
        ],
        'require_approval' => [
            'etiqueta' => 'Señal: requiere aprobación',
            'muta' => false,
            'params' => ['mensaje' => 'texto'],
        ],
        'enviar_aviso' => [
            'etiqueta' => 'Enviar tipo de aviso (módulo/código) — puente F5',
            'muta' => false,
            'params' => ['modulo' => 'texto', 'codigo' => 'texto'],
        ],
    ],
];

