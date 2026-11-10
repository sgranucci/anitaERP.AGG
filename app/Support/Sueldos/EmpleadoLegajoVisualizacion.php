<?php

declare(strict_types=1);

namespace App\Support\Sueldos;

/**
 * Apertura del legajo en solo lectura desde el índice de empleados.
 * El permiso es independiente de editar/actualizar: se puede ver sin poder grabar.
 */
final class EmpleadoLegajoVisualizacion
{
    public const PERMISO = 'visualizar-empleado-sueldos';

    public const VISTA = 'visualizacion';

    public static function activa(): bool
    {
        return request()->query('vista') === self::VISTA;
    }

    /**
     * Lectura del legajo. En visualización exige el permiso propio.
     * En el alta/edición normal deja el permiso que ya tenía la pantalla.
     */
    public static function autorizarComoEdicion(): void
    {
        if (self::activa()) {
            can(self::PERMISO);

            return;
        }

        can('editar-empleado-sueldos');
    }

    public static function puedeMutar(bool $tienePermiso): bool
    {
        return ! self::activa() && $tienePermiso;
    }
}
