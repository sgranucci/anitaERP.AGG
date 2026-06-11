<?php

namespace App\Support\Caja;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Services\Caja\RendicionEstacionamientoJornadaPresentacionService;
use Carbon\Carbon;

class RendicionEstacionamientoCajaPermiso
{
    public const SLUG_ACTUALIZAR_DIA = 'actualizar-rendicion-estacionamiento-caja-dia';

    public const SLUG_ACTUALIZAR_SIN_RESTRICCION_FECHA = 'actualizar-rendicion-estacionamiento-caja-encargado';

    public static function puedeActualizarPorFecha(RendicionEstacionamientoCaja $rendicion): bool
    {
        if (can(self::SLUG_ACTUALIZAR_SIN_RESTRICCION_FECHA, false)) {
            return true;
        }

        if (can(self::SLUG_ACTUALIZAR_DIA, false)) {
            $fechaRendicion = $rendicion->fecharendicion;

            return $fechaRendicion !== null
                && Carbon::today()->isSameDay($fechaRendicion);
        }

        return false;
    }

    public static function mensajeRestriccionFecha(): string
    {
        return 'Solo puede modificar rendiciones registradas en el día de hoy. '
            .'Para fechas anteriores solicite al encargado de tesorería.';
    }

    public static function puedeModificarRendicionTurno(RendicionEstacionamientoCaja $rendicion): bool
    {
        if ($rendicion->esRendicionJornada()) {
            return true;
        }

        $jornadaId = (int) ($rendicion->turnoOperativo?->jornada_estacionamiento_id ?? 0);
        if ($jornadaId <= 0) {
            return true;
        }

        return ! app(RendicionEstacionamientoJornadaPresentacionService::class)
            ->jornadaPresentadaBloqueaRendicionesTurno($jornadaId);
    }

    public static function mensajeJornadaPresentadaBloqueoTurno(): string
    {
        return 'La jornada ya fue presentada en caja. Las rendiciones de turno no pueden modificarse. '
            .'Edite o anule la presentación de jornada si necesita corregir.';
    }
}
