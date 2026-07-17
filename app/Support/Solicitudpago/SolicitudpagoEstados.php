<?php

namespace App\Support\Solicitudpago;

final class SolicitudpagoEstados
{
    public const EMITIDA = 'EMITIDA';

    public const CONTROLADA = 'CONTROLADA';

    public const AUTORIZADA = 'AUTORIZADA';

    public const PAGADA = 'PAGADA';

    public const SUSPENDIDA = 'SUSPENDIDA';

    public const RECHAZADA = 'RECHAZADA';

    public const TERMINADA = 'TERMINADA';

    /** @var array<string, string> */
    private const ANITA_A_ERP = [
        'E' => self::EMITIDA,
        'C' => self::CONTROLADA,
        'A' => self::AUTORIZADA,
        'P' => self::PAGADA,
        'S' => self::SUSPENDIDA,
        'R' => self::RECHAZADA,
        'T' => self::TERMINADA,
    ];

    /** @var array<string, string> */
    private const ERP_A_ANITA = [
        self::EMITIDA => 'E',
        self::CONTROLADA => 'C',
        self::AUTORIZADA => 'A',
        self::PAGADA => 'P',
        self::SUSPENDIDA => 'S',
        self::RECHAZADA => 'R',
        self::TERMINADA => 'T',
    ];

    public static function desdeAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));
        if ($v === '') {
            return self::EMITIDA;
        }

        return self::ANITA_A_ERP[$v] ?? self::EMITIDA;
    }

    public static function haciaAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return self::ERP_A_ANITA[$v] ?? 'E';
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::EMITIDA, 'nombre' => 'Emitida'],
            ['valor' => self::CONTROLADA, 'nombre' => 'Controlada'],
            ['valor' => self::AUTORIZADA, 'nombre' => 'Autorizada'],
            ['valor' => self::PAGADA, 'nombre' => 'Pagada'],
            ['valor' => self::SUSPENDIDA, 'nombre' => 'Suspendida'],
            ['valor' => self::RECHAZADA, 'nombre' => 'Rechazada'],
            ['valor' => self::TERMINADA, 'nombre' => 'Terminada'],
        ];
    }
}
