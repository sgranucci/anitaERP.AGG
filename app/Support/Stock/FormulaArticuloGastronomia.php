<?php

namespace App\Support\Stock;

/**
 * Facturación gastronomía (ítems opcionales con orden): AGG hoy, CROWN a futuro.
 */
final class FormulaArticuloGastronomia
{
    /** @var list<string> */
    private const EMPRESAS = ['AGG', 'CROWN'];

    public static function opcionalesHabilitados(): bool
    {
        $emp = strtoupper(trim((string) config('app.empresa')));

        return in_array($emp, self::EMPRESAS, true);
    }
}
