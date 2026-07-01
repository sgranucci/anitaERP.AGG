<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use Carbon\Carbon;

/**
 * Jornadas en las que las ventas viven solo en anitaERP (sin réplica venta/vencae/vengrav en Informix).
 * Las auditorías operativas comparan ERP ↔ rendgastro (y ctamov en cierre), no cabecera venta Anita.
 */
final class GastronomiaVentasSoloErpSupport
{
    public static function esJornada(int $empresaId, string $fechaJornada): bool
    {
        $desde = self::fechaDesdeEmpresa($empresaId);
        if ($desde === null) {
            return false;
        }

        return Carbon::parse($fechaJornada)->toDateString() >= $desde;
    }

    public static function fechaDesdeEmpresa(int $empresaId): ?string
    {
        $map = config('gastronomia.ventas_solo_erp_desde_por_empresa', []);
        $raw = trim((string) ($map[$empresaId] ?? config('gastronomia.ventas_solo_erp_desde', '')));
        if ($raw === '') {
            return null;
        }

        return Carbon::parse($raw)->toDateString();
    }
}
