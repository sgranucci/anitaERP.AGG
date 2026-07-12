<?php

declare(strict_types=1);

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use Carbon\Carbon;

class RendicionBingoCajaPermiso
{
    public const SLUG_BORRAR = 'borrar-rendicion-bingo-caja';

    public const SLUG_BORRAR_DIA = 'borrar-rendicion-bingo-caja-dia';

    public const SLUG_BORRAR_SIN_RESTRICCION_FECHA = 'borrar-rendicion-bingo-caja-encargado';

    /**
     * La restricción es por fecha de presentación en caja (fecharendicion),
     * no por la fecha de la jornada del bingo: una rendición de la jornada
     * de ayer presentada hoy se considera "del día" y puede borrarse.
     */
    public static function puedeEliminarPorFecha(RendicionBingoCaja $rendicion): bool
    {
        if (can(self::SLUG_BORRAR_SIN_RESTRICCION_FECHA, false)) {
            return true;
        }

        $fecha = $rendicion->fecharendicion;
        $esHoy = $fecha !== null && Carbon::today()->isSameDay($fecha);

        if (can(self::SLUG_BORRAR_DIA, false)) {
            return $esHoy;
        }

        // Permiso base de borrar sin modificadores: solo el día de presentación.
        return $esHoy;
    }

    public static function mensajeRestriccionFecha(): string
    {
        return 'Solo puede borrar rendiciones de bingo presentadas en el día de hoy. '
            .'Para fechas anteriores solicite al encargado de tesorería.';
    }
}
