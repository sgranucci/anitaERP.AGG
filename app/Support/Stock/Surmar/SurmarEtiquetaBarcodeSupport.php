<?php

namespace App\Support\Stock\Surmar;

/**
 * Parseo de lectura de etiqueta Surmar (ID ERP, QR v2 o barcode Anita)
 * y armado del Code39 a imprimir (formato configurable).
 *
 * - Solo dígitos / Code39 `*id*` / AIM `]A0id` → id ERP (ZPL Surmar).
 * - QR JSON v2 (`{"v":2,"etiqueta_id":…}`) → id ERP.
 * - Anita (a-stkmov.c Ing_articulo / eti_prod.fc): sku-nint-nap-tipo-orden (sep. '-' o "'").
 */
final class SurmarEtiquetaBarcodeSupport
{
    public const FORMATO_ID = 'id';

    public const FORMATO_ANITA = 'anita';

    /**
     * Formato activo del Code39 en ZPL/PDF.
     * Config: recepcion_anita_surmar.etiqueta_barcode_formato (SURMAR_ETIQUETA_BARCODE_FORMATO).
     */
    public static function formatoImpresion(): string
    {
        $fmt = strtolower(trim((string) config('recepcion_anita_surmar.etiqueta_barcode_formato', self::FORMATO_ANITA)));

        return $fmt === self::FORMATO_ID ? self::FORMATO_ID : self::FORMATO_ANITA;
    }

    /**
     * Valor del Code39 a imprimir (ZPL ^FD / preview).
     * Anita: sku-nint-nap-tipo-orden (espejo eti_prod.fc). Sin nro_interno → id.
     *
     * @param  array{
     *   id?:int,
     *   codigo_articulo?:string,
     *   anita_nro_interno?:int|null,
     *   nro_apertura?:int|null,
     *   anita_nro_apertura?:int|null,
     *   anita_tipo?:string|null,
     *   origen_tipo?:string|null,
     *   anita_orden?:int|null
     * }  $d
     */
    public static function codigoParaImpresion(array $d): string
    {
        $id = (int) ($d['id'] ?? 0);
        if (self::formatoImpresion() !== self::FORMATO_ANITA) {
            return $id > 0 ? (string) $id : '';
        }

        $nint = (int) ($d['anita_nro_interno'] ?? 0);
        if ($nint <= 0) {
            return $id > 0 ? (string) $id : '';
        }

        $sku = ltrim(trim((string) ($d['codigo_articulo'] ?? '')), '0');
        if ($sku === '') {
            $sku = trim((string) ($d['codigo_articulo'] ?? ''));
        }
        if ($sku === '') {
            return $id > 0 ? (string) $id : '';
        }

        $nap = (int) ($d['nro_apertura'] ?? $d['anita_nro_apertura'] ?? 1);
        if ($nap < 1) {
            $nap = 1;
        }

        $tipo = strtoupper(trim((string) ($d['anita_tipo'] ?? $d['origen_tipo'] ?? 'COM')));
        if ($tipo === '') {
            $tipo = 'COM';
        }
        $tipo = substr(str_pad($tipo, 3, ' ', STR_PAD_RIGHT), 0, 3);

        $orden = (int) ($d['anita_orden'] ?? 1);
        if ($orden < 1) {
            $orden = 1;
        }

        // Misma forma que eti_prod.fc: sprintf("%s-%ld-%d-%3.3s-%d", …)
        return sprintf('%s-%d-%d-%s-%d', $sku, $nint, $nap, rtrim($tipo), $orden);
    }

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

        // QR v2 (y variantes con prefijo Zebra MA,/QA, o AIM ]Q3): {"v":2,"etiqueta_id":16688,…}
        $desdeQr = self::parsearQrJson($raw, $vacio);
        if ($desdeQr !== null) {
            return $desdeQr;
        }

        // Solo dígitos → ID ERP (tras quitar * de Code39 / AIM)
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
     * Trim, BOM, zero-width, AIM de lector, comillas y delimitadores Code39 (*…*).
     */
    private static function normalizarEntrada(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw) ?? $raw;
        // Zero-width / soft hyphen que meten algunos lectores
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $raw) ?? $raw;
        $raw = trim($raw, " \t\n\r\0\x0B\"'");

        // AIM identifier del wedge: ]A0 (Code39), ]C1 (Code128), ]Q3 (QR), etc.
        if (preg_match('/^\][A-Za-z][0-9A-Za-z](.*)$/s', $raw, $m)) {
            $raw = trim($m[1]);
        }

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
        // JSON embebido en basura del lector
        if (($json === '' || ($json[0] ?? '') !== '{') && preg_match('/(\{.*\})/s', $raw, $m)) {
            $json = $m[1];
        }

        $id = 0;
        if ($json !== '' && ($json[0] ?? '') === '{') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $id = (int) ($data['etiqueta_id'] ?? $data['id'] ?? 0);
            }
        }
        // JSON truncado / comillas tipográficas: buscar etiqueta_id a mano
        if ($id <= 0 && preg_match('/"etiqueta_id"\s*:\s*(\d+)/', $raw, $m)) {
            $id = (int) $m[1];
        }
        if ($id <= 0) {
            return null;
        }

        return array_merge($vacio, [
            'modo' => 'id',
            'etiqueta_id' => $id,
        ]);
    }
}
