<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionRendgastroNroOperCompartidoSupport;

/**
 * Piso/techo de rendg_nro_oper para gastronomía.
 * Delega al numerador compartido 850000+ (misma semilla que estacionamiento).
 *
 * @deprecated Preferir RendicionRendgastroNroOperCompartidoSupport directamente.
 */
final class RendicionGastronomiaNroOperPisoSupport
{
    public static function piso(): int
    {
        return RendicionRendgastroNroOperCompartidoSupport::piso();
    }

    public static function techo(): int
    {
        return RendicionRendgastroNroOperCompartidoSupport::techo();
    }

    /** @deprecated Usar piso() — ya no hay franja por empresa. */
    public static function pisoParaEmpresa(int $empresaId): int
    {
        unset($empresaId);

        return self::piso();
    }

    /** @deprecated Usar techo() — ya no hay franja por empresa. */
    public static function techoParaEmpresa(int $empresaId): int
    {
        unset($empresaId);

        return self::techo();
    }

    public static function enRango(int $nroOper): bool
    {
        return RendicionRendgastroNroOperCompartidoSupport::enRango($nroOper);
    }

    /** @deprecated Usar enRango() — el rango es global. */
    public static function enRangoEmpresa(int $empresaId, int $nroOper): bool
    {
        unset($empresaId);

        return self::enRango($nroOper);
    }

    public static function filtroSqlAnita(int $empresaId = 0, string $columna = 'rendg_nro_oper'): string
    {
        unset($empresaId);

        return RendicionRendgastroNroOperCompartidoSupport::filtroSqlAnita($columna);
    }
}
