<?php

namespace App\Support\Stock\Surmar;

/**
 * Parseo de lectura de etiqueta Surmar (ID ERP o barcode Anita).
 *
 * Anita (a-stkmov.c Ing_articulo): sku-nint-nap-tipo-orden (sep. '-' o "'").
 */
final class SurmarEtiquetaBarcodeSupport
{
    /**
     * @return array{
     *   modo: 'id'|'anita'|'nint_nap'|'vacio',
     *   etiqueta_id: ?int,
     *   sku: ?string,
     *   nro_interno: ?int,
     *   nro_apertura: ?int,
     *   tipo: ?string,
     *   orden: ?int
     * }
     */
    public static function parsear(string $raw): array
    {
        $raw = trim($raw);
        $vacio = [
            'modo' => 'vacio',
            'etiqueta_id' => null,
            'sku' => null,
            'nro_interno' => null,
            'nro_apertura' => null,
            'tipo' => null,
            'orden' => null,
        ];
        if ($raw === '') {
            return $vacio;
        }

        // Solo dígitos → ID ERP
        if (preg_match('/^\d+$/', $raw)) {
            $id = (int) $raw;

            return array_merge($vacio, [
                'modo' => $id > 0 ? 'id' : 'vacio',
                'etiqueta_id' => $id > 0 ? $id : null,
            ]);
        }

        $sep = null;
        if (str_contains($raw, '-')) {
            $sep = '-';
        } elseif (str_contains($raw, "'")) {
            $sep = "'";
        }

        if ($sep !== null) {
            $parts = array_values(array_filter(explode($sep, $raw), fn ($p) => $p !== ''));
            // nint-nap
            if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
                return array_merge($vacio, [
                    'modo' => 'nint_nap',
                    'nro_interno' => (int) $parts[0],
                    'nro_apertura' => (int) $parts[1],
                ]);
            }
            // sku-nint-nap[-tipo[-orden]]
            if (count($parts) >= 3) {
                $sku = ltrim(trim((string) $parts[0]), '0');
                if ($sku === '') {
                    $sku = trim((string) $parts[0]);
                }
                $nint = (int) preg_replace('/\D+/', '', (string) $parts[1]);
                $nap = (int) preg_replace('/\D+/', '', (string) $parts[2]);
                $tipo = isset($parts[3]) ? strtoupper(trim((string) $parts[3])) : null;
                $orden = isset($parts[4]) ? (int) $parts[4] : null;

                if ($nint > 0 && $nap > 0) {
                    return [
                        'modo' => 'anita',
                        'etiqueta_id' => null,
                        'sku' => $sku !== '' ? $sku : null,
                        'nro_interno' => $nint,
                        'nro_apertura' => $nap,
                        'tipo' => $tipo !== '' ? $tipo : null,
                        'orden' => $orden,
                    ];
                }
            }
        }

        return $vacio;
    }
}
