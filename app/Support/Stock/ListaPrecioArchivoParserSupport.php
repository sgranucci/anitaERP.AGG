<?php

namespace App\Support\Stock;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Formato estándar Interforming de lista de precios en PDF/DOCX
 * (Bagnara, Agro, listas de cliente, etc.).
 *
 * Contrato:
 * - Columnas: Artículo | Detalle | Precio Lista | Precio Min ML (opcional, se ignora)
 * - Un SKU por fila; rubros como títulos de sección
 * - Pie opcional: "Vigencia dd/mm/aaaa"
 * - Preferir PDF si el Word junta varios SKU en una celda
 */
final class ListaPrecioArchivoParserSupport
{
    private const EXTENSIONES = ['pdf', 'docx'];

    /**
     * @return array{
     *   items: list<array{sku: string, detalle: string, precio: float, seccion: string, fila: int}>,
     *   titulo: string|null,
     *   vigencia: string|null,
     *   vigencia_ymd: string|null,
     *   fuente: string,
     *   advertencias: list<string>
     * }
     */
    public static function parsearUploaded(UploadedFile $archivo): array
    {
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if ($ext === '') {
            $mime = (string) $archivo->getMimeType();
            $ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'word') ? 'docx' : '');
        }

        return self::parsearRuta((string) $archivo->getRealPath(), $ext, (string) $archivo->getClientOriginalName());
    }

    /**
     * @return array{
     *   items: list<array{sku: string, detalle: string, precio: float, seccion: string, fila: int}>,
     *   titulo: string|null,
     *   vigencia: string|null,
     *   vigencia_ymd: string|null,
     *   fuente: string,
     *   advertencias: list<string>
     * }
     */
    public static function parsearRuta(string $ruta, string $extension, string $nombreOriginal = ''): array
    {
        $extension = strtolower(ltrim($extension, '.'));
        if (! in_array($extension, self::EXTENSIONES, true)) {
            throw new RuntimeException('Formato no soportado. Use PDF o DOCX.');
        }
        if ($ruta === '' || ! is_readable($ruta)) {
            throw new RuntimeException('No se pudo leer el archivo de la lista.');
        }

        $resultado = $extension === 'pdf'
            ? self::parsearPdf($ruta)
            : self::parsearDocx($ruta);

        if ($resultado['items'] === []) {
            throw new RuntimeException('No se encontraron artículos con Precio Lista en el archivo.');
        }

        if ($nombreOriginal !== '') {
            $resultado['nombre_archivo'] = $nombreOriginal;
        }

        return $resultado;
    }

    /**
     * @return list<string>
     */
    public static function extensionesPermitidas(): array
    {
        return self::EXTENSIONES;
    }

    public static function normalizarImporte(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        // int/float reales: OK. Strings con puntos NO usar is_numeric:
        // en PHP is_numeric('610.200') === true y (float) da 610.2 (miles AR rotos).
        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }

        $texto = trim((string) $raw);
        $texto = preg_replace('/^\$+\s*/u', '', $texto) ?? $texto;
        $texto = str_replace(["\xc2\xa0", ' '], '', $texto);
        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return null;
        }

        // Entero puro sin separadores
        if (preg_match('/^-?\d+$/', $texto)) {
            return round((float) $texto, 2);
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $partes = explode(',', $texto);
            if (count($partes) === 2 && strlen($partes[1]) <= 2) {
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimoPunto !== false) {
            $partes = explode('.', $texto);
            $ultima = $partes[array_key_last($partes)];
            if (count($partes) >= 2 && strlen($ultima) === 3) {
                // Miles AR sin decimales: 610.200 → 610200
                $texto = str_replace('.', '', $texto);
            } elseif (count($partes) >= 3 && strlen($ultima) === 2) {
                $dec = array_pop($partes);
                $texto = implode('', $partes).'.'.$dec;
            }
            // Un solo punto + 1–2 decimales: dejar como decimal EN (610.2)
        }

        $texto = preg_replace('/[^\d.\-]/', '', $texto) ?? '';
        if ($texto === '' || ! is_numeric($texto)) {
            return null;
        }

        return round((float) $texto, 2);
    }

    /**
     * @return array{
     *   items: list<array{sku: string, detalle: string, precio: float, seccion: string, fila: int}>,
     *   titulo: string|null,
     *   vigencia: string|null,
     *   vigencia_ymd: string|null,
     *   fuente: string,
     *   advertencias: list<string>
     * }
     */
    private static function parsearPdf(string $ruta): array
    {
        $texto = self::textoPdfLayout($ruta);
        $advertencias = [];
        $items = [];
        $seccion = '';
        $fila = 0;

        foreach (preg_split("/\r\n|\n|\r/", $texto) ?: [] as $linea) {
            $linea = rtrim($linea);
            if (trim($linea) === '') {
                continue;
            }

            $tituloSeccion = self::detectarTituloSeccion($linea);
            if ($tituloSeccion !== null) {
                $seccion = $tituloSeccion;

                continue;
            }

            if (preg_match('/^\s*Art[ií]culo\b/iu', $linea)) {
                continue;
            }

            // SKU + detalle + Precio Lista (+ opcional Precio Min)
            if (! preg_match(
                '/^\s*([A-Z][A-Z0-9]{4,})\s+(.+?)\s+\$\s*([\d.]+(?:,\d{1,2})?)\s*(?:\$\s*([\d.]+(?:,\d{1,2})?))?\s*$/u',
                $linea,
                $m
            )) {
                continue;
            }

            $precio = self::normalizarImporte($m[3]);
            if ($precio === null || $precio <= 0) {
                continue;
            }

            $fila++;
            $items[] = [
                'sku' => strtoupper(trim($m[1])),
                'detalle' => trim(preg_replace('/\s+/', ' ', $m[2]) ?? $m[2]),
                'precio' => $precio,
                'seccion' => $seccion,
                'fila' => $fila,
            ];
        }

        $meta = self::extraerMeta($texto);
        if ($items === []) {
            $advertencias[] = 'El PDF no devolvió filas con el patrón SKU + Precio Lista.';
        }

        return [
            'items' => $items,
            'titulo' => $meta['titulo'],
            'vigencia' => $meta['vigencia'],
            'vigencia_ymd' => $meta['vigencia_ymd'],
            'fuente' => 'pdf',
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array{
     *   items: list<array{sku: string, detalle: string, precio: float, seccion: string, fila: int}>,
     *   titulo: string|null,
     *   vigencia: string|null,
     *   vigencia_ymd: string|null,
     *   fuente: string,
     *   advertencias: list<string>
     * }
     */
    private static function parsearDocx(string $ruta): array
    {
        $xml = self::leerDocumentXml($ruta);
        $ns = ['w' => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'];
        $root = simplexml_load_string($xml);
        if ($root === false) {
            throw new RuntimeException('DOCX inválido: no se pudo leer word/document.xml.');
        }
        $root->registerXPathNamespace('w', $ns['w']);

        $advertencias = [];
        $items = [];
        $fila = 0;
        $seccion = '';
        $textoPlano = [];

        foreach ($root->xpath('//w:tbl') ?: [] as $tabla) {
            $tabla->registerXPathNamespace('w', $ns['w']);
            foreach ($tabla->xpath('./w:tr') ?: [] as $tr) {
                $tr->registerXPathNamespace('w', $ns['w']);
                $celdas = $tr->xpath('./w:tc') ?: [];
                if ($celdas === []) {
                    continue;
                }

                if (count($celdas) === 1) {
                    $titulo = self::textoCelda($celdas[0]);
                    if ($titulo !== '') {
                        $seccion = $titulo;
                        $textoPlano[] = $titulo;
                    }

                    continue;
                }

                $artParrafos = self::parrafosCelda($celdas[0]);
                $detParrafos = isset($celdas[1]) ? self::parrafosCelda($celdas[1]) : [];
                $precioParrafos = isset($celdas[2]) ? self::parrafosCelda($celdas[2]) : [];

                $primerArt = $artParrafos[0] ?? '';
                if ($primerArt === '' || preg_match('/^art[ií]culo$/iu', $primerArt)) {
                    continue;
                }

                $pares = self::expandirFilaDocx($artParrafos, $detParrafos, $precioParrafos);
                if (count($pares) > 1) {
                    $advertencias[] = 'Se desdobló una fila con varios SKU en la sección «'.$seccion.'».';
                }

                foreach ($pares as $par) {
                    $precio = self::normalizarImporte($par['precio']);
                    if ($precio === null || $precio <= 0) {
                        continue;
                    }
                    $sku = strtoupper(trim($par['sku']));
                    if ($sku === '' || ! preg_match('/^[A-Z][A-Z0-9]{4,}$/', $sku)) {
                        continue;
                    }
                    $fila++;
                    $items[] = [
                        'sku' => $sku,
                        'detalle' => $par['detalle'],
                        'precio' => $precio,
                        'seccion' => $seccion,
                        'fila' => $fila,
                    ];
                    $textoPlano[] = $sku.' '.$par['detalle'].' '.$par['precio'];
                }
            }
        }

        // Pie / título fuera de tablas
        foreach ($root->xpath('//w:p') ?: [] as $p) {
            $t = self::textoNodo($p);
            if ($t !== '') {
                $textoPlano[] = $t;
            }
        }
        $meta = self::extraerMeta(implode("\n", $textoPlano));

        return [
            'items' => $items,
            'titulo' => $meta['titulo'],
            'vigencia' => $meta['vigencia'],
            'vigencia_ymd' => $meta['vigencia_ymd'],
            'fuente' => 'docx',
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    /**
     * @param  list<string>  $arts
     * @param  list<string>  $dets
     * @param  list<string>  $precios
     * @return list<array{sku: string, detalle: string, precio: string}>
     */
    private static function expandirFilaDocx(array $arts, array $dets, array $precios): array
    {
        $skus = [];
        foreach ($arts as $bloque) {
            foreach (preg_split('/\s+/', trim($bloque)) ?: [] as $sku) {
                $sku = trim($sku);
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        $n = max(count($skus), count($precios), 1);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $sku = $skus[$i] ?? ($skus[0] ?? '');
            $det = $dets[$i] ?? ($dets[0] ?? '');
            $precio = $precios[$i] ?? ($precios[0] ?? '');
            if ($sku === '' && $precio === '') {
                continue;
            }
            $out[] = [
                'sku' => $sku,
                'detalle' => trim(preg_replace('/\s+/', ' ', $det) ?? $det),
                'precio' => $precio,
            ];
        }

        return $out;
    }

    private static function textoPdfLayout(string $ruta): string
    {
        $bin = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin === '') {
            throw new RuntimeException('Falta el binario pdftotext para leer el PDF.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'lista_precio_archivo_pdf_');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para el PDF.');
        }
        $txt = $tmp.'.txt';
        @unlink($tmp);

        $process = new Process([$bin, '-layout', '-enc', 'UTF-8', $ruta, $txt]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! is_readable($txt)) {
            @unlink($txt);
            throw new RuntimeException('No se pudo extraer texto del PDF (pdftotext).');
        }

        $texto = (string) file_get_contents($txt);
        @unlink($txt);

        return $texto;
    }

    private static function leerDocumentXml(string $ruta): string
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('No se pudo abrir el DOCX.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') {
            throw new RuntimeException('El DOCX no contiene word/document.xml.');
        }

        return $xml;
    }

    /**
     * @return list<string>
     */
    private static function parrafosCelda(\SimpleXMLElement $celda): array
    {
        $celda->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $out = [];
        foreach ($celda->xpath('./w:p') ?: [] as $p) {
            $t = self::textoNodo($p);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    private static function textoCelda(\SimpleXMLElement $celda): string
    {
        return implode(' ', self::parrafosCelda($celda));
    }

    private static function textoNodo(\SimpleXMLElement $nodo): string
    {
        $nodo->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $partes = [];
        foreach ($nodo->xpath('.//w:t') ?: [] as $t) {
            $partes[] = (string) $t;
        }

        return trim(preg_replace('/\s+/', ' ', implode('', $partes)) ?? '');
    }

    private static function detectarTituloSeccion(string $linea): ?string
    {
        $t = trim($linea);
        if ($t === '') {
            return null;
        }
        if (preg_match('/\$\s*[\d.]/', $t)) {
            return null;
        }
        if (preg_match('/^\s*Art[ií]culo\b/iu', $t)) {
            return null;
        }
        if (preg_match('/lista\s+de\s+precios/iu', $t)) {
            return null;
        }
        if (preg_match('/vigencia|no incluyen iva|pueden variar/iu', $t)) {
            return null;
        }
        // Títulos de rubro: mayúsculas / pocas palabras
        if (preg_match('/^[A-ZÁÉÍÓÚÑ0-9][A-ZÁÉÍÓÚÑ0-9\s\/\(\)\-]{2,80}$/u', $t)
            && ! preg_match('/^[A-Z][A-Z0-9]{4,}$/', $t)) {
            return $t;
        }

        return null;
    }

    /**
     * @return array{titulo: string|null, vigencia: string|null, vigencia_ymd: string|null}
     */
    private static function extraerMeta(string $texto): array
    {
        $titulo = null;
        if (preg_match('/LISTA\s+DE\s+PRECIOS\s*N[°º]?\s*(\d+)/iu', $texto, $m)) {
            $titulo = 'Lista de precios N° '.$m[1];
        }

        $vigencia = null;
        $vigenciaYmd = null;
        if (preg_match('/Vigencia\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/iu', $texto, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                $vigencia = sprintf('%02d/%02d/%04d', $d, $mo, $y);
                $vigenciaYmd = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        return [
            'titulo' => $titulo,
            'vigencia' => $vigencia,
            'vigencia_ymd' => $vigenciaYmd,
        ];
    }
}
