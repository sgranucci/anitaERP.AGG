<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Reduce fotos de alta resolución antes del OCR para evitar timeouts y mejorar lectura.
 */
final class RecepcionProveedorOcrImagenSupport
{
    public static function prepararParaOcr(string $rutaAbsoluta, ?string $mime = null): string
    {
        $mime ??= (string) @mime_content_type($rutaAbsoluta);
        if (! str_starts_with($mime, 'image/')) {
            return $rutaAbsoluta;
        }

        $maxAncho = max(1200, (int) config('recepcion_proveedor.ocr.imagen_max_ancho', 2400));
        $info = @getimagesize($rutaAbsoluta);
        if ($info === false || ($info[0] ?? 0) <= $maxAncho) {
            return $rutaAbsoluta;
        }

        $ancho = (int) $info[0];
        $alto = (int) $info[1];
        $nuevoAlto = (int) round($alto * ($maxAncho / $ancho));

        $origen = self::abrirImagen($rutaAbsoluta, (int) ($info[2] ?? 0));
        if ($origen === null) {
            return $rutaAbsoluta;
        }

        $destino = imagecreatetruecolor($maxAncho, $nuevoAlto);
        if ($destino === false) {
            imagedestroy($origen);

            return $rutaAbsoluta;
        }

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $maxAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($origen);

        $tmp = RecepcionProveedorOcrTempSupport::archivo('ocr_img_', 'jpg');
        imagejpeg($destino, $tmp, (int) config('recepcion_proveedor.ocr.imagen_jpeg_calidad', 88));
        imagedestroy($destino);

        return is_readable($tmp) ? $tmp : $rutaAbsoluta;
    }

    /** @return \GdImage|resource|null */
    private static function abrirImagen(string $ruta, int $tipo)
    {
        return match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null,
            default => null,
        };
    }
}
