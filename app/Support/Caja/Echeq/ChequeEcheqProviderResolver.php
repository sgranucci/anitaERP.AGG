<?php

namespace App\Support\Caja\Echeq;

use InvalidArgumentException;

/**
 * Resuelve el driver eCheq configurado (extensible por banco).
 */
final class ChequeEcheqProviderResolver
{
    public static function habilitado(): bool
    {
        return (bool) config('cheque.echeq.habilitado', true);
    }

    public static function resolve(?string $provider = null): ChequeEcheqProviderInterface
    {
        $codigo = strtolower(trim((string) ($provider ?: config('cheque.echeq.provider', 'manual'))));
        if ($codigo === '' || $codigo === 'null' || $codigo === 'none') {
            $codigo = 'manual';
        }

        return match ($codigo) {
            'manual' => new ChequeEcheqManualProvider(),
            // Futuro: 'galicia' => app(GaliciaEcheqProvider::class),
            // Futuro: 'santander' => app(SantanderEcheqProvider::class),
            default => throw new InvalidArgumentException('Provider eCheq no configurado: '.$codigo),
        };
    }
}
