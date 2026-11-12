<?php

namespace App\Support\Sueldos;

use RuntimeException;
use ZipArchive;

/**
 * Merge de plantillas Word compatible con tags Anita `$tag$` y PhpWord `${tag}`.
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
