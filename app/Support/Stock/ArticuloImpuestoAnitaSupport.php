<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Mapea stkm_cod_impuesto de Anita al id de `impuesto` del ERP.
 *
 * En El Bierzo, a-comprob trata el código 1 como IVA 10,5 % (tipo_iva 2),
 * no como exento. El id 1 del ERP es Exento.
 */
final class ArticuloImpuestoAnitaSupport
{
    public static function impuestoIdDesdeCodigoAnita(?string $codigoAnita): int
    {
        $codigo = trim((string) $codigoAnita);

        if (EntornoEmpresaSupport::esElBierzo()) {
            return self::impuestoIdBierzo($codigo);
        }

        if ($codigo === '' || $codigo === '0') {
            return 1;
        }

        $n = (int) $codigo;

        return $n > 4 ? 1 : ($n > 0 ? $n : 1);
    }

    public static function tasaDesdeCodigoAnita(?string $codigoAnita): ?float
    {
        $codigo = trim((string) $codigoAnita);

        if (EntornoEmpresaSupport::esElBierzo()) {
            if ($codigo === '' || $codigo === '0') {
                return 0.0;
            }
            if (isset($codigo[0]) && $codigo[0] === '1') {
                return 10.5;
            }
            $n = (int) $codigo;
            if ($n === 2) {
                return 10.5;
            }
            if ($n === 3) {
                return 21.0;
            }

            return null;
        }

        if ($codigo === '' || $codigo === '0') {
            return 0.0;
        }
        $n = (int) $codigo;
        if ($n === 1) {
            return 0.0;
        }
        if ($n === 2) {
            return 10.5;
        }
        if ($n === 3) {
            return 21.0;
        }

        return null;
    }

    private static function impuestoIdBierzo(string $codigo): int
    {
        if ($codigo === '' || $codigo === '0') {
            return 1;
        }
        if (isset($codigo[0]) && $codigo[0] === '1') {
            return 2;
        }
        $n = (int) $codigo;
        if ($n === 2) {
            return 2;
        }
        if ($n === 3) {
            return 3;
        }

        return 1;
    }
}
