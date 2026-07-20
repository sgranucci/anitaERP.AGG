<?php

namespace App\Support\Sueldos;

use Illuminate\Support\Facades\URL;

class EmpleadoAltaAutorizacion
{
    public const DIAS_VALIDEZ_LINK = 14;

    public static function urlAutorizacionFirmada(int $empleadoId): string
    {
        $dias = max(1, (int) config('sueldos.empleado_alta_link_dias', self::DIAS_VALIDEZ_LINK));

        return URL::temporarySignedRoute(
            'autorizar_empleado_sueldos_desde_aviso',
            now()->addDays($dias),
            ['id' => $empleadoId]
        );
    }
}
