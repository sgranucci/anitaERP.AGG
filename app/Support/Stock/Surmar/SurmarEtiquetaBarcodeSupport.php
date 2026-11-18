<?php

namespace App\Support\Stock\Surmar;

/**
 * Parseo de lectura de etiqueta Surmar (ID ERP, QR v2 o barcode Anita).
 *
 * - Solo dígitos / Code39 `*id*` → id ERP (ZPL Surmar).
 * - QR JSON v2 (`{"v":2,"etiqueta_id":…}`) → id ERP.
 * - Anita (a-stkmov.c Ing_articulo): sku-nint-nap-tipo-orden (sep. '-' o "'").
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
        $raw = self::normalizarEntrada($raw);
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

        // QR v2 (y variantes con prefijo Zebra MA,/QA,): {"v":2,"etiqueta_id":16688,…}
        $desdeQr = self::parsearQrJson($raw, $vacio);
        if ($desdeQr !== null) {
            return $desdeQr;
        }

        // Solo dígitos → ID ERP (tras quitar * de Code39)
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

    /**
     * Trim, BOM, comillas y delimitadores Code39 (*…*).
     */
    private static function normalizarEntrada(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = trim($raw, " \t\n\r\0\x0B\"'");

        // Code39: start/stop asterisks (*16688* o *16688)
        if (preg_match('/^\*(\d+)\*$/', $raw, $m) || preg_match('/^\*(\d+)$/', $raw, $m)) {
            return $m[1];
        }

        return trim($raw);
    }

    /**
     * @param  array{
     *   modo: 'id'|'anita'|'nint_nap'|'vacio',
     *   etiqueta_id: ?int,
     *   sku: ?string,
     *   nro_interno: ?int,
     *   nro_apertura: ?int,
     *   tipo: ?string,
     *   orden: ?int
     * }  $vacio
     * @return array<string, mixed>|null
     */
    private static function parsearQrJson(string $raw, array $vacio): ?array
    {
        $json = $raw;
        // Prefijo opcional de campo ZPL QR (MA,/QA,/HM, …) si el lector lo incluye
        if (preg_match('/^[A-Za-z]{1,2},(\{.*\})\s*$/s', $raw, $m)) {
            $json = $m[1];
        }
        if ($json === '' || $json[0] !== '{') {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        $id = (int) ($data['etiqueta_id'] ?? $data['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return array_merge($vacio, [
            'modo' => 'id',
            'etiqueta_id' => $id,
        ]);
    }
}
