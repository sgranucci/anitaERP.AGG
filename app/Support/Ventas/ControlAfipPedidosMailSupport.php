<?php

namespace App\Support\Ventas;

/**
 * Constantes del batch legacy l-pedrem -b (cron sábados 18:00 El Bierzo).
 */
final class ControlAfipPedidosMailSupport
{
    public const MODULO = 'ventas';

    /** Resumen semanal a administración (equivale a envia_mail_cliente). */
    public const CODIGO_AVISO_RESUMEN = 'control_afip_pedidos';

    public const DIAS_VENTANA = 180;

    public const HORA_CRON = '18:00';

    /** programacion_dias: 6 = sábado (1=lun … 7=dom). */
    public const DIAS_CRON = '6';

    /** @var list<string> */
    public const EMAILS_RESUMEN_DEFAULT = [
        'fernandod@elbierzo.com.ar',
        'luisav@elbierzo.com.ar',
        'claudiam@elbierzo.com.ar',
    ];

    public const NOMBRE_AVISO = 'Control AFIP pedidos (clientes con problemas)';

    public const DESCRIPCION_AVISO = 'Batch semanal: clientes con pedidos en los últimos 180 días '
        .'que fallan validación del padrón ARCA. Reemplaza l-pedrem -b. '
        .'Envía resumen a estos destinatarios y un mail por cliente al vendedor.';
}
