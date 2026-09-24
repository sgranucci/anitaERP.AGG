<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;

/**
 * Signo al persistir montos de cuenta de caja en IE.
 *
 * Contrato I/E (no TRA/canje): el formulario trabaja con importe absoluto;
 * al grabar se firma según el tipo (I = +1, E = −1). Si el usuario carga un
 * monto negativo (la UI lo trata como Haber), se toma abs() para no aplicar
 * el signo dos veces e invertir Debe/Haber en el asiento.
 *
 * TRA/canje: el usuario ya carga + entrada / − salida; no se altera el signo.
 */
final class IngresoEgresoCajaMontoSignoSupport
{
    public static function signoPersistencia(?Tipotransaccion_Caja $tipo): int
    {
        if (IngresoEgresoTransferenciaSupport::esTransferencia($tipo)
            || IngresoEgresoCanjeChequeSupport::esCanje($tipo)) {
            return 1;
        }
        if ($tipo && strtoupper(trim((string) ($tipo->signo ?? ''))) !== 'I') {
            return -1;
        }

        return 1;
    }

    /**
     * Monto firmado para DB / asiento a partir del valor del formulario.
     */
    public static function aBaseDatos(float $montoFormulario, ?Tipotransaccion_Caja $tipo): float
    {
        if (IngresoEgresoTransferenciaSupport::esTransferencia($tipo)
            || IngresoEgresoCanjeChequeSupport::esCanje($tipo)) {
            return round($montoFormulario, 2);
        }

        return round(abs($montoFormulario) * self::signoPersistencia($tipo), 2);
    }

    /**
     * Alias semántico para el armado del asiento (misma regla que aBaseDatos).
     */
    public static function importeFirmadoParaAsiento(float $montoFormulario, ?Tipotransaccion_Caja $tipo): float
    {
        return self::aBaseDatos($montoFormulario, $tipo);
    }

    /**
     * Valor a mostrar en el formulario a partir del monto en DB.
     * I/E: siempre absoluto. TRA/canje: conserva el signo.
     */
    public static function aFormulario(float $montoDb, ?Tipotransaccion_Caja $tipo): float
    {
        if (IngresoEgresoTransferenciaSupport::esTransferencia($tipo)
            || IngresoEgresoCanjeChequeSupport::esCanje($tipo)) {
            return round($montoDb, 2);
        }

        return round(abs($montoDb), 2);
    }
}
