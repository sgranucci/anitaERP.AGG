<?php

declare(strict_types=1);

namespace App\Support\Caja\Estacionamiento;

use Illuminate\Http\Request;

/**
 * Identificador de terminal para estacionamiento (coincide con configuracion_puntoventa_estacionamiento.identificador_pc).
 */
final class EstacionamientoIdentificadorPc
{
    /**
     * Valor sugerido al crear una fila en el ABM (desde el navegador de la terminal).
     * Prioriza la IP del cliente aunque el runtime use identificador fijo por .env.
     */
    public static function sugerirEnFormularioAlta(?Request $request = null): string
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($request instanceof Request) {
            $ip = $request->ip();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        return self::resolver($request);
    }

    public static function resolver(?Request $request = null): string
    {
        $request ??= app()->bound('request') ? request() : null;

        if (filter_var(config('estacionamiento.identificador_pc_usar_ip_cliente'), FILTER_VALIDATE_BOOLEAN)
            && $request instanceof Request) {
            $ip = $request->ip();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        $fijo = trim((string) config('estacionamiento.identificador_pc'));

        return $fijo !== '' ? $fijo : (string) gethostname();
    }
}
