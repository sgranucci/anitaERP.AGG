<?php

namespace App\Support\Caja;

use App\Exceptions\Contable\PeriodoContableCerradoException;
use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use InvalidArgumentException;

class RendicionMaquinavendingCajaPermiso
{
    public const SLUG_CREAR = 'crear-rendicion-maquinavending-caja';

    public const SLUG_ACTUALIZAR_DIA = 'actualizar-rendicion-maquinavending-caja-dia';

    public const SLUG_ACTUALIZAR_ENCARGADO = 'actualizar-rendicion-maquinavending-caja-encargado';

    public static function puedeActualizarPorFecha(RendicionMaquinavendingCaja $rendicion): bool
    {
        if (can(self::SLUG_ACTUALIZAR_ENCARGADO, false)) {
            return true;
        }

        if (can(self::SLUG_ACTUALIZAR_DIA, false)) {
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

    public static function assertAltaPermitida(int $empresaId, Carbon $fechaCaja, ?Carbon $fechaJornadaContable = null): void
    {
        if (can(self::SLUG_ACTUALIZAR_ENCARGADO, false)) {
            // Encargado: puede registrar con fecha de presentación distinta a hoy.
        } elseif (Carbon::today()->isSameDay($fechaCaja)
            && (can(self::SLUG_ACTUALIZAR_DIA, false) || can(self::SLUG_CREAR, false))) {
            // Cajero: presentación el día de hoy (aunque la jornada Ventas sea anterior).
        } else {
            throw new InvalidArgumentException(self::mensajeRestriccionFecha());
        }

        $fechaPeriodo = $fechaJornadaContable ?? $fechaCaja;
        self::assertPeriodoContablePorFecha($empresaId, $fechaPeriodo->format('Y-m-d'));
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
