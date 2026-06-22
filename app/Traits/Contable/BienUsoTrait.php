<?php

namespace App\Traits\Contable;

trait BienUsoTrait
{
    /** @var list<array{id: string, valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['id' => '1', 'valor' => 'A', 'nombre' => 'Activo'],
        ['id' => '2', 'valor' => 'I', 'nombre' => 'Inactivo'],
    ];

    /** @var list<array{id: string, valor: string, nombre: string}> */
    public static array $enumTipoBien = [
        ['id' => '1', 'valor' => 'I', 'nombre' => 'Instalaciones'],
        ['id' => '2', 'valor' => 'M', 'nombre' => 'Máquinas'],
        ['id' => '3', 'valor' => 'P', 'nombre' => 'PCs'],
    ];

    public static function labelEnum(string $valor, array $enum): string
    {
        foreach ($enum as $item) {
            if (($item['valor'] ?? '') === $valor) {
                return (string) ($item['nombre'] ?? $valor);
            }
        }

        return $valor;
    }

    public static function labelEstado(?string $valor): string
    {
        return self::labelEnum((string) $valor, self::$enumEstado);
    }

    public static function labelTipoBien(?string $valor): string
    {
        return self::labelEnum((string) $valor, self::$enumTipoBien);
    }
}
