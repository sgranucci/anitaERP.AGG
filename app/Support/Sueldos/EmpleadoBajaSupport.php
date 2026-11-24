<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Baja provisoria (Anita: fecha egreso sin flag B) vs definitiva (estado B).
 *
 * Provisoria = estado Activo + fecha_egreso cargada.
 * Definitiva = estado Baja.
 */
class EmpleadoBajaSupport
{
    public const MOV_INGRESO = 'I';

    public const MOV_BAJA_DEFINITIVA = 'B';

    public const MOV_BAJA_PROVISORIA = 'BP';

    public const MOV_ANULA_PROVISORIA = 'XA';

    /** @var array<string, string> */
    public const MOV_LABELS = [
        self::MOV_INGRESO => 'Ingreso',
        self::MOV_BAJA_DEFINITIVA => 'Baja definitiva',
        self::MOV_BAJA_PROVISORIA => 'Baja provisoria',
        self::MOV_ANULA_PROVISORIA => 'Anula provisoria',
    ];

    public static function esBajaProvisoria(?object $empleado): bool
    {
        if ($empleado === null) {
            return false;
        }
        $estado = $empleado->estado ?? null;
        $egreso = $empleado->fecha_egreso ?? null;

        return EmpleadoEstados::esActivo($estado) && $egreso !== null && (string) $egreso !== '';
    }

    public static function esBajaDefinitiva(?object $empleado): bool
    {
        if ($empleado === null) {
            return false;
        }

        return EmpleadoEstados::esBaja($empleado->estado ?? null);
    }

    public static function tieneEgresoProgramado(?object $empleado): bool
    {
        if ($empleado === null) {
            return false;
        }
        $egreso = $empleado->fecha_egreso ?? null;

        return $egreso !== null && (string) $egreso !== '';
    }

    public static function labelMovimiento(?string $tipo): string
    {
        $t = strtoupper(trim((string) $tipo));

        return self::MOV_LABELS[$t] ?? ($t !== '' ? $t : '—');
    }

    /**
     * Badge corto para grilla de activos con baja programada.
     */
    public static function badgeProvisoria(?object $empleado): ?string
    {
        if (! self::esBajaProvisoria($empleado)) {
            return null;
        }
        $txt = Carbon::parse($empleado->fecha_egreso)->format('d/m/Y');

        return 'Baja prog. '.$txt;
    }

    /**
     * @param  DateTimeInterface|string|null  $periodoDesde
     * @param  DateTimeInterface|string|null  $periodoHasta
     */
    public static function egresoEnPeriodo(
        object $empleado,
        $periodoDesde,
        $periodoHasta
    ): bool {
        if (! self::tieneEgresoProgramado($empleado) && ! self::esBajaDefinitiva($empleado)) {
            return false;
        }
        $egreso = $empleado->fecha_egreso ?? null;
        if ($egreso === null || (string) $egreso === '') {
            return false;
        }
        $e = Carbon::parse($egreso)->toDateString();
        $desde = $periodoDesde ? Carbon::parse($periodoDesde)->toDateString() : null;
        $hasta = $periodoHasta ? Carbon::parse($periodoHasta)->toDateString() : null;
        if ($desde && $e < $desde) {
            return false;
        }
        if ($hasta && $e > $hasta) {
            return false;
        }

        return true;
    }
}
