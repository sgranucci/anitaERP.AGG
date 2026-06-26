<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Cabeceras Anita fuera del alcance del reporte mensual de totales gastronomía.
 * FSL = slots/estacionamiento; FBI = bingo. Ya figuran en rendgastro u otros circuitos.
 */
final class GastronomiaAuditoriaMesTotalesAnitaSupport
{
    /** @var list<string> */
    public const TIPOS_VENTA_EXCLUIDOS = ['FSL', 'FBI'];

    public static function esTipoVentaExcluido(string $tipoAnita): bool
    {
        return in_array(strtoupper(trim($tipoAnita)), self::TIPOS_VENTA_EXCLUIDOS, true);
    }

    /**
     * @param  list<object>  $cabeceras
     * @return list<object>
     */
    public static function filtrarCabecerasIncluidas(array $cabeceras): array
    {
        return array_values(array_filter(
            $cabeceras,
            static fn (object $cab): bool => ! self::esTipoVentaExcluido((string) ($cab->ven_tipo ?? '')),
        ));
    }
}
