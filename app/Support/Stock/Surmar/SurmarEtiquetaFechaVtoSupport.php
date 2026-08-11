<?php

namespace App\Support\Stock\Surmar;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Fecha de vencimiento de etiqueta Surmar (COM): fecha base + articulo.vencimientoendia
 * (Anita stkmae.stkm_vto_en_dias).
 */
final class SurmarEtiquetaFechaVtoSupport
{
    public static function calcular(?CarbonInterface $fechaBase, ?int $vencimientoEnDias): ?string
    {
        $dias = (int) ($vencimientoEnDias ?? 0);
        if ($dias <= 0 || $fechaBase === null) {
            return null;
        }

        return Carbon::parse($fechaBase->format('Y-m-d'))
            ->addDays($dias)
            ->toDateString();
    }

    /**
     * Prioriza fecha enviada por el operador; si viene vacía usa vencimientoendia del artículo.
     */
    public static function resolver(?string $fechaVtoManual, ?CarbonInterface $fechaBase, ?int $vencimientoEnDias): ?string
    {
        $manual = is_string($fechaVtoManual) ? trim($fechaVtoManual) : '';
        if ($manual !== '') {
            return Carbon::parse($manual)->toDateString();
        }

        return self::calcular($fechaBase, $vencimientoEnDias);
    }
}
