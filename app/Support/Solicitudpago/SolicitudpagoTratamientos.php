<?php

namespace App\Support\Solicitudpago;

final class SolicitudpagoTratamientos
{
    public const NORMAL = 'NORMAL';

    public const URGENTE = 'URGENTE';

    public const ANTICIPADA = 'ANTICIPADA';

    public const PLAN_DE_PAGO = 'PLAN_DE_PAGO';

    public const RECURRENTE = 'RECURRENTE';

    /** @var array<string, string> */
    private const ANITA_A_ERP = [
        'N' => self::NORMAL,
        '0' => self::NORMAL,
        'U' => self::URGENTE,
        '1' => self::URGENTE,
        'A' => self::ANTICIPADA,
        '2' => self::ANTICIPADA,
        'P' => self::PLAN_DE_PAGO,
        '3' => self::PLAN_DE_PAGO,
        'R' => self::RECURRENTE,
        '4' => self::RECURRENTE,
    ];

    /** @var array<string, string> */
    private const ERP_A_ANITA = [
        self::NORMAL => 'N',
        self::URGENTE => 'U',
        self::ANTICIPADA => 'A',
        self::PLAN_DE_PAGO => 'P',
        self::RECURRENTE => 'R',
    ];

    public static function desdeAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));
        if ($v === '') {
            return self::NORMAL;
        }

        return self::ANITA_A_ERP[$v] ?? self::NORMAL;
    }

    public static function haciaAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return self::ERP_A_ANITA[$v] ?? 'N';
    }

    public static function usaCuotas(?string $tratamiento): bool
    {
        return in_array(strtoupper(trim((string) $tratamiento)), [self::PLAN_DE_PAGO, self::RECURRENTE], true);
    }

    /** Pago como OPA (anticipo a proveedores), no como OPP de gasto. */
    public static function esAnticipada(?string $tratamiento): bool
    {
        return strtoupper(trim((string) $tratamiento)) === self::ANTICIPADA;
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::NORMAL, 'nombre' => 'Normal'],
            ['valor' => self::URGENTE, 'nombre' => 'Urgente'],
            ['valor' => self::ANTICIPADA, 'nombre' => 'Anticipada'],
            ['valor' => self::PLAN_DE_PAGO, 'nombre' => 'Plan de pago'],
            ['valor' => self::RECURRENTE, 'nombre' => 'Recurrente'],
        ];
    }

    public static function label(?string $tratamiento): string
    {
        $tratamiento = strtoupper(trim((string) $tratamiento));
        foreach (self::opciones() as $opcion) {
            if ($opcion['valor'] === $tratamiento) {
                return $opcion['nombre'];
            }
        }

        return $tratamiento !== '' ? $tratamiento : '—';
    }
}
