<?php

namespace App\Support\Sueldos;

/**
 * Filtro de empleados / emisión de recibos (espejo help_4 de l-recibolargo.c):
 *  1. Todos            — todos los legajos de la empresa de la corrida
 *  2. Empresa actual   — solo legajos con una sola empresa (mono)
 *  3. Multiempresa     — solo legajos en >1 empresa; al emitir, incluye recibos
 *                        del mismo legajo/período/tipo en otras empresas
 */
class LiquidacionAlcanceRecibo
{
    public const TODOS = 'todos';

    public const EMPRESA_ACTUAL = 'empresa_actual';

    public const MULTIEMPRESA = 'multiempresa';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::TODOS => 'Todos',
        self::EMPRESA_ACTUAL => 'Empresa actual (solo mono-empresa)',
        self::MULTIEMPRESA => 'Multiempresa (legajos en varias empresas)',
    ];

    public static function normalizar(?string $alcance): string
    {
        $a = trim((string) $alcance);

        return isset(self::ETIQUETAS[$a]) ? $a : self::TODOS;
    }

    public static function esMultiempresa(?string $alcance): bool
    {
        return self::normalizar($alcance) === self::MULTIEMPRESA;
    }

    /** @return list<string> */
    public static function permitidos(): array
    {
        return array_keys(self::ETIQUETAS);
    }
}
