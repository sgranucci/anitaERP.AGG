<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;

/** Código Anita de depósito (depmae.codigo) a partir del id ERP. */
final class DepmaeAnitaCodigoSupport
{
    public static function codigoDeposito(int $depmaeId): int
    {
        if ($depmaeId <= 0) {
            return 0;
        }

        $deposito = Depmae::query()->find($depmaeId);
        $codigo = trim((string) ($deposito?->codigo ?? ''));

        return $codigo !== '' ? (int) $codigo : 0;
    }
}
