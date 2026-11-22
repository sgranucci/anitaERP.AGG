<?php

namespace App\Support\Ventas;

/**
 * Constantes del batch legacy p-controlnc (cron El Bierzo 23:00).
 */
final class ControlNcDescuentoMailSupport
{
    public const MODULO = 'ventas';

    public const CODIGO_AVISO = 'control_nc_descuento';

    public const HORA_CRON = '23:00';

    /** Todos los días (1=lun … 7=dom). */
    public const DIAS_CRON = '1,2,3,4,5,6,7';

    public const UMBRAL_LISTA_ESPECIAL = 4.0;

    public const UMBRAL_GENERAL = 8.0;

    /** @var list<int> */
    public const LISTAS_ESPECIALES = [1, 8];

    /** @var list<string> */
    public const EMAILS_DEFAULT = [
        'mariom@elbierzo.com.ar',
        'fernandod@elbierzo.com.ar',
    ];

    public const NOMBRE_AVISO = 'Control NC descuento (p-controlnc)';

    public const DESCRIPCION_AVISO = 'Batch diario 23:00: NCP del día con dto >4% (listas 1 y 8) '
        .'y >8% (resto). Merge ERP+Anita. Reemplaza p-controlnc del cron Linux.';

    public const TIPO_NCP = 'NCP';
}
