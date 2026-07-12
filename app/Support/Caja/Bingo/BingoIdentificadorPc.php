<?php

declare(strict_types=1);

namespace App\Support\Caja\Bingo;

use Illuminate\Http\Request;

/**
 * Identificador de terminal para bingo (coincide con configuracion_puntoventa_bingo.identificador_pc).
 */
final class BingoIdentificadorPc
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

        if (filter_var(config('bingo.identificador_pc_usar_ip_cliente'), FILTER_VALIDATE_BOOLEAN)
            && $request instanceof Request) {
            $ip = $request->ip();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        $fijo = trim((string) config('bingo.identificador_pc'));

        return $fijo !== '' ? $fijo : (string) gethostname();
    }
}
