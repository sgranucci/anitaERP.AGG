<?php

namespace App\Support\Solicitudpago;

final class ConceptoSolicitudpagoEstados
{
    public const ACTIVO = 'ACTIVO';

    public const SUSPENDIDO = 'SUSPENDIDO';

    public static function desdeAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return $v === 'S' ? self::SUSPENDIDO : self::ACTIVO;
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::ACTIVO, 'nombre' => 'Activo'],
            ['valor' => self::SUSPENDIDO, 'nombre' => 'Suspendido'],
        ];
    }
}
