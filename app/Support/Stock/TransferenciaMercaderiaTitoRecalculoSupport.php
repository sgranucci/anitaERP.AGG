<?php

namespace App\Support\Stock;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Helpers del recálculo de TRA TITO al cambiar la cotización de una COM.
 */
final class TransferenciaMercaderiaTitoRecalculoSupport
{
    /**
     * @return array{desde: string, hasta: string}
     */
    public static function rangoMesEnCurso(?DateTimeInterface $ahora = null): array
    {
        $ref = $ahora !== null ? Carbon::parse($ahora) : Carbon::now();

        return [
            'desde' => $ref->copy()->startOfMonth()->toDateString(),
            'hasta' => $ref->copy()->endOfMonth()->toDateString(),
        ];
    }

    public static function precioRequiereCambio(float $antes, float $despues): bool
    {
        return abs($antes - $despues) > 0.0000005;
    }
}
