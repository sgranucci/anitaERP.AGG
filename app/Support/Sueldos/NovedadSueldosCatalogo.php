<?php

namespace App\Support\Sueldos;

/**
 * Catálogos de estado y origen de novedades de liquidación.
 */
class NovedadSueldosCatalogo
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_INCLUIDA = 'incluida';

    public const ESTADO_ANULADA = 'anulada';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_INCLUIDA => 'Incluida en liquidación',
        self::ESTADO_ANULADA => 'Anulada',
    ];

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_IMPORT = 'import';

    public const ORIGEN_AUSENCIA = 'ausencia';

    public const ORIGEN_RELOJ = 'reloj';

    public const ORIGEN_PLAN_CUOTA = 'plan_cuota';

    public const ORIGEN_SYNC_ANITA = 'sync_anita';

    public const ORIGEN_DESCUENTO_FALLO = 'descuento_fallo';

    public const ORIGEN_SANCION = 'sancion';

    /** @var array<string, string> */
    public const ORIGENES = [
        self::ORIGEN_MANUAL => 'Manual',
        self::ORIGEN_IMPORT => 'Importación Excel',
        self::ORIGEN_AUSENCIA => 'Ausencia / licencia',
        self::ORIGEN_RELOJ => 'Reloj / control horario',
        self::ORIGEN_PLAN_CUOTA => 'Plan de cuotas',
        self::ORIGEN_SYNC_ANITA => 'Sync Anita',
        self::ORIGEN_DESCUENTO_FALLO => 'Descuento por fallo',
        self::ORIGEN_SANCION => 'Sanción disciplinaria',
    ];

    public static function etiquetaEstado(?string $estado): string
    {
        return self::ESTADOS[$estado] ?? (string) $estado;
    }

    public static function etiquetaOrigen(?string $origen): string
    {
        return self::ORIGENES[$origen] ?? (string) $origen;
    }

    public static function normalizarEstado(?string $estado): string
    {
        $estado = trim((string) $estado);

        return isset(self::ESTADOS[$estado]) ? $estado : self::ESTADO_PENDIENTE;
    }

    /**
     * Estado leído de Excel. Vacío = pendiente. null = texto no reconocido.
     */
    public static function interpretarEstadoImport(mixed $valor): ?string
    {
        $texto = strtolower(trim((string) $valor));
        if ($texto === '') {
            return self::ESTADO_PENDIENTE;
        }

        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto) ?? $texto;
        $texto = trim($texto, '_');

        $mapa = [
            'pendiente' => self::ESTADO_PENDIENTE,
            'p' => self::ESTADO_PENDIENTE,
            '0' => self::ESTADO_PENDIENTE,
            'incluida' => self::ESTADO_INCLUIDA,
            'incluida_en_liquidacion' => self::ESTADO_INCLUIDA,
            'i' => self::ESTADO_INCLUIDA,
            'l' => self::ESTADO_INCLUIDA,
            '1' => self::ESTADO_INCLUIDA,
            'anulada' => self::ESTADO_ANULADA,
            'a' => self::ESTADO_ANULADA,
            'b' => self::ESTADO_ANULADA,
            'x' => self::ESTADO_ANULADA,
        ];

        return $mapa[$texto] ?? null;
    }

    public static function normalizarOrigen(?string $origen): string
    {
        $origen = trim((string) $origen);

        return isset(self::ORIGENES[$origen]) ? $origen : self::ORIGEN_MANUAL;
    }

    /** @return list<string> */
    public static function estadosPermitidos(): array
    {
        return array_keys(self::ESTADOS);
    }

    /** @return list<string> */
    public static function origenesPermitidos(): array
    {
        return array_keys(self::ORIGENES);
    }
}
