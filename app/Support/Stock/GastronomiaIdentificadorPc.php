<?php

declare(strict_types=1);

namespace App\Support\Stock;

use Illuminate\Http\Request;

/**
 * Identificador de terminal para gastronomía (coincide con configuracion_puntoventa_gastronomia.identificador_pc).
 *
 * Modo IP cliente: ver GASTRONOMIA_IDENTIFICADOR_USAR_IP_CLIENTE y TrustProxies detrás de nginx/load balancer.
 */
final class GastronomiaIdentificadorPc
{
    public static function resolver(?Request $request = null): string
    {
        $request ??= app()->bound('request') ? request() : null;

        if (filter_var(config('gastronomia.identificador_pc_usar_ip_cliente'), FILTER_VALIDATE_BOOLEAN)
            && $request instanceof Request) {
            $ip = $request->ip();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        return (string) config('gastronomia.identificador_pc');
    }
}
