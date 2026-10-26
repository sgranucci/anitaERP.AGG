<?php

namespace App\Support\Caja;

use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Comprobantes internos de ingresos y egresos: letra A y sucursal 0.
 * ICO e IDO toman el correlativo de compras.t_comp (tcomp_refer → ventas.numerador).
 * DTC toma el próximo número del numerador ERP caja.dtc.
 */
final class IngresoEgresoComprobanteIvaNumeracionSupport
{
    public const LETRA = 'A';

    public const SUCURSAL = 0;

    /** @var list<string> */
    public const TIPOS = ['ICO', 'IDO'];

    /** @var list<string> */
    public const TIPOS_AUTOMATICOS = ['ICO', 'IDO', 'DTC'];

    public static function esAutomatico(?string $abreviatura): bool
    {
        $abrev = strtoupper(substr(trim((string) $abreviatura), 0, 3));

        return in_array($abrev, self::TIPOS_AUTOMATICOS, true);
    }

    public static function esDtc(?string $abreviatura): bool
    {
        return strtoupper(substr(trim((string) $abreviatura), 0, 3)) === 'DTC';
    }

    public static function siguienteNumero(string $abreviatura): int
    {
        $abrev = strtoupper(substr(trim($abreviatura), 0, 3));
        if (! in_array($abrev, self::TIPOS, true)) {
            throw new \InvalidArgumentException('El tipo '.$abrev.' no numera por el talonario de Anita.');
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
