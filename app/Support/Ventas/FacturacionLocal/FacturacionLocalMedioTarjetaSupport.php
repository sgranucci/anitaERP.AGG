<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Medio de tarjeta del POS Local: pide número de cupón del posnet.
 * Canje, efectivo, transferencia y billeteras no lo piden.
 */
final class FacturacionLocalMedioTarjetaSupport
{
    public static function pideCupon(string $nombre, ?string $codigo = null): bool
    {
        $texto = self::normalizar($nombre.' '.(string) $codigo);
        if ($texto === '') {
            return false;
        }

        foreach (['CANJE', 'CTG', 'EFECTIVO', 'TRANSFER', 'MERCADO PAGO', 'MERCADOPAGO', 'CHEQUE', 'DOLAR', 'EURO'] as $excluido) {
            if (str_contains($texto, $excluido)) {
                return false;
            }
        }

        foreach ([
            'VISA', 'MASTER', 'MAESTRO', 'CABAL', 'AMEX', 'AMERICAN EXPRESS', 'NARANJA',
            'FISERV', 'POSNET', 'GETNET', 'PAYWAY', 'FIRST DATA',
            'TARJETA', 'CREDITO', 'DEBITO',
        ] as $marca) {
            if (str_contains($texto, $marca)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $texto
        );

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}
