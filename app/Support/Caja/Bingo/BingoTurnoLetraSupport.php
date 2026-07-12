<?php

declare(strict_types=1);

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\TurnoBingo;

final class BingoTurnoLetraSupport
{
    public static function desdeTurno(?TurnoBingo $turno): string
    {
        if ($turno === null) {
            return ' ';
        }

        $codigo = strtoupper(trim((string) ($turno->codigo ?? '')));
        if ($codigo !== '' && preg_match('/^[MTN]$/', $codigo)) {
            return $codigo;
        }

        $nombre = strtoupper(trim((string) ($turno->nombre ?? '')));
        if (str_starts_with($nombre, 'M')) {
            return 'M';
        }
        if (str_starts_with($nombre, 'T')) {
            return 'T';
        }
        if (str_starts_with($nombre, 'N')) {
            return 'N';
        }

        return substr($codigo !== '' ? $codigo : ' ', 0, 1);
    }
}
