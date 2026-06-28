<?php

namespace App\Support\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;
use Carbon\Carbon;

class MaquinavendingRendicionPermiso
{
    public const SLUG_ENCARGADO = 'actualizar-mv-rend-gastronomia-encargado';

    public static function puedeModificar(MaquinavendingRendicion $rendicion): bool
    {
        if ($rendicion->estaPresentadaEnCaja()) {
            return false;
        }

        if (self::esJornadaHoy($rendicion)) {
            return true;
        }

        return can(self::SLUG_ENCARGADO, false);
    }

    public static function esJornadaHoy(MaquinavendingRendicion $rendicion): bool
    {
        $fechaJornada = $rendicion->fecha_jornada;

        return $fechaJornada !== null
            && Carbon::today()->isSameDay($fechaJornada);
    }

    public static function mensajeBloqueoModificacion(MaquinavendingRendicion $rendicion): string
    {
        if ($rendicion->estaPresentadaEnCaja()) {
            return self::mensajeBloqueoPresentada();
        }

        if (! self::esJornadaHoy($rendicion) && ! can(self::SLUG_ENCARGADO, false)) {
            return self::mensajeBloqueoJornadaEncargado();
        }

        return 'No se puede modificar ni eliminar esta rendición.';
    }

    public static function mensajeBloqueoPresentada(): string
    {
        return 'No se puede modificar ni eliminar: la rendición ya fue presentada en caja.';
    }

    public static function mensajeBloqueoJornadaEncargado(): string
    {
        return 'La jornada de esta rendición no es la de hoy. '
            .'Solo un encargado puede modificarla o eliminarla.';
    }
}
