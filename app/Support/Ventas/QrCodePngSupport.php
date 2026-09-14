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

    private static function pngConGd(string $contenido, int $sizePx, int $marginModulos): string
    {
        $qr = Encoder::encode($contenido, ErrorCorrectionLevel::M());
        $matrix = $qr->getMatrix();
        $modulos = $matrix->getWidth();
        $lado = $modulos + (2 * $marginModulos);
        $escala = max(1, (int) floor($sizePx / max(1, $lado)));
        $pixeles = $lado * $escala;

        $img = imagecreate($pixeles, $pixeles);
        if ($img === false) {
            throw new \RuntimeException('No se pudo crear la imagen del QR.');
        }

        $blanco = imagecolorallocate($img, 255, 255, 255);
        $negro = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $blanco);

        for ($y = 0; $y < $modulos; $y++) {
            for ($x = 0; $x < $modulos; $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }
                $x0 = ($x + $marginModulos) * $escala;
                $y0 = ($y + $marginModulos) * $escala;
                imagefilledrectangle(
                    $img,
                    $x0,
                    $y0,
                    $x0 + $escala - 1,
                    $y0 + $escala - 1,
                    $negro
                );
            }
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
