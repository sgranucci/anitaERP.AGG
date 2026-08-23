<?php

return [

    /*
    | Recordatorio de tickets Pendientes cerca de la fecha prevista de visita.
    | Destinatarios: Configuración → Avisos (seguridad / ingreso_proveedor_recordatorio).
    */
    'recordatorio_habilitado' => filter_var(env('INGRESO_PROVEEDOR_RECORDATORIO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    'recordatorio_hora' => env('INGRESO_PROVEEDOR_RECORDATORIO_HORA', '08:45'),
    'recordatorio_horas' => max(1, (int) env('INGRESO_PROVEEDOR_RECORDATORIO_HORAS', 24)),

    /*
    | Alerta a Compras cerca de fin de mes: contrato sin tickets Finalizado.
    | Destinatarios: Configuración → Avisos (seguridad / ingreso_proveedor_abono_sin_cierre).
    */
    'abono_alerta_habilitado' => filter_var(env('INGRESO_PROVEEDOR_ABONO_ALERTA_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
    'abono_alerta_dia' => max(1, min(28, (int) env('INGRESO_PROVEEDOR_ABONO_ALERTA_DIA', 25))),
    'abono_alerta_hora' => env('INGRESO_PROVEEDOR_ABONO_ALERTA_HORA', '09:00'),

];
