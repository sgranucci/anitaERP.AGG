<?php

namespace App\Support\Stock;

/**
 * Gate de pantallas/maestros SIFAB exclusivos de INTERFORMING.
 */
final class InterformingSifabSupport
{
    public static function esInterforming(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'INTERFORMING';
    }

    public static function abortSiNoInterforming(): void
    {
        abort_unless(self::esInterforming(), 404);
    }
}
