<?php

namespace App\Support\Ventas;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * PNG de QR sin depender de Imagick (este entorno solo tiene GD en FPM/CLI).
 */
final class QrCodePngSupport
{
    public static function png(string $contenido, int $sizePx = 500, int $marginModulos = 2): string
    {
        $contenido = trim($contenido);
        if ($contenido === '') {
            return '';
        }

        if (extension_loaded('imagick')) {
            try {
                return (string) QrCode::encoding('UTF-8')
                    ->format('png')
                    ->size(max(50, $sizePx))
                    ->margin(max(0, $marginModulos))
                    ->generate($contenido);
            } catch (\Throwable) {
                // Caída a GD.
            }
        }

        return self::pngConGd($contenido, max(50, $sizePx), max(0, $marginModulos));
    }

    public static function dataUri(string $contenido, int $sizePx = 500, int $marginModulos = 2): string
    {
        $png = self::png($contenido, $sizePx, $marginModulos);
        if ($png === '') {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * SVG vectorial (mejor para laser / cámara: no se interpola como el PNG).
     */
    public static function svgDataUri(string $contenido, int $marginModulos = 4): string
    {
        $svg = self::svg($contenido, $marginModulos);
        if ($svg === '') {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function svg(string $contenido, int $marginModulos = 4): string
    {
        $matrix = self::matriz($contenido, $marginModulos, ErrorCorrectionLevel::Q());
        if ($matrix === null) {
            return '';
        }

        [, $lado, $puntos] = $matrix;
        $rects = [];
        foreach ($puntos as [$x, $y]) {
            $rects[] = '<rect x="'.$x.'" y="'.$y.'" width="1" height="1"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$lado.' '.$lado
            .'" width="'.$lado.'" height="'.$lado.'" shape-rendering="crispEdges">'
            .'<rect width="'.$lado.'" height="'.$lado.'" fill="#ffffff"/>'
            .'<g fill="#000000">'.implode('', $rects).'</g></svg>';
    }

    private static function pngConGd(string $contenido, int $sizePx, int $marginModulos): string
    {
        $matrix = self::matriz($contenido, $marginModulos);
        if ($matrix === null) {
            return '';
        }

        [, $lado, $puntos] = $matrix;
        $escala = max(1, (int) floor($sizePx / max(1, $lado)));
        $pixeles = $lado * $escala;

        $img = imagecreatetruecolor($pixeles, $pixeles);
        if ($img === false) {
            throw new \RuntimeException('No se pudo crear la imagen del QR.');
        }

        $blanco = imagecolorallocate($img, 255, 255, 255);
        $negro = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $pixeles - 1, $pixeles - 1, $blanco);

        foreach ($puntos as [$x, $y]) {
            $x0 = $x * $escala;
            $y0 = $y * $escala;
            imagefilledrectangle(
                $img,
                $x0,
                $y0,
                $x0 + $escala - 1,
                $y0 + $escala - 1,
                $negro
            );
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /**
     * @return array{0: int, 1: int, 2: list<array{0: int, 1: int}>}|null
     */
    private static function matriz(
        string $contenido,
        int $marginModulos,
        ?ErrorCorrectionLevel $errorCorrection = null
    ): ?array {
        $contenido = trim($contenido);
        $marginModulos = max(0, $marginModulos);
        if ($contenido === '') {
            return null;
        }

        $qr = Encoder::encode($contenido, $errorCorrection ?? ErrorCorrectionLevel::M());
        $byteMatrix = $qr->getMatrix();
        $modulos = $byteMatrix->getWidth();
        $lado = $modulos + (2 * $marginModulos);
        $puntos = [];
        for ($y = 0; $y < $modulos; $y++) {
            for ($x = 0; $x < $modulos; $x++) {
                if ($byteMatrix->get($x, $y) !== 1) {
                    continue;
                }
                $puntos[] = [$x + $marginModulos, $y + $marginModulos];
            }
        }

        return [$modulos, $lado, $puntos];
    }
}
