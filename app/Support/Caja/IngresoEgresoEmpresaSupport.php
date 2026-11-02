<?php

namespace App\Support\Caja;

use App\Support\Contable\AsientoEmpresaCambioSupport;
use InvalidArgumentException;

/**
 * La empresa al ingresar un movimiento la elige contaduría.
 * El resto queda fijo en la empresa de trabajo (la del listado o la sesión).
 * Un movimiento ya grabado no cambia de empresa salvo `cambiar-empresa-asiento`.
 * Si quedó mal, se revierte y se vuelve a cargar.
 */
final class IngresoEgresoEmpresaSupport
{
    public const MENSAJE_SIN_ELECCION = 'No se puede cambiar la empresa al ingresar el movimiento. Elegila en el listado y volvé a cargar.';

    public const MENSAJE_YA_GRABADO = 'No se puede cambiar la empresa de un movimiento ya ingresado. Si quedó mal, revertirlo y cargarlo de nuevo.';

    public static function puedeElegirAlIngresar(): bool
    {
        return self::puedeCambiarGrabado()
            || (bool) can('crear-asiento', false)
            || (bool) can('actualizar-asiento', false);
    }

    public static function puedeCambiarGrabado(): bool
    {
        return AsientoEmpresaCambioSupport::permitido();
    }

    public static function soloLectura(bool $yaGrabado): bool
    {
        return self::soloLecturaConPermisos(
            $yaGrabado,
            self::puedeElegirAlIngresar(),
            self::puedeCambiarGrabado()
        );
    }

    public static function soloLecturaConPermisos(bool $yaGrabado, bool $puedeElegirAlIngresar, bool $puedeCambiarGrabado): bool
    {
        if ($yaGrabado) {
            return ! $puedeCambiarGrabado;
        }

        return ! $puedeElegirAlIngresar;
    }

    public static function assertAlGuardar(bool $yaGrabado, int $empresaAnterior, int $empresaNueva): void
    {
        self::assertAlGuardarConPermisos(
            $yaGrabado,
            $empresaAnterior,
            $empresaNueva,
            self::puedeElegirAlIngresar(),
            self::puedeCambiarGrabado()
        );
    }

    public static function assertAlGuardarConPermisos(
        bool $yaGrabado,
        int $empresaAnterior,
        int $empresaNueva,
        bool $puedeElegirAlIngresar,
        bool $puedeCambiarGrabado
    ): void {
        if ($empresaNueva <= 0) {
            throw new InvalidArgumentException('Debe ingresar empresa.');
        }

        if ($yaGrabado) {
            if ($empresaAnterior > 0 && $empresaNueva !== $empresaAnterior && ! $puedeCambiarGrabado) {
                throw new InvalidArgumentException(self::MENSAJE_YA_GRABADO);
            }

            return;
        }

        if ($puedeElegirAlIngresar) {
            return;
        }

        if ($empresaAnterior <= 0 || $empresaNueva !== $empresaAnterior) {
            throw new InvalidArgumentException(self::MENSAJE_SIN_ELECCION);
        }
    }
}
