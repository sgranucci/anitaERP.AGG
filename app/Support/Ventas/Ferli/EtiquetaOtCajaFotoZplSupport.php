<?php

namespace App\Support\Ventas\Ferli;

/**
 * Foto de la etiqueta de caja (OT).
 *
 * El .zpl de cada artículo trae la imagen con la compresión de ~DG
 * (repeticiones G–Y / g–z, coma y !). Ese formato lo entiende ~DG, no ^GFA.
 * Con la impresora recién encendida ^GFA no dibuja la foto. Una etiqueta de
 * Anita en C la baja a la RAM y, a partir de ahí, el ERP también la muestra.
 * Cada trabajo la vuelve a bajar y la llama con ^XG, sin depender de Anita.
 * Además el ancho tiene que cubrir el origen X=670; ^PW792 la deja afuera.
 */
final class EtiquetaOtCajaFotoZplSupport
{
    /** Nombre en RAM (R:). Se pisa en cada etiqueta del mismo envío. */
    private const NOMBRE = 'OTFOTO';

    /** 4 pulgadas a 300 dpi. La Zebra recorta si el cabezal es más angosto. */
    private const ANCHO_MINIMO = 1248;

    /**
     * @return array{descarga: string, campo: string, ancho: int}
     */
    public static function preparar(string $contenido): array
    {
        $normalizado = str_replace(["\r\n", "\r"], "\n", $contenido);

        if (! preg_match('/\^FO(\d+),(\d+)\^GFA,(\d+),(\d+),(\d+),(.*)\^FS/s', $normalizado, $m)) {
            return [
                'descarga' => '',
                'campo' => $contenido,
                'ancho' => self::ANCHO_MINIMO,
            ];
        }

        $origenX = (int) $m[1];
        $origenY = (int) $m[2];
        $totalBytes = (int) $m[3];
        $bytesPorFila = (int) $m[5];
        $data = trim($m[6]);

        $ancho = max(self::ANCHO_MINIMO, $origenX + ($bytesPorFila * 8));

        $descarga = '~DGR:'.self::NOMBRE.'.GRF,'.$totalBytes.','.$bytesPorFila.','.$data."\r\n";
        $campo = '^FO'.$origenX.','.$origenY.'^XGR:'.self::NOMBRE.'.GRF,1,1^FS';

        return [
            'descarga' => $descarga,
            'campo' => $campo,
            'ancho' => $ancho,
        ];
    }
}
