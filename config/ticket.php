<?php
// Constantes de configuracion modulo tickets

return [
	"dominioEmail" => '@grupoagg.com',
    "passwordNuevoUsuario" => '12345',
    // Área Sistemas / Tecnología (CC 92): administración compartida entre técnicos del área
    'administracion_sistemas_areadestino_id' => (int) env('TICKET_ADMINISTRACION_SISTEMAS_AREADESTINO_ID', 1),
    'administracion_sistemas_centrocosto' => env('TICKET_ADMINISTRACION_SISTEMAS_CC', '92'),
    "rolTecnico" => [
                    ['areadestino_id' => 1, 'rol_id' => 11],
                    ['areadestino_id' => 2, 'rol_id' => 11],
                    ['areadestino_id' => 3, 'rol_id' => 11],
                    ['areadestino_id' => 4, 'rol_id' => 11],
                    ]
];