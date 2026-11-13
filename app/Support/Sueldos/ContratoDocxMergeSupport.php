<?php

namespace App\Support\Sueldos;

use App\Support\Pdf\DompdfPaperSupport;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use ZipArchive;

/**
 * Merge de plantillas Word compatible con tags Anita `$tag$` y PhpWord `${tag}`.
 * La salida operativa es HTML (preview) / PDF (emisión); el DOCX es solo intermediario.
 */
final class ContratoDocxMergeSupport
{
    /**
     * @param  array<string, string|int|float|null>  $valores
     */
    public function generar(string $plantillaPath, array $valores, string $destinoPath): void
    {
        if (! is_file($plantillaPath)) {
            throw new RuntimeException('No se encontró el archivo de plantilla Word.');
        }

        $dir = dirname($destinoPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de emisión.');
        }

        if (! @copy($plantillaPath, $destinoPath)) {
            throw new RuntimeException('No se pudo copiar la plantilla para emitir.');
        }

        $zip = new ZipArchive;
        if ($zip->open($destinoPath) !== true) {
            throw new RuntimeException('No se pudo abrir el documento Word generado.');
        }

        $mapa = $this->normalizarValores($valores);
        $partes = ['word/document.xml'];
        for ($i = 1; $i <= 20; $i++) {
            $partes[] = "word/header{$i}.xml";
            $partes[] = "word/footer{$i}.xml";
        }

        foreach ($partes as $parte) {
            $xml = $zip->getFromName($parte);
            if ($xml === false) {
                continue;
            }
            $zip->addFromString($parte, $this->reemplazarEnXml($xml, $mapa));
        }

        $zip->close();
    }

    /**
     * Merge a un DOCX temporal, lo convierte a HTML y borra el temporal.
     *
     * @param  array<string, string|int|float|null>  $valores
     */
    public function generarHtml(string $plantillaPath, array $valores): string
    {
        $tmp = $this->docxTemporal($plantillaPath, $valores);
        try {
            return $this->docxAHtml($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Merge a DOCX temporal y genera PDF definitivo en $destinoPdf.
     *
     * @param  array<string, string|int|float|null>  $valores
     */
    public function generarPdf(string $plantillaPath, array $valores, string $destinoPdf): void
    {
        $dir = dirname($destinoPdf);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de emisión.');
        }

        $tmp = $this->docxTemporal($plantillaPath, $valores);
        try {
            $this->docxAPdf($tmp, $destinoPdf);
        } finally {
            @unlink($tmp);
        }
    }

    public function docxAHtml(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException('No se encontró el documento para previsualizar.');
        }

        try {
            $phpWord = IOFactory::load($docxPath);
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo convertir la plantilla a vista previa: '.$e->getMessage(), 0, $e);
        }

        if (trim(strip_tags($html)) === '') {
            throw new RuntimeException('La vista previa del contrato quedó vacía.');
        }

        // Dompdf / iframe: asegurar charset
        if (! preg_match('/<meta[^>]+charset=/i', $html)) {
            $html = preg_replace(
                '/<head([^>]*)>/i',
                '<head$1><meta http-equiv="Content-Type" content="text/html; charset=utf-8">',
                $html,
                1
            ) ?? $html;
        }

        return $html;
    }

    public function docxAPdf(string $docxPath, string $destinoPdf): void
    {
        $html = $this->docxAHtml($docxPath);
        try {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_COMPROBANTE);
            $pdf->save($destinoPdf);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo generar el PDF del contrato: '.$e->getMessage(), 0, $e);
        }

        if (! is_file($destinoPdf) || filesize($destinoPdf) < 50) {
            throw new RuntimeException('El PDF del contrato no se generó correctamente.');
        }
    }

    /**
     * @param  array<string, string|int|float|null>  $valores
     */
    private function docxTemporal(string $plantillaPath, array $valores): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ctr_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para el contrato.');
        }
        $docx = $tmp.'.docx';
        @unlink($tmp);
        $this->generar($plantillaPath, $valores, $docx);

        return $docx;
    }

    /**
     * @param  array<string, string|int|float|null>  $valores
     * @return array<string, string>
     */
    private function normalizarValores(array $valores): array
    {
        $out = [];
        foreach ($valores as $tag => $valor) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $out[$tag] = $this->escaparXml((string) ($valor ?? ''));
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $mapa
     */
    private function reemplazarEnXml(string $xml, array $mapa): string
    {
        // Anita: $tag$  | PhpWord: ${tag}
        foreach ($mapa as $tag => $valor) {
            $xml = str_replace('$'.$tag.'$', $valor, $xml);
            $xml = str_replace('${'.$tag.'}', $valor, $xml);
        }

        // Tags sin valor: dejar vacío para no imprimir el placeholder
        $xml = preg_replace('/\$\{([A-Za-z0-9_]+)\}/', '', $xml) ?? $xml;
        $xml = preg_replace('/\$([A-Za-z0-9_]+)\$/', '', $xml) ?? $xml;

        return $xml;
    }

    private function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
