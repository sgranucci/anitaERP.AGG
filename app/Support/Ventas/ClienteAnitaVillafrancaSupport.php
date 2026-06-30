<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Coeficiente;

/**
 * Réplica climae en Anita Villafranca (/usr2/villafranca) para clientes EL BIERZO con coeficiente > 0.
 */
final class ClienteAnitaVillafrancaSupport
{
    public static function aplicaEmpresa(): bool
    {
        return config('app.empresa') === 'EL BIERZO';
    }

    public static function pathSistema(): string
    {
        return rtrim((string) config('cliente_anita.villafranca.path_sistema', '/usr2/villafranca'), '/');
    }

    public static function codigoCoeficienteDesdeId(?int $coeficienteId): int
    {
        if ($coeficienteId === null || $coeficienteId <= 0) {
            return 0;
        }

        $codigo = Coeficiente::query()->whereKey($coeficienteId)->value('codigo');

        return max(0, (int) ($codigo ?? 0));
    }

    public static function debeSincronizar(int $codigoCoeficiente): bool
    {
        return self::aplicaEmpresa()
            && $codigoCoeficiente > 0
            && self::pathSistema() !== '';
    }
}
