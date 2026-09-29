<?php

namespace App\Support\Contable;

use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrImagenSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrTempSupport;
use Symfony\Component\Process\Process;

/**
 * Lee el asiento desde un PDF (texto o escaneado) o desde una foto de la impresión.
 */
final class AsientoImpresionLecturaSupport
{
    private const MAX_PAGINAS = 2;

    private const DPI = 200;

    /**
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    public static function leer(string $ruta, ?string $mime = null): array
    {
        if (! is_readable($ruta)) {
            throw new \RuntimeException('No se pudo leer el archivo.');
        }

        $mime ??= (string) @mime_content_type($ruta);
        $esPdf = $mime === 'application/pdf' || str_ends_with(strtolower($ruta), '.pdf');

        if ($esPdf) {
            $texto = self::textoPdf($ruta);
            $parseado = AsientoPegadoTextoSupport::decodificar($texto);
            if ($parseado['filas'] !== []) {
                return $parseado;
            }

            return self::leerImagenes(self::pdfAPng($ruta));
        }

        $preparada = RecepcionProveedorOcrImagenSupport::prepararParaOcr($ruta, $mime);

        try {
            return self::leerImagenes([$preparada]);
        } finally {
            if ($preparada !== $ruta && is_file($preparada)) {
                @unlink($preparada);
            }
        }
    }

    private static function textoPdf(string $rutaPdf): string
    {
        self::assertBinario('pdftotext');
        $tmp = RecepcionProveedorOcrTempSupport::archivo('asiento_pdf_', 'txt');
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $rutaPdf, $tmp]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! is_readable($tmp)) {
            @unlink($tmp);

            return '';
        }

        $texto = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $texto;
    }

    /**
     * @return list<string>
     */
    private static function pdfAPng(string $rutaPdf): array
    {
        self::assertBinario('pdftoppm');
        $dir = RecepcionProveedorOcrTempSupport::directorio().'/asiento_pdf_'.uniqid('', true);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta temporal para leer el PDF.');
        }
        $prefijo = $dir.'/page';

        $process = new Process([
            'pdftoppm',
            '-png',
            '-r',
            (string) self::DPI,
            '-f',
            '1',
            '-l',
            (string) self::MAX_PAGINAS,
            $rutaPdf,
            $prefijo,
        ]);
        $process->setTimeout(90);
        $process->run();

        $paginas = glob($prefijo.'-*.png') ?: [];
        sort($paginas, SORT_NATURAL);
        if ($paginas === []) {
            self::borrarDirectorio($dir);
            throw new \RuntimeException('No se pudo convertir el PDF a imagen para leerlo.');
        }

        return $paginas;
    }

    /**
     * @param  list<string>  $imagenes
     * @return array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}
     */
    private static function leerImagenes(array $imagenes): array
    {
        self::assertBinario('tesseract');
        $temporales = [];
        $preparadas = [];

        try {
            foreach ($imagenes as $imagen) {
                $ampliada = self::ampliarParaOcr($imagen);
                if ($ampliada !== $imagen) {
                    $temporales[] = $ampliada;
                }
                $legible = self::aclararParaOcr($ampliada);
                if ($legible !== $ampliada) {
                    $temporales[] = $legible;
                }
                $preparadas[] = $legible;
            }

            $primero = self::ocrPreparadas($preparadas, '6');
            if (! AsientoPegadoTextoSupport::lecturaIncompleta($primero['texto'], $primero['parseado'])) {
                return $primero['parseado'];
            }

            $segundo = self::ocrPreparadas($preparadas, '4');

            return AsientoPegadoTextoSupport::elegirLectura($primero['parseado'], $segundo['parseado']);
        } finally {
            foreach ($temporales as $temporal) {
                if (is_file($temporal)) {
                    @unlink($temporal);
                }
            }
            $dir = dirname($imagenes[0] ?? '');
            if ($dir !== '' && str_contains($dir, 'asiento_pdf_')) {
                self::borrarDirectorio($dir);
            }
        }
    }

    /**
     * @param  list<string>  $imagenes
     * @return array{texto: string, parseado: array{filas: list<array{codigo_cuenta: string, codigo_centrocosto: string, debe: float, haber: float, detalle: string}>, con_encabezado: bool}}
     */
    private static function ocrPreparadas(array $imagenes, string $psm): array
    {
        $lineas = [];
        $textos = [];
        foreach ($imagenes as $imagen) {
            $textos[] = self::textoTesseract($imagen, $psm);
            foreach (self::palabrasHocr(self::hocr($imagen, $psm)) as $linea) {
                $lineas[] = $linea;
            }
        }

        $texto = implode("\n", $textos);

        return [
            'texto' => $texto,
            'parseado' => AsientoPegadoTextoSupport::decodificarOcr($texto, $lineas),
        ];
    }

    private static function ampliarParaOcr(string $ruta): string
    {
        $info = @getimagesize($ruta);
        if ($info === false || (int) ($info[0] ?? 0) >= 1600 || (int) ($info[0] ?? 0) < 1) {
            return $ruta;
        }

        $origen = self::abrirImagen($ruta, (int) ($info[2] ?? 0));
        if ($origen === null) {
            return $ruta;
        }

        $ancho = (int) $info[0];
        $alto = (int) $info[1];
        $nuevoAncho = min(3200, $ancho * 2);
        $nuevoAlto = (int) round($alto * ($nuevoAncho / $ancho));
        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        if ($destino === false) {
            imagedestroy($origen);

            return $ruta;
        }

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($origen);

        $tmp = RecepcionProveedorOcrTempSupport::archivo('asiento_zoom_', 'png');
        imagepng($destino, $tmp);
        imagedestroy($destino);

        return is_readable($tmp) ? $tmp : $ruta;
    }

    /**
     * La impresión de Anita trae una grilla gris clara. En escala de grises con más
     * contraste Tesseract separa los importes de esas líneas.
     */
    private static function aclararParaOcr(string $ruta): string
    {
        $info = @getimagesize($ruta);
        if ($info === false) {
            return $ruta;
        }

        $origen = self::abrirImagen($ruta, (int) ($info[2] ?? 0));
        if ($origen === null) {
            return $ruta;
        }

        imagefilter($origen, IMG_FILTER_GRAYSCALE);
        imagefilter($origen, IMG_FILTER_CONTRAST, -35);

        $tmp = RecepcionProveedorOcrTempSupport::archivo('asiento_contraste_', 'png');
        imagepng($origen, $tmp);
        imagedestroy($origen);

        return is_readable($tmp) ? $tmp : $ruta;
    }

    /** @return \GdImage|resource|null */
    private static function abrirImagen(string $ruta, int $tipo)
    {
        return match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($ruta),
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($ruta) : null,
            default => null,
        };
    }

    private static function textoTesseract(string $imagen, string $psm = '6'): string
    {
        $base = RecepcionProveedorOcrTempSupport::base('asiento_txt_');
        $process = new Process(['tesseract', $imagen, $base, '-l', 'spa', '-psm', $psm]);
        $process->setTimeout(90);
        $process->run();

        $archivo = $base.'.txt';
        if (! is_readable($archivo)) {
            return '';
        }

        $texto = (string) file_get_contents($archivo);
        @unlink($archivo);

        return $texto;
    }

    private static function hocr(string $imagen, string $psm = '6'): string
    {
        $base = RecepcionProveedorOcrTempSupport::base('asiento_hocr_');
        $process = new Process(['tesseract', $imagen, $base, '-l', 'spa', '-psm', $psm, 'hocr']);
        $process->setTimeout(90);
        $process->run();

        $archivo = $base.'.hocr';
        if (! is_readable($archivo)) {
            $archivo = $base.'.html';
        }
        if (! is_readable($archivo)) {
            return '';
        }

        $html = (string) file_get_contents($archivo);
        @unlink($archivo);

        return $html;
    }

    /**
     * @return list<list<array{t: string, x1: int, x2: int}>>
     */
    private static function palabrasHocr(string $hocr): array
    {
        if ($hocr === '') {
            return [];
        }

        preg_match_all(
            "/<span class=['\"]ocrx_word['\"][^>]*title=['\"]bbox\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)\\s+(\\d+)[^'\"]*['\"][^>]*>(.*?)<\\/span>/s",
            $hocr,
            $coincidencias,
            PREG_SET_ORDER
        );

        $palabras = [];
        foreach ($coincidencias as $coincidencia) {
            $texto = trim(strip_tags(html_entity_decode((string) $coincidencia[5], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($texto === '') {
                continue;
            }
            $palabras[] = [
                't' => $texto,
                'x1' => (int) $coincidencia[1],
                'x2' => (int) $coincidencia[3],
                'y' => (int) $coincidencia[2],
            ];
        }

        usort($palabras, static function (array $a, array $b): int {
            return $a['y'] <=> $b['y'] ?: $a['x1'] <=> $b['x1'];
        });

        $lineas = [];
        foreach ($palabras as $palabra) {
            $indice = null;
            foreach ($lineas as $i => $linea) {
                if (abs($palabra['y'] - $linea['y']) <= 12) {
                    $indice = $i;
                    break;
                }
            }
            if ($indice === null) {
                $lineas[] = ['y' => $palabra['y'], 'palabras' => []];
                $indice = array_key_last($lineas);
            }
            $lineas[$indice]['palabras'][] = [
                't' => $palabra['t'],
                'x1' => $palabra['x1'],
                'x2' => $palabra['x2'],
            ];
        }

        $salida = [];
        foreach ($lineas as $linea) {
            $palabrasLinea = $linea['palabras'];
            usort($palabrasLinea, static fn (array $a, array $b): int => $a['x1'] <=> $b['x1']);
            $salida[] = $palabrasLinea;
        }

        return $salida;
    }

    private static function borrarDirectorio(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $ruta = $dir.'/'.$item;
            if (is_dir($ruta)) {
                self::borrarDirectorio($ruta);
            } else {
                @unlink($ruta);
            }
        }

        @rmdir($dir);
    }

    private static function assertBinario(string $bin): void
    {
        $process = new Process(['which', $bin]);
        $process->run();
        if ($process->isSuccessful()) {
            return;
        }

        throw new \RuntimeException('Falta el programa '.$bin.' para leer el PDF o la imagen.');
    }
}
