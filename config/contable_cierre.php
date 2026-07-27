<?php

return [

    /*
    | Horas antes del vencimiento de una apertura para enviar recordatorio al usuario habilitado.
    */
    'recordatorio_horas_antes_vencimiento' => (int) env('CONTABLE_CIERRE_RECORDATORIO_HORAS', 2),

    /*
    | Intervalo del job que procesa vencimientos y recordatorios (minutos, informativo).
    */
    'job_intervalo_minutos' => (int) env('CONTABLE_CIERRE_JOB_INTERVALO_MIN', 15),

    /*
    | Días de validez del enlace firmado de habilitación desde el correo de solicitud pendiente.
    */
    'apertura_link_habilitacion_dias' => (int) env('CONTABLE_CIERRE_APERTURA_LINK_DIAS', 7),

    /*
    | Hora (HH:MM) a partir de la cual, el día de fecha_ejecucion, el job aplica el cierre programado.
    */
    'hora_fin_dia' => (string) env('CONTABLE_CIERRE_HORA_FIN_DIA', '23:50'),

];
