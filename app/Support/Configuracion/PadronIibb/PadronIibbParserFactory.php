<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

use InvalidArgumentException;

/**
 * Resuelve el parser de cada provincia que carga contra padron_iibb_tasa.
 *
 * CABA (901), ARBA (902) y Santa Fe (921) no pasan por acá: tienen servicios
 * dedicados porque escriben en tablas propias o ya cuentan con su propio motor.
 */
final class PadronIibbParserFactory
{
    /** Tipo de padrón de Tucumán que carga tasas en padron_iibb_tasa. */
    public const TUCUMAN_TASAS = 'T';

    /** Tipo de padrón de Tucumán que carga en padron_coeficiente_tucuman. */
    public const TUCUMAN_COEFICIENTES = 'C';

    public const JURISDICCION_TUCUMAN = 924;

    /** @return list<int> */
    public static function jurisdiccionesSoportadas(): array
    {
        return [904, 908, 914, self::JURISDICCION_TUCUMAN];
    }

    public static function soporta(int $jurisdiccion): bool
    {
        return in_array($jurisdiccion, self::jurisdiccionesSoportadas(), true);
    }

    /**
     * Tucumán es la única provincia donde el usuario elige el tipo de padrón y
     * el tipo "C" no produce tasas sino coeficientes en otra tabla.
     */
    public static function esTucumanCoeficientes(int $jurisdiccion, ?string $tipoPadron): bool
    {
        return $jurisdiccion === self::JURISDICCION_TUCUMAN
            && strtoupper(trim((string) $tipoPadron)) === self::TUCUMAN_COEFICIENTES;
    }

    public static function crear(int $jurisdiccion, ?string $tipoPadron = null): PadronIibbParser
    {
        return match ($jurisdiccion) {
            904 => new PadronIibbCordobaParser,
            908 => new PadronIibbEntreRiosParser,
            914 => new PadronIibbMisionesParser,
            self::JURISDICCION_TUCUMAN => self::crearTucuman($tipoPadron),
            default => throw new InvalidArgumentException(
                "No hay importador de padrón IIBB para la jurisdicción {$jurisdiccion}."
            ),
        };
    }

    private static function crearTucuman(?string $tipoPadron): PadronIibbParser
    {
        if (self::esTucumanCoeficientes(self::JURISDICCION_TUCUMAN, $tipoPadron)) {
            throw new InvalidArgumentException(
                'El padrón de coeficientes de Tucumán se carga con PadronIibbTucumanCoeficienteCargaService.'
            );
        }

        return new PadronIibbTucumanTasasParser;
    }
}
