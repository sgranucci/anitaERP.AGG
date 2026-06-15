<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Extrae texto de PDF (pdftotext) o imagen/PDF escaneado (pdftoppm + tesseract).
 */
class RecepcionProveedorOcrTextoExtractor
{
    public function extraer(string $rutaAbsoluta, ?string $mime = null): string
    {
        if (! is_readable($rutaAbsoluta)) {
            throw new \RuntimeException('Archivo OCR no legible.');
        }

        $mime ??= (string) @mime_content_type($rutaAbsoluta);
        $esPdf = $mime === 'application/pdf' || Str::endsWith(strtolower($rutaAbsoluta), '.pdf');

        if ($esPdf) {
            $texto = $this->extraerPdfConPdftotext($rutaAbsoluta);
            $minChars = (int) config('recepcion_proveedor.ocr.pdf_min_chars_texto', 40);
            if (mb_strlen(trim($texto)) >= $minChars) {
                return $texto;
            }

            return $this->extraerConTesseract($this->pdfAPng($rutaAbsoluta));
        }

        $rutaOcr = RecepcionProveedorOcrImagenSupport::prepararParaOcr($rutaAbsoluta, $mime);

        return $this->extraerConTesseract($rutaOcr);
    }

    private function extraerPdfConPdftotext(string $rutaPdf): string
    {
        $bin = (string) config('recepcion_proveedor.ocr.pdftotext_bin', 'pdftotext');
        $this->assertBinario($bin, 'pdftotext');

        $tmp = RecepcionProveedorOcrTempSupport::archivo('ocr_', 'txt');

        $process = new Process([
            $bin,
            '-layout',
            '-enc',
            'UTF-8',
            $rutaPdf,
            $tmp,
        ]);
        $process->setTimeout((int) config('recepcion_proveedor.ocr.timeout_segundos', 120));
        $process->run();

        if (! $process->isSuccessful() || ! is_readable($tmp)) {
            @unlink($tmp);

            return '';
        }

        $texto = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $texto;
    }

    /** @return list<string> rutas PNG generadas */
    private function pdfAPng(string $rutaPdf): array
    {
        $bin = (string) config('recepcion_proveedor.ocr.pdftoppm_bin', 'pdftoppm');
        $this->assertBinario($bin, 'pdftoppm');

        $dir = RecepcionProveedorOcrTempSupport::directorio().'/ocr_pdf_'.uniqid('', true);
        File::ensureDirectoryExists($dir);
        $prefijo = $dir.'/page';

        $process = new Process([
            $bin,
            '-png',
            '-r',
            (string) (int) config('recepcion_proveedor.ocr.dpi_pdf', 300),
            '-f',
            '1',
            '-l',
            (string) max(1, (int) config('recepcion_proveedor.ocr.pdf_max_paginas', 3)),
            $rutaPdf,
            $prefijo,
        ]);
        $process->setTimeout((int) config('recepcion_proveedor.ocr.timeout_segundos', 120));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('No se pudo rasterizar el PDF para OCR: '.$process->getErrorOutput());
        }

        $paginas = glob($prefijo.'-*.png') ?: [];
        sort($paginas, SORT_NATURAL);

        if ($paginas === []) {
            throw new \RuntimeException('El PDF no generó imágenes para OCR.');
        }

        return $paginas;
    }

    /**
     * @param  string|list<string>  $entrada
     */
    private function extraerConTesseract(string|array $entrada): string
    {
        $bin = (string) config('recepcion_proveedor.ocr.tesseract_bin', 'tesseract');
        $this->assertBinario($bin, 'tesseract');

        $lang = (string) config('recepcion_proveedor.ocr.tesseract_lang', 'spa');
        $archivos = is_array($entrada) ? $entrada : [$entrada];
        $textos = [];

        foreach ($archivos as $archivo) {
            $textosPagina = [];
            foreach ($this->modosPsmTesseract() as $psm) {
                $fragmento = $this->ejecutarTesseractPagina($bin, $lang, $archivo, $psm);
                if (trim($fragmento) !== '') {
                    $textosPagina[] = $fragmento;
                }
            }

            if ($textosPagina === []) {
                throw new \RuntimeException('Error Tesseract OCR: no se extrajo texto con ningún modo PSM.');
            }

            $textos[] = $this->fusionarTextosOcr($textosPagina);
        }

        if (is_array($entrada)) {
            $dir = dirname($entrada[0] ?? '');
            if ($dir !== '' && str_contains($dir, 'ocr_pdf_')) {
                File::deleteDirectory($dir);
            }
        }

        return trim(implode("\n\n", $textos));
    }

    /** @return list<int> */
    private function modosPsmTesseract(): array
    {
        $principal = (int) config('recepcion_proveedor.ocr.tesseract_psm', 4);
        $extras = (string) config('recepcion_proveedor.ocr.tesseract_psm_extra', '11');
        $modos = $principal > 0 ? [$principal] : [];

        foreach (array_filter(array_map('trim', explode(',', $extras))) as $psmRaw) {
            $psm = (int) $psmRaw;
            if ($psm > 0) {
                $modos[] = $psm;
            }
        }

        if ($modos === []) {
            $modos = [4, 11];
        }

        return array_values(array_unique($modos));
    }

    private function ejecutarTesseractPagina(string $bin, string $lang, string $archivo, int $psm): string
    {
        $tmp = RecepcionProveedorOcrTempSupport::base('ocr_');
        $args = [$bin, $archivo, $tmp, '-l', $lang, '-psm', (string) $psm];

        $process = new Process($args);
        $process->setTimeout((int) config('recepcion_proveedor.ocr.timeout_segundos', 120));
        $process->run();

        $txt = $tmp.'.txt';
        if (! is_readable($txt)) {
            return '';
        }

        $texto = (string) file_get_contents($txt);
        @unlink($txt);

        return $texto;
    }

    /**
     * Une lecturas PSM distintas priorizando líneas únicas (PSM 11 suele captar etiquetas sueltas como Nro O.C.).
     *
     * @param  list<string>  $fragmentos
     */
    private function fusionarTextosOcr(array $fragmentos): string
    {
        $lineasVistas = [];
        $salida = [];

        foreach ($fragmentos as $fragmento) {
            foreach (preg_split('/\R/u', $fragmento) ?: [] as $linea) {
                $linea = trim((string) $linea);
                if ($linea === '') {
                    continue;
                }
                $clave = mb_strtoupper($linea);
                if (isset($lineasVistas[$clave])) {
                    continue;
                }
                $lineasVistas[$clave] = true;
                $salida[] = $linea;
            }
        }

        return implode("\n", $salida);
    }

    private function assertBinario(string $bin, string $nombre): void
    {
        $process = new Process(['which', $bin]);
        $process->run();
        if ($process->isSuccessful()) {
            return;
        }

        if (is_executable($bin)) {
            return;
        }

        throw new \RuntimeException(
            "Dependencia OCR '{$nombre}' no encontrada ({$bin}). "
            .'Instale el paquete del sistema o configure la ruta en .env.'
        );
    }
}
