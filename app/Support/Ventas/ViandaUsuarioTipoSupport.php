<?php

namespace App\Support\Ventas;

final class ViandaUsuarioTipoSupport
{
    /** @var array<string, string> */
    public const ETIQUETAS = [
        'L' => 'Legajo',
        'A' => 'Administrador',
    ];

    /** @return list<string> */
    public static function tiposValidos(): array
    {
        return array_keys(self::ETIQUETAS);
    }

    public static function etiqueta(?string $tipo): string
    {
        $tipo = strtoupper(trim((string) $tipo));

        return self::ETIQUETAS[$tipo] ?? ($tipo !== '' ? $tipo : '—');
    }

    public static function tipoValido(?string $tipo): bool
    {
        return isset(self::ETIQUETAS[strtoupper(trim((string) $tipo))]);
    }

    public static function esAdministrador(?string $tipo): bool
    {
        return strtoupper(trim((string) $tipo)) === 'A';
    }
}
