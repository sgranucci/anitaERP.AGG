<?php

namespace App\Support\Contable\ConciliacionBancaria;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Lee el Excel de comparación de Florencia (Anita Actual vs ERP) con leyenda:
 * - verde Anita Actual: OK en ambos
 * - amarillo Anita Actual: faltan en ERP
 * - rojo Anita ERP: no corresponden (sacar del ERP)
 */
final class ConciliacionBancariaComparacionChequesFlorenciaSupport
{
    /**
     * @return array{
     *   archivo: string,
     *   amarillos: list<array<string,mixed>>,
     *   rojos: list<array<string,mixed>>,
     *   verdes_anita: list<string>,
     *   verdes_erp: list<string>
     * }
     */
    public static function leer(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer: '.$ruta);
        }

        try {
            $reader = IOFactory::createReaderForFile($ruta);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(false); // hace falta el fill
            }
            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly(['Pend, Cheques ACTUAL', 'Pend. cheques ERP']);
            }
            $libro = $reader->load($ruta);
        } catch (Throwable $e) {
            throw new RuntimeException('Error abriendo comparación Florencia: '.$e->getMessage(), 0, $e);
        }

        $anita = $libro->getSheetByName('Pend, Cheques ACTUAL');
        $erp = $libro->getSheetByName('Pend. cheques ERP');
        if ($anita === null || $erp === null) {
            throw new RuntimeException('Faltan solapas «Pend, Cheques ACTUAL» / «Pend. cheques ERP».');
        }

        $amarillos = [];
        $verdesAnita = [];
        $max = (int) $anita->getHighestRow();
        for ($r = 6; $r <= $max; $r++) {
            $tip = trim((string) $anita->getCellByColumnAndRow(1, $r)->getValue());
            if ($tip === '' || str_starts_with(mb_strtolower($tip), 'total')) {
                continue;
            }
            $cat = self::categoriaColor($anita->getCellByColumnAndRow(1, $r));
            if ($cat === null) {
                $cat = self::categoriaColor($anita->getCellByColumnAndRow(2, $r));
            }
            $fila = self::filaAnita($anita, $r, $tip);
            if ($fila === null) {
                continue;
            }
            if ($cat === 'yellow') {
                $amarillos[] = $fila;
            } elseif ($cat === 'green') {
                $verdesAnita[] = $fila['numero'];
            }
        }

        $rojos = [];
        $verdesErp = [];
        $maxE = (int) $erp->getHighestRow();
        for ($r = 4; $r <= $maxE; $r++) {
            $tip = trim((string) $erp->getCellByColumnAndRow(1, $r)->getValue());
            if ($tip === '' || strcasecmp($tip, 'Tip') === 0) {
                continue;
            }
            $cat = self::categoriaColor($erp->getCellByColumnAndRow(1, $r));
            if ($cat === null) {
                $cat = self::categoriaColor($erp->getCellByColumnAndRow(2, $r));
            }
            $fila = self::filaErp($erp, $r, $tip);
            if ($fila === null) {
                continue;
            }
            if ($cat === 'red') {
                $rojos[] = $fila;
            } elseif ($cat === 'green') {
                $verdesErp[] = $fila['numero'];
            }
        }

        unset($libro);

        return [
            'archivo' => basename($ruta),
            'amarillos' => $amarillos,
            'rojos' => $rojos,
            'verdes_anita' => array_values(array_unique($verdesAnita)),
            'verdes_erp' => array_values(array_unique($verdesErp)),
        ];
    }

    /**
     * @return array{tip: string, numero: string, importe: float, detalle: string, fecha_emision: ?string, fecha_cheque: ?string}|null
     */
    private static function filaAnita($hoja, int $r, string $tip): ?array
    {
        $numero = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) $hoja->getCellByColumnAndRow(2, $r)->getCalculatedValue());
        if ($numero === '' || $numero === '0') {
            return null;
        }
        $deb = self::floatCelda($hoja->getCellByColumnAndRow(9, $r));
        $cred = self::floatCelda($hoja->getCellByColumnAndRow(10, $r));
        $importe = $cred > 0.005 ? $cred : ($deb > 0.005 ? -$deb : $cred);

        return [
            'tip' => strtoupper($tip),
            'numero' => $numero,
            'importe' => round($importe, 2),
            'detalle' => trim((string) $hoja->getCellByColumnAndRow(8, $r)->getCalculatedValue()),
            'fecha_emision' => self::fechaCelda($hoja->getCellByColumnAndRow(4, $r)),
            'fecha_cheque' => self::fechaCelda($hoja->getCellByColumnAndRow(5, $r)),
        ];
    }

    /**
     * @return array{tip: string, numero: string, importe: float, detalle: string, fecha_emision: ?string, fecha_cheque: ?string}|null
     */
    private static function filaErp($hoja, int $r, string $tip): ?array
    {
        $numero = ConciliacionBancariaExcelReferenciaSupport::soloDigitos((string) $hoja->getCellByColumnAndRow(2, $r)->getCalculatedValue());
        if ($numero === '' || $numero === '0') {
            return null;
        }
        $deb = self::floatCelda($hoja->getCellByColumnAndRow(8, $r));
        $cred = self::floatCelda($hoja->getCellByColumnAndRow(9, $r));
        $importe = $cred > 0.005 ? $cred : ($deb > 0.005 ? -$deb : $cred);

        return [
            'tip' => strtoupper($tip),
            'numero' => $numero,
            'importe' => round($importe, 2),
            'detalle' => trim((string) $hoja->getCellByColumnAndRow(7, $r)->getCalculatedValue()),
            'fecha_emision' => self::fechaCelda($hoja->getCellByColumnAndRow(3, $r)),
            'fecha_cheque' => self::fechaCelda($hoja->getCellByColumnAndRow(4, $r)),
        ];
    }

    private static function categoriaColor($cell): ?string
    {
        $fill = $cell->getStyle()->getFill();
        $type = (string) $fill->getFillType();
        if ($type === '' || $type === 'none') {
            return null;
        }
        $rgb = strtoupper((string) $fill->getStartColor()->getRGB());
        if ($rgb === '' || $rgb === '000000' || $rgb === 'FFFFFF') {
            // a veces el theme queda vacío y el rgb está en ARGB
            $argb = strtoupper((string) $fill->getStartColor()->getARGB());
            $rgb = strlen($argb) >= 6 ? substr($argb, -6) : $argb;
        }
        if (in_array($rgb, ['92D050', '00B050', 'C6EFCE', '70AD47'], true)) {
            return 'green';
        }
        if (in_array($rgb, ['FFFF00', 'FFC000', 'FFEB9C', 'FFE699', 'FFD966'], true)) {
            return 'yellow';
        }
        if (in_array($rgb, ['FF0000', 'C00000', 'FFC7CE', 'FF6B6B'], true)) {
            return 'red';
        }

        return null;
    }

    private static function floatCelda($cell): float
    {
        $v = $cell->getCalculatedValue();
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }

        return 0.0;
    }

    private static function fechaCelda($cell): ?string
    {
        $v = $cell->getCalculatedValue();
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        if (is_string($v) && trim($v) !== '' && ! str_contains($v, '--')) {
            $ts = strtotime($v);

            return $ts ? date('Y-m-d', $ts) : null;
        }

        return null;
    }
}
