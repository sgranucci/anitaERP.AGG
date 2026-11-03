<?php

namespace App\Support\Ventas\Ferli;

/**
 * Foto de la etiqueta de caja (OT).
 *
 * Los .zpl de fotos_articulos salen de Labelary con la compresión de ~DG
 * (repeticiones G–Y / g–z, coma, ! y :) metida dentro de un ^GFA. Esa
 * compresión es solo de ~DG/~DB: con la impresora recién encendida el ^GFA
 * no dibuja nada. Anita en C baja la imagen con ~DG y a partir de ahí el
 * firmware a veces interpreta el mismo dato; por eso “después de Anita sí”.
 *
 * Acá se expande a hex plano y se manda ^GFA ASCII puro.
 *
 * Zebra 1 (ZT411 203 dpi, cabezal 832) tiene guardado zpl.left_position
 * = -214: recién encendida corre todo 214 puntos a la derecha y la foto
 * queda fuera del cabezal. El EPL de Anita anula ese corrimiento hasta el
 * próximo apagado. La etiqueta manda ^LS0 y la foto en X=456, donde sale
 * después de Anita; los .zpl la traen en X=670, que se pasa de 832.
 */
final class EtiquetaOtCajaFotoZplSupport
{
    /** Ancho del cabezal de la ZT411 a 203 dpi. */
    private const ANCHO_MINIMO = 832;

    private const FOTO_X = 456;

    /** ^LS de la etiqueta: negativo corre a la derecha. -30 la centra en el rollo. */
    public const CORRIMIENTO_LS = -30;

    /**
     * @return array{campo: string, ancho: int}
     */
    public static function preparar(string $contenido): array
    {
        $normalizado = str_replace(["\r\n", "\r"], "\n", $contenido);

        if (! preg_match('/\^FO(\d+),(\d+)\^GFA,(\d+),(\d+),(\d+),(.*)\^FS/s', $normalizado, $m)) {
            return [
                'campo' => $contenido,
                'ancho' => self::ANCHO_MINIMO,
            ];
        }

        $origenY = (int) $m[2];
        $totalBytes = (int) $m[3];
        $bytesPorFila = (int) $m[5];
        $data = trim($m[6]);

        $ancho = self::ANCHO_MINIMO;
        $origenX = max(0, min(self::FOTO_X, $ancho - ($bytesPorFila * 8)));

        $hex = self::expandirCompresionDg($data, $bytesPorFila, $totalBytes);
        if ($hex === null) {
            $hex = preg_replace('/\s+/', '', $data) ?? $data;
        }

        $bytesReales = intdiv(strlen($hex), 2);
        if ($bytesReales > 0) {
            $totalBytes = $bytesReales;
        }

        $campo = '^FO'.$origenX.','.$origenY
            .'^GFA,'.$totalBytes.','.$totalBytes.','.$bytesPorFila.','
            .$hex.'^FS';

        return [
            'campo' => $campo,
            'ancho' => $ancho,
        ];
    }

    /**
     * Expande la “Alternative Data Compression Scheme” de ~DG a hex ASCII.
     * Devuelve null si el dato no cierra bien.
     */
    public static function expandirCompresionDg(string $data, int $bytesPorFila, int $totalBytesEsperados): ?string
    {
        if ($bytesPorFila <= 0 || $totalBytesEsperados <= 0) {
            return null;
        }

        $rowHexLen = $bytesPorFila * 2;
        $filasEsperadas = (int) ceil($totalBytesEsperados / $bytesPorFila);
        $high = ' ghijklmnopqrstuvwxyz';
        $low = ' GHIJKLMNOPQRSTUVWXY';

        $filas = [];
        $actual = '';
        $anterior = '';
        $repetir = 0;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $c = $data[$i];

            if ($c === "\n" || $c === "\r" || $c === ' ' || $c === "\t") {
                continue;
            }

            if ($c === ':') {
                if ($actual !== '' || $anterior === '') {
                    return null;
                }
                $filas[] = $anterior;

                continue;
            }

            if ($c === ',' || $c === '!') {
                $relleno = $c === '!' ? 'F' : '0';
                $fila = str_pad($actual, $rowHexLen, $relleno);
                if (strlen($fila) > $rowHexLen) {
                    $fila = substr($fila, 0, $rowHexLen);
                }
                $filas[] = $fila;
                $anterior = $fila;
                $actual = '';

                continue;
            }

            $idxHigh = strpos($high, $c);
            if ($idxHigh !== false && $idxHigh > 0) {
                $repetir += $idxHigh * 20;

                continue;
            }

            $idxLow = strpos($low, $c);
            if ($idxLow !== false && $idxLow > 0) {
                $repetir += $idxLow;

                continue;
            }

            if (! ctype_xdigit($c)) {
                return null;
            }

            $count = $repetir > 0 ? $repetir : 1;
            $repetir = 0;
            $actual .= str_repeat(strtoupper($c), $count);

            while (strlen($actual) >= $rowHexLen) {
                $fila = substr($actual, 0, $rowHexLen);
                $actual = substr($actual, $rowHexLen);
                $filas[] = $fila;
                $anterior = $fila;
            }
        }

        if ($repetir !== 0) {
            return null;
        }

        if ($actual !== '') {
            $filas[] = str_pad($actual, $rowHexLen, '0');
        }

        if ($filas === []) {
            return null;
        }

        if (count($filas) > $filasEsperadas) {
            $filas = array_slice($filas, 0, $filasEsperadas);
        }

        $hex = implode('', $filas);
        $hexEsperado = $totalBytesEsperados * 2;
        if (strlen($hex) > $hexEsperado) {
            $hex = substr($hex, 0, $hexEsperado);
        } elseif (strlen($hex) < $hexEsperado) {
            $hex = str_pad($hex, $hexEsperado, '0');
        }

        if ($hex === '' || (strlen($hex) % 2) !== 0) {
            return null;
        }

        return $hex;
    }
}
