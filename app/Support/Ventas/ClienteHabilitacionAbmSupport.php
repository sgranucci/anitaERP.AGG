<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Pasar un cliente de Suspendido a Activo.
 * Hasta que exista el permiso habilitar-clientes, sigue valiendo suspender-clientes.
 */
final class ClienteHabilitacionAbmSupport
{
    public const PERMISO = 'habilitar-clientes';

    public static function usuarioPuedeHabilitar(): bool
    {
        if (! self::permisoRegistrado()) {
            return can('suspender-clientes', false);
        }

        return can(self::PERMISO, false);
    }

    public static function errorSiHabilitaSuspendidoSinPermiso(?string $estadoActual, ?string $estadoNuevo): ?string
    {
        if (self::usuarioPuedeHabilitar()) {
            return null;
        }

        if (trim((string) $estadoActual) !== Cliente::ESTADO_SUSPENDIDO) {
            return null;
        }

        if (trim((string) $estadoNuevo) !== Cliente::ESTADO_ACTIVO) {
            return null;
        }

        return 'No tiene permiso para habilitar clientes suspendidos.';
    }

    public static function permisoRegistrado(): bool
    {
        static $registrado = null;
        if ($registrado === null) {
            $registrado = DB::table('permiso')->where('slug', self::PERMISO)->exists();
        }

        return $registrado;
    }
}
