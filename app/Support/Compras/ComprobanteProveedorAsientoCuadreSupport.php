<?php

namespace App\Support\Compras;

/**
 * Cuadre de centavos en el asiento del comprobante de proveedor.
 *
 * La tolerancia 0,05 permite redondeo neto vs COM / conceptos vs total, pero Anita
 * exige Debe = Haber (tol. 0,009). Si se ignora un desvío de 0,01–0,05 el asiento
 * no sincroniza (p. ej. FGA #48: provisión COM 110015,67 vs neto 110015,70).
 */
final class ComprobanteProveedorAsientoCuadreSupport
{
    /** Desvío máximo neto vs COM o conceptos vs total antes de rechazar. */
    public const TOLERANCIA = 0.05;

    /** A partir de 1 centavo hay que imputar la diferencia (no tragarla). */
    public const MIN_CENTAVO = 0.01;

    public static function hayDiferenciaAImputar(float $diferencia): bool
    {
        return abs(round($diferencia, 2)) >= self::MIN_CENTAVO;
    }

    /**
     * Suma $ajuste a una línea DEBE. Evita la cuenta excluida (provisión FAR)
     * para no dejar saldo residual contra el asiento de la COM.
     *
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>  $lineasDebe
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>
     */
    public static function absorberCentavosEnDebe(
        array $lineasDebe,
        float $ajuste,
        int $cuentaExcluidaId = 0,
    ): array {
        $ajuste = round($ajuste, 2);
        if (! self::hayDiferenciaAImputar($ajuste) || abs($ajuste) > self::TOLERANCIA) {
            return $lineasDebe;
        }

        for ($i = count($lineasDebe) - 1; $i >= 0; $i--) {
            if ($cuentaExcluidaId > 0 && (int) ($lineasDebe[$i]['cuentacontable_id'] ?? 0) === $cuentaExcluidaId) {
                continue;
            }

            $nuevo = round((float) ($lineasDebe[$i]['importe'] ?? 0) + $ajuste, 2);
            if ($nuevo > 0) {
                $lineasDebe[$i]['importe'] = $nuevo;

                return $lineasDebe;
            }
        }

        $ultimo = count($lineasDebe) - 1;
        if ($ultimo >= 0) {
            $lineasDebe[$ultimo]['importe'] = round((float) ($lineasDebe[$ultimo]['importe'] ?? 0) + $ajuste, 2);
        }

        return $lineasDebe;
    }
}
