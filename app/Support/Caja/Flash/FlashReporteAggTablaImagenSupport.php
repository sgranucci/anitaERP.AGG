<?php

namespace App\Support\Caja\Flash;

use RuntimeException;

/**
 * PNG de la matriz Tabla!A1:G13 para el mail del Flash Report AGG.
 */
final class FlashReporteAggTablaImagenSupport
{
    public const ANCHO_COL_PX = 120;

    public const ALTO_FILA_PX = 26;

    public const FILAS = 13;

    public const COLUMNAS = 7;

    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    private const FONT_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /**
     * @param  list<list<array{texto: string, negrita?: bool, rojo?: bool, encabezado?: bool}>>  $celdas
     */
    public function generar(array $celdas, string $destino): void
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Se requiere GD para armar la imagen de Tabla A1:G13.');
        }

        $dir = dirname($destino);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear '.$dir);
        }

        $ancho = self::COLUMNAS * self::ANCHO_COL_PX + 1;
        $alto = self::FILAS * self::ALTO_FILA_PX + 1;
        $img = imagecreatetruecolor($ancho, $alto);
        imageantialias($img, true);

        $blanco = imagecolorallocate($img, 255, 255, 255);
        $cabecera = imagecolorallocate($img, 133, 193, 233);
        $etiqueta = imagecolorallocate($img, 244, 246, 247);
        $texto = imagecolorallocate($img, 23, 32, 42);
        $rojo = imagecolorallocate($img, 192, 57, 43);
        $borde = imagecolorallocate($img, 204, 204, 204);
        imagefilledrectangle($img, 0, 0, $ancho - 1, $alto - 1, $blanco);

        $font = is_file(self::FONT) ? self::FONT : null;
        $fontBold = is_file(self::FONT_BOLD) ? self::FONT_BOLD : $font;

        for ($r = 0; $r < self::FILAS; $r++) {
            for ($c = 0; $c < self::COLUMNAS; $c++) {
                $celda = $celdas[$r][$c] ?? ['texto' => ''];
                $x = $c * self::ANCHO_COL_PX;
                $y = $r * self::ALTO_FILA_PX;
                $esEncabezado = ! empty($celda['encabezado']);
                $fondo = $blanco;
                if ($esEncabezado) {
                    $fondo = $cabecera;
                } elseif ($c === 0 && trim((string) ($celda['texto'] ?? '')) !== '') {
                    $fondo = $etiqueta;
                }
                imagefilledrectangle($img, $x, $y, $x + self::ANCHO_COL_PX, $y + self::ALTO_FILA_PX, $fondo);
                imagerectangle($img, $x, $y, $x + self::ANCHO_COL_PX, $y + self::ALTO_FILA_PX, $borde);

                $txt = trim((string) ($celda['texto'] ?? ''));
                if ($txt === '') {
                    continue;
                }
                $color = ! empty($celda['rojo']) ? $rojo : $texto;
                $negrita = $esEncabezado || ! empty($celda['negrita']);
                $this->texto($img, $txt, $x, $y, $color, $negrita, $c > 0 && $r > 0 && ! $esEncabezado, $font, $fontBold);
            }
        }

        imagepng($img, $destino);
        imagedestroy($img);
    }

    private function texto(
        \GdImage $img,
        string $txt,
        int $x,
        int $y,
        int $color,
        bool $negrita,
        bool $derecha,
        ?string $font,
        ?string $fontBold,
    ): void {
        $ttf = $negrita ? $fontBold : $font;
        if ($ttf !== null) {
            $size = 9;
            $bbox = imagettfbbox($size, 0, $ttf, $txt);
            $tw = abs($bbox[2] - $bbox[0]);
            $th = abs($bbox[7] - $bbox[1]);
            $tx = $derecha
                ? $x + self::ANCHO_COL_PX - 8 - $tw
                : $x + 6;
            $ty = $y + (int) ((self::ALTO_FILA_PX + $th) / 2) - 1;
            imagettftext($img, $size, 0, $tx, $ty, $color, $ttf, $txt);

            return;
        }

        $fontId = $negrita ? 3 : 2;
        $tw = imagefontwidth($fontId) * strlen($txt);
        $th = imagefontheight($fontId);
        $tx = $derecha
            ? $x + self::ANCHO_COL_PX - 6 - $tw
            : $x + 4;
        $ty = $y + (int) ((self::ALTO_FILA_PX - $th) / 2);
        imagestring($img, $fontId, max($x + 2, $tx), $ty, $txt, $color);
    }
}
