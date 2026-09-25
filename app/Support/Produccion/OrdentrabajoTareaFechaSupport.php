<?php

namespace App\Support\Produccion;

/**
 * Fechas de ordentrabajo_tarea.
 *
 * En Ferli hay muchas filas con MySQL '0000-00-00' (import L8 / legacy).
 * Eso no es NULL, y el control de secuencia lo interpretaba como "tarea ya
 * finalizada", bloqueando el Fin de tareas abiertas (ej. OT 30312 CORTADO PL-VISTA).
 */
final class OrdentrabajoTareaFechaSupport
{
    public static function tieneValor(mixed $fecha): bool
    {
        return self::normalizar($fecha) !== null;
    }

    public static function normalizar(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        if ($fecha instanceof \DateTimeInterface) {
            $ymd = $fecha->format('Y-m-d');

            return ($ymd === '0000-00-00' || (int) $fecha->format('Y') < 1900) ? null : $ymd;
        }

        $texto = trim((string) $fecha);
        if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
            return null;
        }

        return substr($texto, 0, 10);
    }
}
