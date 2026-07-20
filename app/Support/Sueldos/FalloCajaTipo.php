<?php

namespace App\Support\Sueldos;

/**
 * Tipos de tabla de fallos de caja (sueldos). En Anita (tblfallo) el tipo es el entero
 * tblf_id (1 = Bingo, 2 = Máquinas); en el ERP se guarda el texto completo en la columna `tipo`.
 *
 * Si el mapeo de Anita estuviera invertido, cambiar únicamente POR_CODIGO_ANITA aquí.
 */
class FalloCajaTipo
{
    public const BINGO = 'Bingo';

    public const MAQUINAS = 'Máquinas';

    /** @var list<string> */
    public const OPCIONES = [
        self::BINGO,
        self::MAQUINAS,
    ];

    /**
     * En Anita tblf_id / agr_id_fallo: 1 = Máquinas, 2 = Bingo.
     * (Confirmado por agrupamiento: "JR DE OP. MAQUINAS" → 1, "JR DE OP. BINGO" → 2.)
     *
     * @var array<int, string>
     */
    public const POR_CODIGO_ANITA = [
        1 => self::MAQUINAS,
        2 => self::BINGO,
    ];

    public static function desdeCodigoAnita(int $codigo): ?string
    {
        return self::POR_CODIGO_ANITA[$codigo] ?? null;
    }

    public static function esValido(?string $tipo): bool
    {
        return $tipo !== null && in_array($tipo, self::OPCIONES, true);
    }
}
