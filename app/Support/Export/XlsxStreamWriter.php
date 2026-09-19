<?php

namespace App\Support\Export;

/**
 * Arma un .xlsx real (Office Open XML) sin PhpSpreadsheet.
 * Escribe la hoja a disco y la empaqueta en zip: sirve para decenas de miles de filas
 * sin inflar memoria ni HTML intermedio.
 */
final class XlsxStreamWriter
{
    private $sheetHandle;

    private string $sheetPath;

    private int $filaActual = 0;

    private int $columnas = 0;

    private string $ultimaColumna = 'A';

    /** @var array<int, bool> índice 0-based → Debe/Haber/Cotización */
    private array $columnaNumerica = [];

    private bool $sheetDataAbierto = false;

    public function __construct(
        private readonly string $rutaXlsx,
        private readonly string $nombreHoja = 'Hoja1',
    ) {
        $dir = dirname($rutaXlsx);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('No se pudo crear directorio de export: '.$dir);
            }
            @chmod($dir, 0775);
        }
        if (! is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (! is_writable($dir)) {
            throw new \RuntimeException('Directorio de export sin permiso de escritura: '.$dir);
        }

        $this->sheetPath = $rutaXlsx.'.sheet.xml';
        $handle = fopen($this->sheetPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear hoja Excel: '.$this->sheetPath);
        }
        $this->sheetHandle = $handle;

        fwrite($this->sheetHandle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0" tabSelected="1">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>');
    }

    /**
     * @param  list<string>  $cabeceras
     */
    public function escribirCabecera(array $cabeceras): void
    {
        $this->columnas = count($cabeceras);
        $this->ultimaColumna = self::columnaExcel(max(1, $this->columnas));
        foreach ($cabeceras as $i => $titulo) {
            $this->columnaNumerica[$i] = $this->esTituloNumerico((string) $titulo);
        }
        $this->escribirAnchosColumna($cabeceras);
        $this->asegurarSheetData();
        $this->escribirFilaXml($cabeceras, true);
    }

    /**
     * @param  list<string|int|float|null>  $valores
     */
    public function escribirFila(array $valores): void
    {
        $this->asegurarSheetData();
        $this->escribirFilaXml($valores, false);
    }

    /**
     * @return array{path: string, filas: int, bytes: int}
     */
    public function cerrar(): array
    {
        if (! is_resource($this->sheetHandle)) {
            throw new \RuntimeException('El escritor Excel ya fue cerrado.');
        }

        $this->asegurarSheetData();
        $datos = max(1, $this->filaActual);
        fwrite($this->sheetHandle, '</sheetData>');
        if ($this->columnas > 0) {
            $ref = 'A1:'.$this->ultimaColumna.$datos;
            fwrite($this->sheetHandle, '<autoFilter ref="'.$ref.'"/>');
        }
        fwrite($this->sheetHandle, '</worksheet>');
        fclose($this->sheetHandle);
        $this->sheetHandle = null;

        $this->empaquetar();
        @unlink($this->sheetPath);
        @chmod($this->rutaXlsx, 0664);

        return [
            'path' => $this->rutaXlsx,
            'filas' => max(0, $this->filaActual - 1),
            'bytes' => (int) filesize($this->rutaXlsx),
        ];
    }

    public static function columnaExcel(int $indice1): string
    {
        $s = '';
        $n = $indice1;
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)).$s;
            $n = intdiv($n, 26);
        }

        return $s !== '' ? $s : 'A';
    }

    /**
     * @param  list<string|int|float|null>  $valores
     */
    private function escribirFilaXml(array $valores, bool $esCabecera): void
    {
        $this->filaActual++;
        $r = $this->filaActual;
        $xml = '<row r="'.$r.'">';
        foreach (array_values($valores) as $i => $valor) {
            $col = self::columnaExcel($i + 1);
            $ref = $col.$r;
            if ($esCabecera) {
                $xml .= '<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'
                    .self::xml((string) $valor).'</t></is></c>';

                continue;
            }
            if ($this->esCeldaNumerica($i, $valor)) {
                $xml .= '<c r="'.$ref.'" t="n" s="2"><v>'.$this->numeroXml($valor).'</v></c>';

                continue;
            }
            if ($this->esEnteroIdentificador($valor)) {
                $xml .= '<c r="'.$ref.'" t="n"><v>'.(int) $valor.'</v></c>';

                continue;
            }
            $texto = trim((string) ($valor ?? ''));
            if ($texto === '') {
                continue;
            }
            $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'
                .self::xml($texto).'</t></is></c>';
        }
        $xml .= '</row>';
        fwrite($this->sheetHandle, $xml);
        if ($r % 2000 === 0) {
            fflush($this->sheetHandle);
        }
    }

    private function esTituloNumerico(string $titulo): bool
    {
        $n = mb_strtolower(trim($titulo));

        return in_array($n, ['debe', 'haber', 'cotizacion', 'cotización', 'importe'], true);
    }

    private function esCeldaNumerica(int $indice, mixed $valor): bool
    {
        if (empty($this->columnaNumerica[$indice])) {
            return false;
        }
        if ($valor === null || $valor === '') {
            return false;
        }
        if (is_int($valor) || is_float($valor)) {
            return true;
        }
        if (! is_string($valor)) {
            return false;
        }
        $valor = str_replace(',', '.', trim($valor));

        return $valor !== '' && is_numeric($valor);
    }

    private function esEnteroIdentificador(mixed $valor): bool
    {
        if (is_int($valor)) {
            return true;
        }
        if (is_float($valor)) {
            return abs($valor - round($valor)) < 0.0000001;
        }
        if (! is_string($valor)) {
            return false;
        }
        $valor = trim($valor);

        return $valor !== '' && preg_match('/^-?\d+$/', $valor) === 1;
    }

    private function asegurarSheetData(): void
    {
        if ($this->sheetDataAbierto) {
            return;
        }
        fwrite($this->sheetHandle, '<sheetData>');
        $this->sheetDataAbierto = true;
    }

    /**
     * @param  list<string>  $cabeceras
     */
    private function escribirAnchosColumna(array $cabeceras): void
    {
        $xml = '<cols>';
        foreach (array_values($cabeceras) as $i => $titulo) {
            $min = $i + 1;
            $xml .= '<col min="'.$min.'" max="'.$min.'" width="'.$this->anchoColumna((string) $titulo)
                .'" customWidth="1"/>';
        }
        $xml .= '</cols>';
        fwrite($this->sheetHandle, $xml);
    }

    private function anchoColumna(string $titulo): string
    {
        $n = mb_strtolower(trim($titulo));

        return match (true) {
            $n === 'empresa' => '9',
            str_contains($n, 'nro.asi') => '14',
            $n === 'fecha' => '12',
            $n === 'cuenta' => '14',
            str_contains($n, 'descrip') => '28',
            str_contains($n, 'c.costo') || str_contains($n, 'centrocosto') => '12',
            $n === 'mon' => '8',
            str_contains($n, 'cotiz') => '12',
            $n === 'debe' || $n === 'haber' => '16',
            $n === 'detalle' => '42',
            str_contains($n, 'cod. emisor') => '12',
            str_contains($n, 'nombre emisor') => '28',
            $n === 'usuario' => '14',
            str_contains($n, 'fecha ult') => '14',
            str_contains($n, 'o.compra') => '12',
            str_contains($n, 'capex') => '16',
            str_contains($n, 'que se compro') => '32',
            str_contains($n, 'factura') => '24',
            default => '14',
        };
    }

    private function numeroXml(mixed $valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }
        $n = is_string($valor) ? (float) str_replace(',', '.', $valor) : (float) $valor;
        $s = sprintf('%.6F', $n);

        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }

    private static function xml(string $texto): string
    {
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto) ?? $texto;

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function nombreHojaSanitizado(): string
    {
        $nombre = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', ' ', $this->nombreHoja) ?? $this->nombreHoja;
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = 'Mayor plano';
        }
        if (mb_strlen($nombre) > 31) {
            $nombre = mb_substr($nombre, 0, 31);
        }

        return $nombre;
    }

    private function empaquetar(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive no está disponible para generar Excel.');
        }
        if (is_file($this->rutaXlsx)) {
            @unlink($this->rutaXlsx);
        }

        $zip = new \ZipArchive;
        if ($zip->open($this->rutaXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear Excel: '.$this->rutaXlsx);
        }

        $hoja = self::xml($this->nombreHojaSanitizado());
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>'
        );
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>'
        );
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>'
        );
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$hoja.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>'
        );
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF17202A"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF85C1E9"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>'
        );
        if (! $zip->addFile($this->sheetPath, 'xl/worksheets/sheet1.xml')) {
            $zip->close();
            throw new \RuntimeException('No se pudo empaquetar la hoja Excel.');
        }
        if ($zip->close() !== true) {
            throw new \RuntimeException('No se pudo cerrar el Excel: '.$this->rutaXlsx);
        }
        if (! is_file($this->rutaXlsx) || filesize($this->rutaXlsx) <= 0) {
            throw new \RuntimeException('Excel vacío: '.$this->rutaXlsx);
        }
    }
}
