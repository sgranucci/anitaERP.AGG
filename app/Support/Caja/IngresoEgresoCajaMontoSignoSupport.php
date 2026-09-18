<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;

/**
 * Signo al persistir montos de cuenta de caja en IE.
 *
 * El formulario muestra el importe como lo carga el usuario.
 * Al grabar se multiplica por este signo (I = +1, E = −1).
 * TRA no invierte: el usuario ya carga + entrada / − salida.
 */
final class IngresoEgresoCajaMontoSignoSupport
{
    public static function signoPersistencia(?Tipotransaccion_Caja $tipo): int
    {
        if (IngresoEgresoTransferenciaSupport::esTransferencia($tipo)) {
            return 1;
        }
        if ($tipo && strtoupper(trim((string) ($tipo->signo ?? ''))) !== 'I') {
            return -1;
        }

        return 1;
    }

    public static function aBaseDatos(float $montoFormulario, ?Tipotransaccion_Caja $tipo): float
    {
        return round($montoFormulario * self::signoPersistencia($tipo), 2);
    }

    public static function aFormulario(float $montoDb, ?Tipotransaccion_Caja $tipo): float
    {
        return round($montoDb * self::signoPersistencia($tipo), 2);
    }
}
