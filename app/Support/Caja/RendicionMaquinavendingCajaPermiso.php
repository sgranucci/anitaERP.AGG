<?php

namespace App\Support\Caja;

use App\Exceptions\Contable\PeriodoContableCerradoException;
use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use InvalidArgumentException;

class RendicionMaquinavendingCajaPermiso
{
    public static function puedeActualizarPorFecha(RendicionMaquinavendingCaja $rendicion): bool
    {
        if (can('actualizar-rendicion-maquinavending-caja-encargado', false)) {
            return true;
        }

        if (can('actualizar-rendicion-maquinavending-caja-dia', false)) {
            $fechaRendicion = $rendicion->fecharendicion;

            return $fechaRendicion !== null
                && Carbon::today()->isSameDay($fechaRendicion);
        }

        return false;
    }

    public static function puedeEliminar(RendicionMaquinavendingCaja $rendicion): bool
    {
        return self::puedeActualizarPorFecha($rendicion);
    }

    public static function mensajeRestriccionFecha(): string
    {
        return 'Solo puede modificar rendiciones registradas en el día de hoy. '
            .'Para fechas anteriores solicite al encargado de tesorería.';
    }

    public static function assertModificacionPermitida(RendicionMaquinavendingCaja $rendicion): void
    {
        if (! self::puedeActualizarPorFecha($rendicion)) {
            throw new InvalidArgumentException(self::mensajeRestriccionFecha());
        }

        self::assertPeriodoContablePorFecha(
            (int) $rendicion->empresa_id,
            $rendicion->fecharendicion?->format('Y-m-d') ?? now()->format('Y-m-d'),
        );
    }

    public static function assertAltaPermitida(int $empresaId, Carbon $fechaCaja): void
    {
        if (! can('actualizar-rendicion-maquinavending-caja-encargado', false)
            && ! (can('actualizar-rendicion-maquinavending-caja-dia', false) && Carbon::today()->isSameDay($fechaCaja))) {
            throw new InvalidArgumentException(self::mensajeRestriccionFecha());
        }

        self::assertPeriodoContablePorFecha($empresaId, $fechaCaja->format('Y-m-d'));
    }

    public static function assertPeriodoContablePorFecha(int $empresaId, string $fechaYmd): void
    {
        try {
            PeriodoContableCierreSupport::assertOperacionPermitida(
                $empresaId,
                $fechaYmd,
                PeriodoContableCierreSupport::ALCANCE_CAJA,
            );
        } catch (PeriodoContableCerradoException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
    }
}
