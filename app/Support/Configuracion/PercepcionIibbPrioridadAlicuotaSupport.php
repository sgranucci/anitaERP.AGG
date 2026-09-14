<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

/**
 * Orden padrón ↔ tasa de descarte al calcular percepción IIBB.
 *
 * Vive en provincia (solapa % Tasas): es del fisco / parametrización, no de la
 * empresa. Default = padrón primero (si hay fila); Misiones / provincias sin
 * padrón en Anita suelen ir descarte primero.
 */
final class PercepcionIibbPrioridadAlicuotaSupport
{
    /** Padrón si existe (incluye 0); si no, tasa de descarte. */
    public const PADRON_DESCARTE = 'padron_descarte';

    /** Tasa de descarte si hay; si no, padrón. */
    public const DESCARTE_PADRON = 'descarte_padron';

    /** Jurisdicciones AFIP con padrón de percepción en el ERP. */
    public const JURISDICCIONES_CON_PADRON = [901, 902, 904, 908, 914, 921, 924];

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [self::PADRON_DESCARTE, self::DESCARTE_PADRON];
    }

    public static function normalizar(?string $valor): string
    {
        $valor = strtolower(trim((string) $valor));
        if ($valor === self::DESCARTE_PADRON) {
            return self::DESCARTE_PADRON;
        }

        return self::PADRON_DESCARTE;
    }

    public static function priorizaDescarte(?string $valor): bool
    {
        return self::normalizar($valor) === self::DESCARTE_PADRON;
    }

    /** Etiqueta corta para listados. */
    public static function etiqueta(?string $valor): string
    {
        return self::priorizaDescarte($valor)
            ? 'Descarte → padrón'
            : 'Padrón → descarte';
    }

    /**
     * Default de instalación: con padrón → padrón/descarte; sin padrón →
     * descarte/padrón. Misiones (914) tiene padrón cargado pero Anita usa
     * ibrxprov: se fuerza descarte/padrón.
     */
    public static function defaultParaJurisdiccion(?int $jurisdiccion): string
    {
        $jur = (int) $jurisdiccion;
        if ($jur === 914) {
            return self::DESCARTE_PADRON;
        }
        if (in_array($jur, self::JURISDICCIONES_CON_PADRON, true)) {
            return self::PADRON_DESCARTE;
        }

        return self::DESCARTE_PADRON;
    }

    /**
     * Resuelve la alícuota según la prioridad de la provincia.
     *
     * @return float|null null = no percibir (p. ej. "No retiene" sin padrón en modo padrón primero)
     */
    public static function resolver(
        ?string $prioridad,
        ?float $tasaPadron,
        float $tasaDescarte,
        bool $clienteLocalEnJurisdiccion,
        bool $esNoRetiene
    ): ?float {
        $priorizaDescarte = self::priorizaDescarte($prioridad);
        $hayPadron = $tasaPadron !== null;
        $hayDescarte = $clienteLocalEnJurisdiccion && $tasaDescarte > 0.00001;

        if ($priorizaDescarte) {
            if ($hayDescarte) {
                return $tasaDescarte;
            }
            if ($hayPadron) {
                return $tasaPadron;
            }
            if ($esNoRetiene) {
                return null;
            }

            return $clienteLocalEnJurisdiccion ? $tasaDescarte : null;
        }

        if ($hayPadron) {
            return $tasaPadron;
        }
        if ($esNoRetiene) {
            return null;
        }
        if ($clienteLocalEnJurisdiccion) {
            return $tasaDescarte;
        }

        return null;
    }
}
