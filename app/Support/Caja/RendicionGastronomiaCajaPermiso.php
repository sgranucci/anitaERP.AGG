<?php

namespace App\Support\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Services\Caja\RendicionGastronomiaJornadaPresentacionService;
use Carbon\Carbon;

class RendicionGastronomiaCajaPermiso
{
    public const SLUG_ACTUALIZAR_DIA = 'actualizar-rendicion-gastronomia-caja-dia';

    public const SLUG_ACTUALIZAR_SIN_RESTRICCION_FECHA = 'actualizar-rendicion-gastronomia-caja-encargado';

    public static function puedeActualizarPorFecha(RendicionGastronomiaCaja $rendicion): bool
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

    public static function puedeModificarRendicionTurno(RendicionGastronomiaCaja $rendicion): bool
    {
        if ($rendicion->esRendicionJornada()) {
            return true;
        }

        $jornadaId = (int) ($rendicion->turnoOperativo?->jornada_gastronomia_id ?? 0);
        if ($jornadaId <= 0) {
            return true;
        }

        return ! app(RendicionGastronomiaJornadaPresentacionService::class)
            ->jornadaPresentadaBloqueaRendicionesTurno($jornadaId);
    }

    public static function mensajeJornadaPresentadaBloqueoTurno(): string
    {
        return 'La jornada ya fue presentada en caja. Las rendiciones de turno no pueden modificarse. '
            .'Edite o anule la presentación de jornada si necesita corregir.';
    }
}
