<?php

namespace App\Support\Solicitudpago;

final class ConceptoSolicitudpagoFormaPago
{
    public const SIN_CUOTAS = 'SIN_CUOTAS';

    public const CUOTAS = 'CUOTAS';

    public static function desdeAnita(int|string|null $valor): string
    {
        return ((int) $valor) === 1 ? self::CUOTAS : self::SIN_CUOTAS;
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::SIN_CUOTAS, 'nombre' => 'Sin cuotas'],
            ['valor' => self::CUOTAS, 'nombre' => 'Cuotas'],
        ];
    }
}
