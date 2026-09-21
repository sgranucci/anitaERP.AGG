<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Presentación de medios de pago en botones rápidos del POS Local.
 */
final class FacturacionLocalMedioPresentacionSupport
{
    /**
     * @return array{icono:string,icono_color:string,tema:string,etiqueta:string,pide_cupon:bool}
     */
    public static function presentacion(string $nombre, ?string $codigo = null): array
    {
        $texto = self::normalizar($nombre.' '.(string) $codigo);
        $regla = self::resolverRegla($texto);
        $pideCupon = FacturacionLocalMedioTarjetaSupport::pideCupon($nombre, $codigo);

        return [
            'icono' => $regla['icono'],
            'icono_color' => $regla['icono_color'],
            'tema' => $regla['tema'],
            'etiqueta' => $regla['etiqueta'],
            'pide_cupon' => $pideCupon,
        ];
    }

    /**
     * @return array{icono:string,icono_color:string,tema:string,etiqueta:string}
     */
    private static function resolverRegla(string $texto): array
    {
        foreach (self::reglas() as $regla) {
            foreach ($regla['keywords'] as $keyword) {
                if (str_contains($texto, self::normalizar($keyword))) {
                    return [
                        'icono' => $regla['icono'],
                        'icono_color' => $regla['icono_color'],
                        'tema' => $regla['tema'],
                        'etiqueta' => $regla['etiqueta'],
                    ];
                }
            }
        }

        return [
            'icono' => 'fa fa-wallet',
            'icono_color' => 'text-secondary',
            'tema' => 'default',
            'etiqueta' => 'Medio',
        ];
    }

    /**
     * @return list<array{keywords:list<string>,icono:string,icono_color:string,tema:string,etiqueta:string}>
     */
    private static function reglas(): array
    {
        return [
            [
                'keywords' => ['mercado pago', 'mercadopago', 'mep locales', 'mep ', ' gmep'],
                'icono' => 'fl-icon-mercadopago',
                'icono_color' => '',
                'tema' => 'mp',
                'etiqueta' => 'Mercado Pago',
            ],
            [
                'keywords' => ['american express', 'amex'],
                'icono' => 'fab fa-cc-amex',
                'icono_color' => '',
                'tema' => 'amex',
                'etiqueta' => 'American Express',
            ],
            [
                'keywords' => ['mastercard', 'master card'],
                'icono' => 'fab fa-cc-mastercard',
                'icono_color' => '',
                'tema' => 'master',
                'etiqueta' => 'Mastercard',
            ],
            [
                'keywords' => ['maestro'],
                'icono' => 'fab fa-cc-mastercard',
                'icono_color' => '',
                'tema' => 'maestro',
                'etiqueta' => 'Maestro',
            ],
            [
                'keywords' => ['visa'],
                'icono' => 'fab fa-cc-visa',
                'icono_color' => '',
                'tema' => 'visa',
                'etiqueta' => 'Visa',
            ],
            [
                'keywords' => ['cabal'],
                'icono' => 'fa fa-credit-card',
                'icono_color' => '',
                'tema' => 'cabal',
                'etiqueta' => 'Cabal',
            ],
            [
                'keywords' => ['naranja'],
                'icono' => 'fa fa-credit-card',
                'icono_color' => '',
                'tema' => 'naranja',
                'etiqueta' => 'Naranja',
            ],
            [
                'keywords' => ['go cuotas', 'gocuotas'],
                'icono' => 'fa fa-layer-group',
                'icono_color' => '',
                'tema' => 'gocuotas',
                'etiqueta' => 'Go Cuotas',
            ],
            [
                'keywords' => ['fiserv', 'posnet', 'getnet', 'payway', 'first data'],
                'icono' => 'fa fa-credit-card',
                'icono_color' => '',
                'tema' => 'tarjeta',
                'etiqueta' => 'Tarjeta',
            ],
            [
                'keywords' => ['transferencia', 'interbanking', 'cbu', 'banco'],
                'icono' => 'fa fa-university',
                'icono_color' => '',
                'tema' => 'transfer',
                'etiqueta' => 'Transferencia',
            ],
            [
                'keywords' => ['fondo fijo', 'efectivo', 'caja pesos', 'caja $', 'pesos'],
                'icono' => 'fa fa-money-bill-wave',
                'icono_color' => '',
                'tema' => 'efectivo',
                'etiqueta' => 'Efectivo',
            ],
            [
                'keywords' => ['tarjeta', 'credito', 'debito'],
                'icono' => 'fa fa-credit-card',
                'icono_color' => '',
                'tema' => 'tarjeta',
                'etiqueta' => 'Tarjeta',
            ],
        ];
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
