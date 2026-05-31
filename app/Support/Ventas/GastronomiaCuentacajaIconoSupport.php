<?php

namespace App\Support\Ventas;

/**
 * Icono y etiqueta de botón acordes al nombre/código de una cuenta de caja gastronomía.
 */
final class GastronomiaCuentacajaIconoSupport
{
    /**
     * @return array{icono: string, color: string}
     */
    public static function resolver(string $nombre, ?string $codigo = null): array
    {
        $texto = self::normalizarTexto($nombre.' '.(string) $codigo);

        foreach (self::reglas() as $regla) {
            foreach ($regla['keywords'] as $keyword) {
                if (str_contains($texto, self::normalizarTexto($keyword))) {
                    return [
                        'icono' => $regla['icono'],
                        'color' => $regla['color'],
                    ];
                }
            }
        }

        return [
            'icono' => 'fa fa-cash-register',
            'color' => 'text-secondary',
        ];
    }

    public static function etiquetaBoton(string $nombre, ?string $codigo = null): string
    {
        $texto = self::normalizarTexto($nombre.' '.(string) $codigo);

        if (str_contains($texto, 'MERCADO PAGO') || str_contains($texto, 'MERCADOPAGO') || str_contains($texto, ' GMEP')) {
            return 'Mercado Pago';
        }
        if (str_contains($texto, 'TOTALCOIN') || str_contains($texto, 'TOTAL COIN')) {
            return 'Totalcoin';
        }
        if (str_contains($texto, 'FISERV')) {
            return 'Fiserv';
        }
        if (str_contains($texto, 'CTG') || str_contains($texto, 'CANJE TARJETA')) {
            return 'Canje tarjeta';
        }
        if (str_contains($texto, 'VISA')) {
            return 'Visa';
        }
        if (str_contains($texto, 'EFECTIVO') || str_contains($texto, 'CAJA PESOS')) {
            return 'Efectivo';
        }

        $codigo = trim((string) $codigo);
        if ($codigo !== '') {
            return $codigo;
        }

        $nombre = trim($nombre);
        if ($nombre === '') {
            return 'Medio';
        }

        $palabras = preg_split('/\s+/', $nombre) ?: [];
        $palabras = array_values(array_filter($palabras));

        if (count($palabras) <= 2) {
            return $nombre;
        }

        return implode(' ', array_slice($palabras, 0, 2));
    }

    /**
     * @return array{icono: string, color: string, etiqueta_boton: string}
     */
    public static function presentacion(string $nombre, ?string $codigo = null): array
    {
        $icono = self::resolver($nombre, $codigo);

        return [
            'icono' => $icono['icono'],
            'color' => $icono['color'],
            'etiqueta_boton' => self::etiquetaBoton($nombre, $codigo),
        ];
    }

    /**
     * @return list<array{keywords: list<string>, icono: string, color: string}>
     */
    private static function reglas(): array
    {
        return [
            [
                'keywords' => ['mercado pago', 'mercadopago', 'gmep'],
                'icono' => 'gastro-icon-mercadopago',
                'color' => '',
            ],
            [
                'keywords' => ['total coin', 'totalcoin'],
                'icono' => 'fa fa-coins',
                'color' => 'text-warning',
            ],
            [
                'keywords' => ['canje tarjeta', 'canje tarjetas', 'ctg'],
                'icono' => 'fa fa-barcode',
                'color' => 'text-primary',
            ],
            [
                'keywords' => ['totem', 'tótem'],
                'icono' => 'fa fa-tablet-alt',
                'color' => 'text-info',
            ],
            [
                'keywords' => ['visa'],
                'icono' => 'fab fa-cc-visa',
                'color' => 'text-primary',
            ],
            [
                'keywords' => ['master', 'mastercard', 'cabal'],
                'icono' => 'fab fa-cc-mastercard',
                'color' => 'text-danger',
            ],
            [
                'keywords' => ['amex', 'american express'],
                'icono' => 'fab fa-cc-amex',
                'color' => 'text-info',
            ],
            [
                'keywords' => ['fiserv', 'posnet', 'getnet', 'payway', 'tarjeta', 'credito', 'crédito', 'debito', 'débito'],
                'icono' => 'fa fa-credit-card',
                'color' => 'text-primary',
            ],
            [
                'keywords' => ['transferencia', 'interbanking', 'cbu', 'banco'],
                'icono' => 'fa fa-university',
                'color' => 'text-secondary',
            ],
            [
                'keywords' => ['cheque'],
                'icono' => 'fa fa-money-check',
                'color' => 'text-success',
            ],
            [
                'keywords' => ['dolar', 'dólar', 'usd', 'u$s'],
                'icono' => 'fa fa-dollar-sign',
                'color' => 'text-success',
            ],
            [
                'keywords' => ['euro', 'eur'],
                'icono' => 'fa fa-euro-sign',
                'color' => 'text-success',
            ],
            [
                'keywords' => ['efectivo', 'caja pesos', 'caja $', 'pesos', 'billete', 'moneda'],
                'icono' => 'fa fa-money-bill-wave',
                'color' => 'text-success',
            ],
        ];
    }

    private static function normalizarTexto(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'U', 'N'], $texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}
