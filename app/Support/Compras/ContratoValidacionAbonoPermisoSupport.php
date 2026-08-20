<?php

namespace App\Support\Compras;

final class ContratoValidacionAbonoPermisoSupport
{
    public static function puedeCompletar(
        int $usuarioId,
        int $responsableId,
        bool $tieneCompletar,
        bool $tieneOverride,
    ): bool {
        if ($tieneOverride || $tieneCompletar) {
            return true;
        }

        return $usuarioId > 0 && $responsableId > 0 && $usuarioId === $responsableId;
    }

    public static function desdeSesion(int $responsableId): bool
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        $completar = function_exists('can') && can('completar-validacion-abono', false);
        $override = function_exists('can') && can('override-validacion-abono', false);

        return self::puedeCompletar($usuarioId, $responsableId, $completar, $override);
    }
}
