<?php

namespace App\Support\Wigos;

use Illuminate\Support\Facades\Log;

/**
 * Ruta del openssl.cnf local para conexiones Wigos (subproceso con env OPENSSL_CONF).
 * No modifica /etc/ssl/openssl.cnf (evita impacto en SOAP ARCA u otros TLS del mismo worker).
 */
final class WigosSqlServerOpenSsl
{
    public static function rutaConfiguracion(): ?string
    {
        $ruta = trim((string) config('wigos.openssl_conf', ''));
        if ($ruta === '') {
            return null;
        }

        $rutaReal = realpath($ruta);
        if ($rutaReal === false || ! is_readable($rutaReal)) {
            Log::warning('Wigos: no se encontró config OpenSSL para SQL Server', [
                'openssl_conf' => $ruta,
            ]);

            return null;
        }

        return $rutaReal;
    }
}
