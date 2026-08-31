<?php

namespace App\Support\Caja\Flash;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Rellena la plantilla oficial .xlsx sin reescribir el paquete.
 *
 * PhpSpreadsheet al guardar pierde gráficos, drawings y deja rels rotas
 * (Excel: «Hemos encontrado un problema con contenido»).
 */
final class FlashReporteAggXlsxPatchSupport
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Ancho Excel de 120 px (Calibri 11). */
    public const TABLA_ANCHO_COL_EXCEL = '17.138671875';

    /**
     * @param  array<string, array<string, float|int|string|null>>  $porHoja
     * @return array{tabla_resumen: list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>}
     */
    public function rellenar(string $plantilla, string $destino, array $porHoja, ?string $imagenDestino = null): array
    {
        if (! is_file($plantilla)) {
            throw new RuntimeException('No está la plantilla Flash Report AGG: '.$plantilla);
        }
        if (! @copy($plantilla, $destino)) {
            throw new RuntimeException('No se pudo copiar la plantilla Flash Report AGG a '.$destino);
        }

        $zip = new ZipArchive;
        if ($zip->open($destino) !== true) {
            throw new RuntimeException('No se pudo abrir el Excel generado: '.$destino);
        }

        $rutaTabla = 'xl/worksheets/sheet5.xml';
        try {
            $shared = $this->cargarSharedStrings($zip);
            $hojas = $this->mapaHojas($zip);
            $rutaTabla = $hojas['Tabla'] ?? $rutaTabla;
            $reemplazos = [];

            foreach ($porHoja as $nombreHoja => $celdas) {
                if ($celdas === []) {
                    continue;
                }
                $ruta = $hojas[$nombreHoja] ?? null;
                if ($ruta === null) {
                    continue;
                }
                $xml = $zip->getFromName($ruta);
                if ($xml === false) {
                    continue;
                }
                $reemplazos[$ruta] = $this->aplicarCeldas($xml, $celdas, $shared);
            }

            $xmlTabla = $reemplazos[$rutaTabla] ?? $zip->getFromName($rutaTabla);
            if (is_string($xmlTabla)) {
                $reemplazos[$rutaTabla] = $this->sanearXmlHojaTabla($xmlTabla);
            }

            $reemplazos['xl/sharedStrings.xml'] = $this->serializarSharedStrings($shared);
            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook !== false) {
                $reemplazos['xl/workbook.xml'] = $this->forzarRecalculo($workbook);
            }

            $this->quitarCalcChain($zip);

            foreach ($reemplazos as $ruta => $xml) {
                $zip->deleteName($ruta);
                $zip->addFromString($ruta, $xml);
            }
        } finally {
            $zip->close();
        }

        $tablaResumen = [];
        try {
            $tablaResumen = $this->estamparValoresCalculadosTabla($destino, $rutaTabla, $imagenDestino);
        } catch (Throwable) {
            // Si el motor no puede calcular, Excel recalcula al abrir (fullCalcOnLoad).
        }

        return ['tabla_resumen' => $tablaResumen];
    }

    /**
     * Deja Tabla en A:G (120 px), HLOOKUP("Electronic") y sin columnas viejas.
     */
    public function sanearHojaTablaEnArchivo(string $xlsx): void
    {
        $zip = new ZipArchive;
        if ($zip->open($xlsx) !== true) {
            throw new RuntimeException('No se pudo abrir el Excel: '.$xlsx);
        }
        try {
            $hojas = $this->mapaHojas($zip);
            $ruta = $hojas['Tabla'] ?? null;
            if ($ruta === null) {
                return;
            }
            $xml = $zip->getFromName($ruta);
            if ($xml === false) {
                return;
            }
            $zip->deleteName($ruta);
            $zip->addFromString($ruta, $this->sanearXmlHojaTabla($xml));
        } finally {
            $zip->close();
        }
    }

    public function sanearXmlHojaTabla(string $xml): string
    {
        $xml = str_replace('HLOOKUP(J3,', 'HLOOKUP("Electronic",', $xml);
        $xml = preg_replace('/<mergeCells\b[^>]*>.*?<\/mergeCells>/s', '', $xml) ?? $xml;
        $xml = preg_replace('/<c r="(?:[H-Z]|[A-Z]{2,})\d+"[^>]*\/>/', '', $xml) ?? $xml;
        $xml = preg_replace('/<c r="(?:[H-Z]|[A-Z]{2,})\d+"[^>]*>.*?<\/c>/s', '', $xml) ?? $xml;
        $xml = preg_replace('/<row r="(?:1[4-9]|[2-9]\d)"[^>]*>.*?<\/row>/s', '', $xml) ?? $xml;
        $cols = '<cols><col min="1" max="7" width="'.self::TABLA_ANCHO_COL_EXCEL.'" style="145" customWidth="1"/></cols>';
        $xml = preg_replace('/<cols>.*?<\/cols>/s', $cols, $xml) ?? $xml;
        $xml = preg_replace('/<dimension ref="[^"]*"/', '<dimension ref="A1:G13"', $xml) ?? $xml;
        $xml = preg_replace('/spans="[^"]*"/', 'spans="1:7"', $xml) ?? $xml;
        $xml = preg_replace(
            '/<selection\b[^>]*\/>/',
            '<selection activeCell="A2" sqref="A2"/>',
            $xml
        ) ?? $xml;

        return $xml;
    }

    /**
     * La plantilla deja &lt;v&gt;0&lt;/v&gt; en Tabla. Excel muestra ese caché
     * hasta recalcular. Escribimos el resultado de las fórmulas oficiales
     * (INDEX / G39 / Electronic) sin regrabar el xlsx con PhpSpreadsheet.
     *
     * @return list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>
     */
    private function estamparValoresCalculadosTabla(string $destino, string $rutaTabla, ?string $imagenDestino): array
    {
        $ss = IOFactory::load($destino);
        $valores = [];
        $resumen = [];
        try {
            $tabla = $ss->getSheetByName('Tabla');
            if ($tabla === null) {
                return [];
            }
            for ($fila = 1; $fila <= 13; $fila++) {
                $linea = [];
                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                    $ref = $col.$fila;
                    $cell = $tabla->getCell($ref);
                    $raw = $cell->getValue();
                    $calc = $raw;
                    if (is_string($raw) && str_starts_with($raw, '=')) {
                        try {
                            $calc = $cell->getCalculatedValue();
                        } catch (Throwable) {
                            $calc = null;
                        }
                    }
                    if (is_numeric($calc) && is_string($raw) && str_starts_with($raw, '=')) {
                        $valores[$ref] = (float) $calc;
                    }
                    $formato = (string) $tabla->getStyle($ref)->getNumberFormat()->getFormatCode();
                    $linea[] = $this->celdaResumenTabla($calc, $formato, $fila, $col);
                }
                $resumen[] = $linea;
            }
        } finally {
            $ss->disconnectWorksheets();
        }

        if ($valores !== []) {
            $this->escribirValoresEstampados($destino, $rutaTabla, $valores);
        }

        if ($imagenDestino !== null && $imagenDestino !== '') {
            try {
                (new FlashReporteAggTablaImagenSupport)->generar($resumen, $imagenDestino);
            } catch (Throwable) {
                // El mail sigue con el Excel; la imagen es un extra.
            }
        }

        return $resumen;
    }

    /**
     * @param  array<string, float>  $valores
     */
    private function escribirValoresEstampados(string $destino, string $rutaTabla, array $valores): void
    {
        $zip = new ZipArchive;
        if ($zip->open($destino) !== true) {
            return;
        }
        try {
            $xml = $zip->getFromName($rutaTabla);
            if ($xml === false) {
                return;
            }
            foreach ($valores as $ref => $numero) {
                $xml = preg_replace_callback(
                    '/<c r="'.preg_quote($ref, '/').'"([^>]*)>(<f[^>]*>.*?<\/f>)(?:<v>.*?<\/v>)?/',
                    function (array $m) use ($ref, $numero): string {
                        $attrs = preg_replace('/\s+t="[^"]*"/', '', $m[1]) ?? $m[1];

                        return '<c r="'.$ref.'"'.$attrs.'>'.$m[2].'<v>'.$this->numeroXml($numero).'</v>';
                    },
                    $xml,
                    1
                ) ?? $xml;
            }
            $zip->deleteName($rutaTabla);
            $zip->addFromString($rutaTabla, $xml);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{texto: string, negrita: bool, rojo: bool, encabezado: bool}
     */
    private function celdaResumenTabla(mixed $valor, string $formato, int $fila, string $col): array
    {
        $encabezado = in_array($fila, [2, 8], true);
        $negrita = $encabezado || $col === 'A';
        if ($valor instanceof RichText) {
            $valor = $valor->getPlainText();
        }
        if (is_string($valor)) {
            $texto = trim($valor);
            if (str_starts_with($texto, '=')) {
                $texto = '';
            }

            return [
                'texto' => $texto,
                'negrita' => $negrita,
                'rojo' => false,
                'encabezado' => $encabezado,
            ];
        }
        if (! is_numeric($valor)) {
            return [
                'texto' => '',
                'negrita' => $negrita,
                'rojo' => false,
                'encabezado' => $encabezado,
            ];
        }

        $n = (float) $valor;
        $esPct = str_contains($formato, '%');
        if ($esPct) {
            $texto = number_format($n * 100, 1, ',', '.').'%';
        } elseif (str_contains($formato, '$') || str_contains($formato, '#')) {
            $texto = '$ '.number_format($n, 0, ',', '.');
        } else {
            $texto = number_format($n, 0, ',', '.');
        }

        return [
            'texto' => $texto,
            'negrita' => $negrita,
            'rojo' => $esPct && $n < 0,
            'encabezado' => $encabezado,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mapaHojas(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('La plantilla Flash Report AGG no tiene workbook.xml');
        }

        $ridATarget = [];
        $relsXml = new DOMDocument;
        $relsXml->loadXML($rels);
        foreach ($relsXml->getElementsByTagName('Relationship') as $rel) {
            if (! $rel instanceof DOMElement) {
                continue;
            }
            $ridATarget[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }

        $wb = new DOMDocument;
        $wb->loadXML($workbook);
        $xpath = new DOMXPath($wb);
        $xpath->registerNamespace('m', self::NS);
        $out = [];
        foreach ($xpath->query('//m:sheets/m:sheet') ?: [] as $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }
            $rid = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );
            $target = $ridATarget[$rid] ?? '';
            if ($target === '') {
                continue;
            }
            $out[$sheet->getAttribute('name')] = 'xl/'.ltrim($target, '/');
        }

        return $out;
    }

    /**
     * @return array{index: array<string, int>, textos: list<string>}
     */
    private function cargarSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $textos = [];
        if ($xml !== false) {
            $dom = new DOMDocument;
            $dom->loadXML($xml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('m', self::NS);
            foreach ($xpath->query('//m:si') ?: [] as $si) {
                $textos[] = $si->textContent;
            }
        }

        return [
            'index' => array_flip($textos),
            'textos' => $textos,
        ];
    }

    /**
     * @param  array{index: array<string, int>, textos: list<string>}  $shared
     */
    private function serializarSharedStrings(array $shared): string
    {
        $sst = new DOMDocument('1.0', 'UTF-8');
        $sst->xmlStandalone = true;
        $root = $sst->createElementNS(self::NS, 'sst');
        $root->setAttribute('count', (string) count($shared['textos']));
        $root->setAttribute('uniqueCount', (string) count($shared['textos']));
        $sst->appendChild($root);

        foreach ($shared['textos'] as $texto) {
            $si = $sst->createElementNS(self::NS, 'si');
            $t = $sst->createElementNS(self::NS, 't');
            $t->appendChild($sst->createTextNode($texto));
            if ($texto !== trim($texto) || str_starts_with($texto, ' ') || str_ends_with($texto, ' ')) {
                $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }
            $si->appendChild($t);
            $root->appendChild($si);
        }

        $out = $sst->saveXML();
        if ($out === false) {
            throw new RuntimeException('No se pudo serializar sharedStrings.xml');
        }

        return $out;
    }

    /**
     * @param  array<string, float|int|string|null>  $celdas
     * @param  array{index: array<string, int>, textos: list<string>}  $shared
     */
    private function aplicarCeldas(string $xml, array $celdas, array &$shared): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);
        $sheetData = $xpath->query('//m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('La hoja no tiene sheetData');
        }

        $porFila = [];
        foreach ($celdas as $ref => $valor) {
            [$col, $fila] = $this->partirRef((string) $ref);
            $porFila[$fila][$col] = $valor;
        }
        ksort($porFila, SORT_NUMERIC);

        foreach ($porFila as $fila => $cols) {
            uksort($cols, fn (string $a, string $b) => $this->indiceColumna($a) <=> $this->indiceColumna($b));
            $row = $this->fila($xpath, $sheetData, $fila);
            foreach ($cols as $col => $valor) {
                $this->setearCelda($xpath, $dom, $row, $col.$fila, $valor, $shared);
            }
        }

        $out = $dom->saveXML();
        if ($out === false) {
            throw new RuntimeException('No se pudo serializar la hoja');
        }

        return $out;
    }

    private function fila(DOMXPath $xpath, DOMElement $sheetData, int $fila): DOMElement
    {
        $encontrada = $xpath->query('./m:row[@r="'.$fila.'"]', $sheetData)->item(0);
        if ($encontrada instanceof DOMElement) {
            return $encontrada;
        }

        $dom = $sheetData->ownerDocument;
        if ($dom === null) {
            throw new RuntimeException('sheetData sin documento');
        }
        $row = $dom->createElementNS(self::NS, 'row');
        $row->setAttribute('r', (string) $fila);

        $insertBefore = null;
        foreach ($xpath->query('./m:row', $sheetData) ?: [] as $existente) {
            if (! $existente instanceof DOMElement) {
                continue;
            }
            if ((int) $existente->getAttribute('r') > $fila) {
                $insertBefore = $existente;
                break;
            }
        }
        if ($insertBefore !== null) {
            $sheetData->insertBefore($row, $insertBefore);
        } else {
            $sheetData->appendChild($row);
        }

        return $row;
    }

    /**
     * @param  array{index: array<string, int>, textos: list<string>}  $shared
     */
    private function setearCelda(
        DOMXPath $xpath,
        DOMDocument $dom,
        DOMElement $row,
        string $ref,
        float|int|string|null $valor,
        array &$shared,
    ): void {
        $celda = $xpath->query('./m:c[@r="'.$ref.'"]', $row)->item(0);
        if (! $celda instanceof DOMElement) {
            $celda = $dom->createElementNS(self::NS, 'c');
            $celda->setAttribute('r', $ref);
            $this->insertarCeldaOrdenada($xpath, $row, $celda);
        }

        foreach (['f', 'v', 'is'] as $hijo) {
            foreach ($xpath->query('./m:'.$hijo, $celda) ?: [] as $nodo) {
                $celda->removeChild($nodo);
            }
        }

        if ($valor === null || $valor === '') {
            $celda->removeAttribute('t');

            return;
        }

        if (is_string($valor)) {
            $celda->setAttribute('t', 's');
            $v = $dom->createElementNS(self::NS, 'v');
            $v->appendChild($dom->createTextNode((string) $this->internar($shared, $valor)));
            $celda->appendChild($v);

            return;
        }

        $celda->removeAttribute('t');
        $v = $dom->createElementNS(self::NS, 'v');
        $v->appendChild($dom->createTextNode($this->numeroXml($valor)));
        $celda->appendChild($v);
    }

    private function insertarCeldaOrdenada(DOMXPath $xpath, DOMElement $row, DOMElement $celda): void
    {
        $indice = $this->indiceColumna($this->partirRef($celda->getAttribute('r'))[0]);
        $insertBefore = null;
        foreach ($xpath->query('./m:c', $row) ?: [] as $existente) {
            if (! $existente instanceof DOMElement) {
                continue;
            }
            $col = $this->partirRef($existente->getAttribute('r'))[0];
            if ($this->indiceColumna($col) > $indice) {
                $insertBefore = $existente;
                break;
            }
        }
        if ($insertBefore !== null) {
            $row->insertBefore($celda, $insertBefore);
        } else {
            $row->appendChild($celda);
        }
    }

    /**
     * @param  array{index: array<string, int>, textos: list<string>}  $shared
     */
    private function internar(array &$shared, string $texto): int
    {
        if (isset($shared['index'][$texto])) {
            return (int) $shared['index'][$texto];
        }
        $idx = count($shared['textos']);
        $shared['textos'][] = $texto;
        $shared['index'][$texto] = $idx;

        return $idx;
    }

    private function numeroXml(int|float $valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_finite($valor) && floor($valor) == $valor && abs($valor) < 1e15) {
            return sprintf('%.0f', $valor);
        }

        return rtrim(rtrim(sprintf('%.12F', $valor), '0'), '.');
    }

    private function forzarRecalculo(string $workbookXml): string
    {
        if (preg_match('/<calcPr\b[^>]*\/?>/', $workbookXml, $m)) {
            $tag = $m[0];
            $tag = preg_replace('/\s+fullCalcOnLoad="[^"]*"/', '', $tag) ?? $tag;
            $tag = preg_replace('/\s+calcCompleted="[^"]*"/', '', $tag) ?? $tag;
            $tag = rtrim($tag, '/> ');
            $tag .= ' calcCompleted="0" fullCalcOnLoad="1"/>';

            return str_replace($m[0], $tag, $workbookXml);
        }

        return str_replace('</workbook>', '<calcPr calcCompleted="0" fullCalcOnLoad="1"/></workbook>', $workbookXml);
    }

    private function quitarCalcChain(ZipArchive $zip): void
    {
        if ($zip->locateName('xl/calcChain.xml') !== false) {
            $zip->deleteName('xl/calcChain.xml');
        }

        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($rels !== false && str_contains($rels, 'calcChain.xml')) {
            $dom = new DOMDocument;
            $dom->loadXML($rels);
            foreach ($dom->getElementsByTagName('Relationship') as $rel) {
                if (! $rel instanceof DOMElement) {
                    continue;
                }
                if (str_contains($rel->getAttribute('Target'), 'calcChain.xml')) {
                    $rel->parentNode?->removeChild($rel);
                }
            }
            $zip->deleteName('xl/_rels/workbook.xml.rels');
            $zip->addFromString('xl/_rels/workbook.xml.rels', (string) $dom->saveXML());
        }

        $types = $zip->getFromName('[Content_Types].xml');
        if ($types !== false && str_contains($types, 'calcChain.xml')) {
            $types = preg_replace(
                '/<Override[^>]+PartName="\/xl\/calcChain\.xml"[^>]*\/>/',
                '',
                $types
            ) ?? $types;
            $zip->deleteName('[Content_Types].xml');
            $zip->addFromString('[Content_Types].xml', $types);
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function partirRef(string $ref): array
    {
        if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
            throw new RuntimeException('Referencia de celda inválida: '.$ref);
        }

        return [$m[1], (int) $m[2]];
    }

    private function indiceColumna(string $col): int
    {
        $n = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($col[$i]) - 64);
        }

        return $n;
    }
}
