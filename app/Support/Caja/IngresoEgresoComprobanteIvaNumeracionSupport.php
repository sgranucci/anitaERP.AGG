<?php

namespace App\Support\Caja;

use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * ICO e IDO no son facturas: en Anita van con letra A, sucursal 0 y el
 * correlativo de compras.t_comp (tcomp_refer → ventas.numerador).
 */
final class IngresoEgresoComprobanteIvaNumeracionSupport
{
    public const LETRA = 'A';

    public const SUCURSAL = 0;

    /** @var list<string> */
    public const TIPOS = ['ICO', 'IDO'];

    public static function esAutomatico(?string $abreviatura): bool
    {
        $abrev = strtoupper(substr(trim((string) $abreviatura), 0, 3));

        return in_array($abrev, self::TIPOS, true);
    }

    public static function siguienteNumero(string $abreviatura): int
    {
        $abrev = strtoupper(substr(trim($abreviatura), 0, 3));
        if (! self::esAutomatico($abrev)) {
            throw new \InvalidArgumentException('El tipo '.$abrev.' no numera solo.');
        }

        $segundos = 15;
        $lock = Cache::lock('caja:ie:comprobante-iva:numeracion:'.$abrev, $segundos);

        try {
            return $lock->block($segundos, function () use ($abrev) {
                $clave = (int) PagoproveedorAnitaNumeracionSupport::resolverClaveNumeradorDesdeTComp($abrev);
                if ($clave <= 0) {
                    throw new RuntimeException('t_comp '.$abrev.' no tiene numerador Anita.');
                }

                $siguiente = IngresoEgresoAnitaNumeracionSupport::leerUltimoNumero($clave) + 1;
                IngresoEgresoAnitaNumeracionSupport::actualizarNumerador($clave, $siguiente);

                return $siguiente;
            });
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'No se pudo reservar el número de '.$abrev.' en Anita: '.$e->getMessage(),
                0,
                $e
            );
        }
    }
}
