<?php
// Constantes de configuracion modulo tickets

return [
	"dominioEmail" => '@grupoagg.com',
    "passwordNuevoUsuario" => '12345',
    // Área Sistemas / Tecnología (CC 92): administración compartida entre técnicos del área
    'administracion_sistemas_areadestino_id' => (int) env('TICKET_ADMINISTRACION_SISTEMAS_AREADESTINO_ID', 1),
    'administracion_sistemas_centrocosto' => env('TICKET_ADMINISTRACION_SISTEMAS_CC', '92'),
    // Modos por área (tabla ticket_configuracion_areadestino). Sin fila = dispatch.
    // dispatch = administrador asigna técnicos (Sistemas).
    // claim    = cola del área; el técnico toma el ticket (Mantenimiento).
    'modo_operacion_dispatch' => 'dispatch',
    'modo_operacion_claim' => 'claim',
    // Tope blando de direcciones en CC al avisar comentario del técnico (Office 365 admite ~500 To+CC+Bcc).
    'notificacion_cc_max_destinatarios' => (int) env('TICKET_NOTIFICACION_CC_MAX', 100),
    "rolTecnico" => [
                    ['areadestino_id' => 1, 'rol_id' => 11],
                    ['areadestino_id' => 2, 'rol_id' => 11],
                    ['areadestino_id' => 3, 'rol_id' => 11],
                    ['areadestino_id' => 4, 'rol_id' => 11],
                    ]
];