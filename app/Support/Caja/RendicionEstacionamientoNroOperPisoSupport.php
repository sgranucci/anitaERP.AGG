<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Caja\AnitaSync\RendicionRendgastroNroOperCompartidoSupport;

/**
 * Piso/techo de rendg_nro_oper para estacionamiento.
 * Delega al numerador compartido 850000+ (misma semilla que gastronomía).
 */
final class RendicionEstacionamientoNroOperPisoSupport
{
    public static function piso(): int
    {
        return RendicionRendgastroNroOperCompartidoSupport::piso();
    }

    public static function techo(): int
    {
        return RendicionRendgastroNroOperCompartidoSupport::techo();
    }

    public static function enRango(int $nroOper): bool
    {
        return RendicionRendgastroNroOperCompartidoSupport::enRango($nroOper);
    }

    public static function filtroSqlAnita(string $columna = 'rendg_nro_oper'): string
    {
        return RendicionRendgastroNroOperCompartidoSupport::filtroSqlAnita($columna);
    }
}
