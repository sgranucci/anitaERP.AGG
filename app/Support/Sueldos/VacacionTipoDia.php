<?php

namespace App\Support\Sueldos;

/**
 * Tipos de día de períodos de vacaciones.
 * En ERP se guarda el nombre (`habil`, `corrido`, …). Anita usa 1 carácter (H/C/N/F).
 */
class VacacionTipoDia
{
    public const HABIL = 'habil';

    public const CORRIDO = 'corrido';

    public const NO_HABIL = 'no_habil';

    public const FERIADO = 'feriado';

    /** @var array<string, string> valor ERP => etiqueta UI */
    public const OPCIONES = [
        self::HABIL => 'Hábil',
        self::CORRIDO => 'Corrido',
        self::NO_HABIL => 'No hábil',
        self::FERIADO => 'Feriado',
    ];

    /** @var array<string, string> código Anita => valor ERP */
    private const DESDE_ANITA = [
        'H' => self::HABIL,
        'C' => self::CORRIDO,
        'N' => self::NO_HABIL,
        'F' => self::FERIADO,
    ];

    /** @var array<string, string> alias de texto → valor ERP */
    private const ALIAS = [
        'habil' => self::HABIL,
        'habiles' => self::HABIL,
        'habíl' => self::HABIL,
        'hábil' => self::HABIL,
        'corrido' => self::CORRIDO,
        'corridos' => self::CORRIDO,
        'no_habil' => self::NO_HABIL,
        'no habil' => self::NO_HABIL,
        'nohábil' => self::NO_HABIL,
        'no hábil' => self::NO_HABIL,
        'feriado' => self::FERIADO,
        'feriados' => self::FERIADO,
    ];

    /**
     * Normaliza a valor ERP (`habil`, `corrido`, …). Acepta código Anita o texto.
     */
    public static function normalizar(?string $valor): ?string
    {
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }

        $upper = strtoupper($raw);
        if (isset(self::DESDE_ANITA[$upper])) {
            return self::DESDE_ANITA[$upper];
        }

        $clave = mb_strtolower($raw);
        $clave = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $clave);

        if (isset(self::OPCIONES[$clave])) {
            return $clave;
        }

        if (isset(self::ALIAS[$clave])) {
            return self::ALIAS[$clave];
        }

        // Compatibilidad: un solo carácter desconocido se deja como null
        if (mb_strlen($raw) === 1) {
            return self::DESDE_ANITA[$upper] ?? null;
        }

        return null;
    }

    public static function etiqueta(?string $valor): string
    {
        $tipo = self::normalizar($valor);
        if ($tipo === null) {
            return '';
        }

        return self::OPCIONES[$tipo] ?? $tipo;
    }

    /**
     * @return list<string>
     */
    public static function valoresPermitidos(): array
    {
        return array_keys(self::OPCIONES);
    }
}
