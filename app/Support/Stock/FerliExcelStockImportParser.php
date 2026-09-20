<?php

namespace App\Support\Stock;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee los Excel de stock Ferli (/home/sergio/tmp/stock).
 * No persiste. Distingue lote importado vs número de OT.
 */
final class FerliExcelStockImportParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function parseDirectory(string $dir): array
    {
        $filas = [];
        $paths = glob(rtrim($dir, '/').'/*.xlsx') ?: [];
        sort($paths);
        foreach ($paths as $path) {
            $filas = array_merge($filas, self::parseFile($path));
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFile(string $path): array
    {
        $wb = IOFactory::load($path);
        $out = [];
        foreach ($wb->getAllSheets() as $ws) {
            $layout = self::detectarLayout($ws);
            if ($layout === null) {
                continue;
            }
            $out = array_merge($out, self::parseSheet($ws, basename($path), $layout));
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function detectarLayout(Worksheet $ws): ?array
    {
        $headerRow = 0;
        $maxC = min(40, Coordinate::columnIndexFromString($ws->getHighestDataColumn()));
        $maxR = min(4, (int) $ws->getHighestDataRow());
        for ($r = 1; $r <= $maxR; $r++) {
            for ($c = 1; $c <= $maxC; $c++) {
                $txt = self::norm(self::cellStr($ws, $c, $r));
                if (preg_match('/^art\.?$/', $txt)) {
                    $headerRow = $r;
                    break 2;
                }
            }
        }
        if ($headerRow === 0) {
            return null;
        }

        $cols = [
            'sku' => 3,
            'descripcion' => 4,
            'color' => 0,
            'situacion' => 0,
            'identificador' => 0,
            'deposito' => 0,
            'modulos' => 0,
            'precio_cols' => [],
            'talles' => [],
            'depositos_tomahawk' => [],
        ];

        for ($c = 1; $c <= $maxC; $c++) {
            $raw = self::cellStr($ws, $c, $headerRow);
            $txt = self::norm($raw);
            if ($txt === '') {
                $txt = self::norm(self::cellStr($ws, $c, 1));
            }
            if (preg_match('/^\d{1,2}$/', $txt) && (int) $txt >= 5 && (int) $txt <= 47) {
                $cols['talles'][$c] = (int) $txt;
                continue;
            }
            if ($txt === 'color') {
                $cols['color'] = $c;
            } elseif (str_contains($txt, 'descripcion') || str_contains($txt, 'descripción')) {
                $cols['descripcion'] = $c;
            } elseif (str_contains($txt, 'situacion') || str_contains($txt, 'situación')) {
                $cols['situacion'] = $c;
            } elseif (str_contains($txt, 'numero ot') || str_contains($txt, 'nro de ot') || str_contains($txt, 'nº ot') || $txt === 'ot' || str_contains($txt, 'nro ot') || str_contains($txt, 'num. ot')) {
                $sample = self::cellStr($ws, $c, $headerRow + 1);
                $sampleN = strtoupper(self::sinAcento($sample));
                if (str_contains($sampleN, 'ENTREGA') || self::esEnProduccion($sample)) {
                    $cols['situacion'] = $c;
                } else {
                    $cols['identificador'] = $c;
                }
            } elseif (str_contains($txt, 'deposito') || str_contains($txt, 'depósito') || str_contains($txt, 'dposito')) {
                $cols['deposito'] = $c;
            } elseif (in_array($txt, ['x', 'q m', 'q.m', 'q.m.', 'mod', 'qm', 'c/m', 'c/m.'], true)) {
                $cols['modulos'] = $c + 1;
            } elseif (str_contains($txt, 'precio') || preg_match('/^\d{2}-\d{2}$/', $txt)) {
                $cols['precio_cols'][] = $c;
            }

            $depCodigo = self::aliasDeposito($raw !== '' ? $raw : self::cellStr($ws, $c, 1));
            if ($depCodigo !== null && $cols['deposito'] === 0) {
                $hdr = self::norm($raw);
                if (str_contains($hdr, 'tal-a') || str_contains($hdr, 'fabrica') || str_contains($hdr, 'lugano') || str_contains($hdr, 'dep 12') || str_contains($hdr, 'dep12')) {
                    $cols['depositos_tomahawk'][$c] = $depCodigo;
                }
            }
        }

        if ($cols['talles'] === []) {
            return null;
        }
        if ($cols['modulos'] === 0) {
            for ($c = 1; $c <= $maxC; $c++) {
                if (self::norm(self::cellStr($ws, $c, $headerRow)) === 'n') {
                    $cols['modulos'] = $c;
                    break;
                }
            }
        }
        if ($cols['identificador'] === 0 && $cols['situacion'] > 0) {
            $cols['identificador'] = $cols['situacion'] + 1;
        }
        if ($cols['modulos'] === 0) {
            foreach (['AE', 'R', 'AD'] as $letra) {
                $idx = Coordinate::columnIndexFromString($letra);
                if ($idx <= $maxC) {
                    $cols['identificador'] = $idx;
                    break;
                }
            }
        }

        return ['header_row' => $headerRow, 'cols' => $cols];
    }

    /**
     * @param  array{header_row: int, cols: array<string, mixed>}  $layout
     * @return list<array<string, mixed>>
     */
    private static function parseSheet(Worksheet $ws, string $archivo, array $layout): array
    {
        $cols = $layout['cols'];
        $out = [];
        $carryIdent = [];
        $maxR = (int) $ws->getHighestDataRow();
        for ($r = $layout['header_row'] + 1; $r <= $maxR; $r++) {
            $skuRaw = self::cellStr($ws, $cols['sku'], $r);
            if ($skuRaw === '' || ! preg_match('/\d/', $skuRaw) || ! str_contains($skuRaw, '-')) {
                continue;
            }
            $desc = self::cellStr($ws, $cols['descripcion'], $r);
            $color = ($cols['color'] ?? 0) > 0 ? self::cellStr($ws, $cols['color'], $r) : '';
            if ($color !== '' && preg_match('/^\d+\s*-/', trim($color))) {
                $desc = $color;
            } elseif ($desc === '' && $color !== '') {
                $desc = $color;
            }
            $curva = [];
            foreach ($cols['talles'] as $c => $medida) {
                $val = self::cellNum($ws, $c, $r);
                if ($val > 0) {
                    $curva[$medida] = $val;
                }
            }
            $ps = array_sum($curva);
            $modulosFila = $cols['modulos'] > 0 ? (int) round(self::cellNum($ws, $cols['modulos'], $r)) : 0;
            if ($modulosFila <= 0) {
                $modulosFila = 1;
            }
            $precio = 0.0;
            foreach ($cols['precio_cols'] as $cPrecio) {
                $p = self::cellNum($ws, $cPrecio, $r);
                if ($p > 0) {
                    $precio = $p;
                    break;
                }
            }
            $situacion = $cols['situacion'] > 0 ? self::cellStr($ws, $cols['situacion'], $r) : '';
            $idTexto = $cols['identificador'] > 0 ? self::cellStr($ws, $cols['identificador'], $r) : '';
            $carryKey = $skuRaw.'|'.$desc;
            if (trim($idTexto) !== '') {
                $carryIdent[$carryKey] = $idTexto;
            } elseif (isset($carryIdent[$carryKey])) {
                $idTexto = $carryIdent[$carryKey];
            }
            $rojo = self::esRojo($ws, $cols['sku'], $r) || ($cols['situacion'] > 0 && self::esRojo($ws, $cols['situacion'], $r));
            $enProduccion = $rojo || self::esEnProduccion($situacion);

            $depositos = [];
            if ($cols['depositos_tomahawk'] !== []) {
                foreach ($cols['depositos_tomahawk'] as $cDep => $depCodigo) {
                    $txt = self::cellStr($ws, $cDep, $r);
                    if (trim($txt) === '') {
                        continue;
                    }
                    $depositos[] = ['codigo' => $depCodigo, 'texto' => $txt];
                }
            } else {
                $depRaw = $cols['deposito'] > 0 ? self::cellStr($ws, $cols['deposito'], $r) : '';
                $depCodigo = self::aliasDeposito($depRaw);
                $depositos[] = ['codigo' => $depCodigo, 'texto' => $idTexto];
            }

            if ($enProduccion) {
                $out[] = self::filaBase($archivo, $ws->getTitle(), $r, $skuRaw, $desc, $situacion, true, 'en_produccion', $ps, $modulosFila, $precio, $curva, null, $idTexto);
                continue;
            }

            $sinDeposito = true;
            foreach ($depositos as $dep) {
                if ($dep['codigo'] === null || $dep['codigo'] === '') {
                    continue;
                }
                $sinDeposito = false;
                $identificadores = self::parseIdentificadores($dep['texto'] !== '' ? $dep['texto'] : $idTexto, $modulosFila);
                if ($identificadores === []) {
                    $identificadores = [['valor' => '', 'modulos' => $modulosFila, 'crudo' => $dep['texto']]];
                }
                foreach ($identificadores as $ident) {
                    $mods = $ident['modulos'] > 0 ? $ident['modulos'] : $modulosFila;
                    $out[] = self::filaBase(
                        $archivo,
                        $ws->getTitle(),
                        $r,
                        $skuRaw,
                        $desc,
                        $situacion,
                        false,
                        null,
                        $ps,
                        $mods,
                        $precio,
                        $curva,
                        $dep['codigo'],
                        (string) $ident['valor']
                    );
                }
            }
            if ($sinDeposito) {
                $out[] = self::filaBase($archivo, $ws->getTitle(), $r, $skuRaw, $desc, $situacion, false, 'sin_deposito', $ps, $modulosFila, $precio, $curva, null, $idTexto);
            }
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $curva
     * @return array<string, mixed>
     */
    private static function filaBase(
        string $archivo,
        string $hoja,
        int $fila,
        string $skuRaw,
        string $desc,
        string $situacion,
        bool $enProduccion,
        ?string $omitir,
        float $ps,
        int $modulos,
        float $precio,
        array $curva,
        ?string $depositoCodigo,
        string $identificador
    ): array {
        $talles = [];
        foreach ($curva as $medida => $cantModulo) {
            $talles[(int) $medida] = round($cantModulo * $modulos, 4);
        }

        return [
            'archivo' => $archivo,
            'hoja' => $hoja,
            'fila' => $fila,
            'sku_excel' => $skuRaw,
            'sku' => preg_replace('/\D+/', '', $skuRaw) ?? '',
            'descripcion' => $desc,
            'codigo_combinacion' => self::codigoCombinacionDesdeDescripcionYSku($desc, $skuRaw),
            'situacion' => $situacion,
            'en_produccion' => $enProduccion,
            'omitir' => $omitir,
            'ps' => $ps,
            'modulos' => $modulos,
            'pares' => round($ps * $modulos, 4),
            'precio' => $precio,
            'deposito_codigo' => $depositoCodigo,
            'identificador' => trim($identificador),
            'talles' => $talles,
        ];
    }

    public static function codigoCombinacionDesdeDescripcionYSku(string $desc, string $skuExcel): string
    {
        if (preg_match('/^(\d+)\s*-/', trim($desc), $m)) {
            return (string) (int) $m[1];
        }

        return self::codigoCombinacionDesdeSku($skuExcel);
    }

    public static function codigoCombinacionDesdeSku(string $skuExcel): string
    {
        if (preg_match('/-(\d+)\s*$/', $skuExcel, $m)) {
            return (string) (int) $m[1];
        }

        return '';
    }

    /**
     * @return list<array{valor: string, modulos: int, crudo: string}>
     */
    public static function parseIdentificadores(string $texto, int $modulosFila): array
    {
        $texto = trim($texto);
        if ($texto === '' || strcasecmp($texto, 'MYRIAM') === 0) {
            return [];
        }
        // 12.410 (5) → 12410: el Excel usa punto de miles en nros de OT.
        $texto = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $texto) ?? $texto;
        if (! preg_match_all('/(?:lotr?e?\s*)?(\d{4,})\s*(?:\(\s*(\d+)\s*\))? /i', $texto.' ', $matches, PREG_SET_ORDER)) {
            return [];
        }
        $hayParens = false;
        $out = [];
        foreach ($matches as $m) {
            $mods = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            if ($mods > 0) {
                $hayParens = true;
            }
            $out[] = ['valor' => $m[1], 'modulos' => $mods, 'crudo' => $m[0]];
        }
        if ($hayParens) {
            foreach ($out as &$item) {
                if ($item['modulos'] <= 0) {
                    $item['modulos'] = 1;
                }
            }
            unset($item);

            return $out;
        }
        if (count($out) === 1) {
            $out[0]['modulos'] = $modulosFila;

            return $out;
        }
        $resto = $modulosFila;
        $n = count($out);
        foreach ($out as $i => &$item) {
            $item['modulos'] = $i === $n - 1 ? max(1, $resto) : 1;
            $resto -= $item['modulos'];
        }
        unset($item);

        return $out;
    }

    public static function aliasDeposito(?string $raw): ?string
    {
        $txt = self::norm((string) $raw);
        $txt = preg_replace('/\s+/', ' ', $txt) ?? $txt;
        if ($txt === '') {
            return null;
        }
        $map = [
            'tal-e2' => 'Tal-E2',
            'myriam' => 'Tal-E2',
            'myriam tal-e2' => 'Tal-E2',
            '64-a' => '64-A',
            '64-c' => '64-C',
            '2-e2' => '2-E2',
            'tal-a' => 'Tal-A',
            'fabrica tal-a' => 'Tal-A',
            'fabrica tal a' => 'Tal-A',
            'tal-e1' => 'Tal-E1',
            '2-a' => '2-A',
            '69-a' => '69-A',
            'lugano' => '10',
            'dep 12' => '12',
            'dep12' => '12',
            '12' => '12',
            '10' => '10',
        ];

        return $map[$txt] ?? (preg_match('/^[a-z0-9._ -]{1,10}$/', $txt) ? $raw : null);
    }

    public static function esEnProduccion(string $situacion): bool
    {
        $t = strtoupper(self::sinAcento($situacion));

        return str_contains($t, 'EN PRODUCCION');
    }

    private static function esRojo(Worksheet $ws, int $col, int $row): bool
    {
        $argb = strtoupper((string) $ws->getCell(Coordinate::stringFromColumnIndex($col).$row)
            ->getStyle()->getFont()->getColor()->getARGB());

        return $argb !== '' && str_ends_with($argb, 'FF0000');
    }

    private static function cellStr(Worksheet $ws, int $col, int $row): string
    {
        $cell = $ws->getCell(Coordinate::stringFromColumnIndex($col).$row);
        $v = $cell->getCalculatedValue();

        return trim((string) ($v ?? ''));
    }

    private static function cellNum(Worksheet $ws, int $col, int $row): float
    {
        $v = $ws->getCell(Coordinate::stringFromColumnIndex($col).$row)->getCalculatedValue();
        if (is_numeric($v)) {
            return (float) $v;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d.,-]/', '', (string) $v) ?? '0');
    }

    private static function norm(string $s): string
    {
        return strtolower(trim(self::sinAcento($s)));
    }

    private static function sinAcento(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return $t !== false ? $t : $s;
    }
}
