<?php

namespace App\Support\Seguridad;

/**
 * Extrae texto buscable de adjuntos (PDF, Office, texto plano).
 * Sin OCR: imágenes y binarios solo indexan el nombre de archivo.
 */
class BuscadorFtsExtractorSupport
{
    public const MAX_CHARS = 200000;

    /**
     * @return array{texto: string, mime: string|null, bytes: int}
     */
    public function extraer(string $rutaAbsoluta, string $nombreArchivo): array
    {
        if (! is_file($rutaAbsoluta) || ! is_readable($rutaAbsoluta)) {
            return ['texto' => '', 'mime' => null, 'bytes' => 0];
        }

        $mime = mime_content_type($rutaAbsoluta) ?: null;
        $ext = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $texto = match (true) {
            in_array($ext, ['txt', 'csv', 'log', 'md', 'json', 'xml', 'html', 'htm'], true) => $this->leerPlano($rutaAbsoluta),
            $ext === 'pdf' => $this->extraerPdf($rutaAbsoluta),
            in_array($ext, ['docx', 'odt'], true) => $this->extraerZipXml($rutaAbsoluta, ['word/document.xml', 'content.xml']),
            in_array($ext, ['xlsx', 'ods'], true) => $this->extraerZipXml($rutaAbsoluta, [
                'xl/sharedStrings.xml',
                'xl/worksheets/sheet1.xml',
                'content.xml',
            ]),
            in_array($ext, ['pptx'], true) => $this->extraerPptx($rutaAbsoluta),
            default => '',
        };

        $texto = $this->limpiar($texto);
        if (mb_strlen($texto) > self::MAX_CHARS) {
            $texto = mb_substr($texto, 0, self::MAX_CHARS);
        }

        return [
            'texto' => $texto,
            'mime' => $mime,
            'bytes' => strlen($texto),
        ];
    }

    private function leerPlano(string $ruta): string
    {
        $raw = @file_get_contents($ruta, false, null, 0, self::MAX_CHARS * 2);
        if ($raw === false) {
            return '';
        }

        return mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    private function extraerPdf(string $ruta): string
    {
        $bin = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin === '') {
            return '';
        }

        $cmd = escapeshellcmd($bin).' -q -enc UTF-8 '.escapeshellarg($ruta).' -';
        $out = (string) shell_exec($cmd);

        return $out;
    }

    /**
     * @param  list<string>  $entradas
     */
    private function extraerZipXml(string $ruta, array $entradas): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive;
        if ($zip->open($ruta) !== true) {
            return '';
        }

        $partes = [];
        foreach ($entradas as $entrada) {
            $xml = $zip->getFromName($entrada);
            if ($xml === false || $xml === '') {
                continue;
            }
            $partes[] = strip_tags(str_replace(['</', '>'], [" </", "> "], $xml));
        }
        $zip->close();

        return implode("\n", $partes);
    }

    private function extraerPptx(string $ruta): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive;
        if ($zip->open($ruta) !== true) {
            return '';
        }

        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (! preg_match('#^ppt/slides/slide\d+\.xml$#', $nombre)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if ($xml === false || $xml === '') {
                continue;
            }
            $partes[] = strip_tags(str_replace(['</', '>'], [" </", "> "], $xml));
            if (count($partes) >= 40) {
                break;
            }
        }
        $zip->close();

        return implode("\n", $partes);
    }

    private function limpiar(string $texto): string
    {
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/[^\P{C}\n\t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}
