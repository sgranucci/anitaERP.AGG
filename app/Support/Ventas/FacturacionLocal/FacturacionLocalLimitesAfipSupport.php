<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Límites AFIP de identificación del comprador (configurables).
 */
final class FacturacionLocalLimitesAfipSupport
{
    public static function limiteEfectivo(): float
    {
        return (float) config('facturacion_local.limite_efectivo', 200000);
    }

    public static function limiteResto(): float
    {
        return (float) config('facturacion_local.limite_resto', 400000);
    }

    /**
     * @return list<string>
     */
    public static function erroresIdentificacion(
        float $total,
        bool $tieneDatosCliente,
        bool $pagoConTarjeta = false,
    ): array {
        if ($tieneDatosCliente) {
            return [];
        }

        $limite = $pagoConTarjeta ? self::limiteResto() : self::limiteEfectivo();
        if ($total > $limite) {
            return [
                'El total supera el límite AFIP ($'.number_format($limite, 2, ',', '.').'). '
                .'Debe identificar al cliente (nombre y documento).',
            ];
        }

        return [];
    }
}
