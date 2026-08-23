<?php

namespace App\Support\Seguridad;

final class IngresoProveedorEstados
{
    public const PENDIENTE = 'PENDIENTE';

    public const AUTORIZADO = 'AUTORIZADO';

    public const INGRESADO = 'INGRESADO';

    public const FINALIZADO = 'FINALIZADO';

    public const RECHAZADO = 'RECHAZADO';

    /** @var array<string, array{label: string, badge: string}> */
    public const META = [
        self::PENDIENTE => ['label' => 'Pendiente', 'badge' => 'warning'],
        self::AUTORIZADO => ['label' => 'Autorizado', 'badge' => 'success'],
        self::INGRESADO => ['label' => 'Ingresado', 'badge' => 'info'],
        self::FINALIZADO => ['label' => 'Finalizado', 'badge' => 'secondary'],
        self::RECHAZADO => ['label' => 'Rechazado', 'badge' => 'danger'],
    ];

    public static function etiqueta(string $estado): string
    {
        return self::META[strtoupper($estado)]['label'] ?? $estado;
    }

    public static function badge(string $estado): string
    {
        return self::META[strtoupper($estado)]['badge'] ?? 'light';
    }

    public static function puedeAutorizarORechazar(string $estado): bool
    {
        return strtoupper($estado) === self::PENDIENTE;
    }

    public static function permiteEntro(string $estado): bool
    {
        $estado = strtoupper($estado);

        return $estado === self::AUTORIZADO || $estado === self::INGRESADO;
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return array_keys(self::META);
    }
}
