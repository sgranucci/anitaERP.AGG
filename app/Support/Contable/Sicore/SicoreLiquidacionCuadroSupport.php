<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Cuadro de liquidación SICORE (port de build_workbook de la skill).
 */
final class SicoreLiquidacionCuadroSupport
{
    public const NAVY = '1F4E6B';

    public const BLUE = '2E75B6';

    public const BLUE_L = 'DDEBF7';

    public const ZEBRA = 'F3F7FB';

    public const WHITE = 'FFFFFF';

    public const INK = '1A1A1A';

    public const MUTED = '595959';

    public const GRID = 'BAC6D4';

    public const MONEY_FORMAT = '_-* #,##0.00_-;-* #,##0.00_-;_-* "-"_-';

    /**
     * @param  array<string, array{0: float, 1: float}>  $valores  codigo => [q1, q2]
     * @return array{secciones: list<array<string, mixed>>, total_q1: float, total_q2: float, total: float}
     */
    public static function armarEstructura(array $valores): array
    {
        $v767 = $valores['767'] ?? [0.0, 0.0];
        $v217 = $valores['217'] ?? [0.0, 0.0];
        $v787 = $valores['787'] ?? [0.0, 0.0];

        $secciones = [
            [
                'nombre' => 'IVA',
                'codigo' => '767',
                'filas' => [
                    ['cuenta' => '214010-021', 'concepto' => 'Retenciones IVA compras (FC M)', 'q1' => (float) $v767[0], 'q2' => (float) $v767[1]],
                    ['cuenta' => '', 'concepto' => 'Saldo a favor período anterior', 'q1' => 0.0, 'q2' => 0.0],
                ],
            ],
            [
                'nombre' => 'GANANCIAS',
                'codigo' => '217',
                'filas' => [
                    ['cuenta' => '214010-013', 'concepto' => 'Retenciones a proveedores', 'q1' => (float) $v217[0], 'q2' => (float) $v217[1]],
                    ['cuenta' => '', 'concepto' => 'Saldo a favor período anterior', 'q1' => 0.0, 'q2' => 0.0],
                    ['cuenta' => '214010-013', 'concepto' => 'Retenciones a proveedores por honorarios', 'q1' => 0.0, 'q2' => 0.0],
                    ['cuenta' => '214010-013', 'concepto' => 'Dividendos', 'q1' => 0.0, 'q2' => 0.0],
                ],
            ],
            [
                'nombre' => 'SUELDOS',
                'codigo' => '787',
                'filas' => [
                    ['cuenta' => '214010-008', 'concepto' => 'Retenciones de 4ta categoría (sueldos)', 'q1' => (float) $v787[0], 'q2' => (float) $v787[1]],
                    ['cuenta' => '', 'concepto' => 'Saldo a favor período anterior', 'q1' => 0.0, 'q2' => 0.0],
                ],
            ],
        ];

        $totalQ1 = 0.0;
        $totalQ2 = 0.0;
        foreach ($secciones as &$sec) {
            $sq1 = 0.0;
            $sq2 = 0.0;
            foreach ($sec['filas'] as &$fila) {
                $fila['total'] = round($fila['q1'] + $fila['q2'], 2);
                $sq1 += $fila['q1'];
                $sq2 += $fila['q2'];
            }
            unset($fila);
            $sec['subtotal_q1'] = round($sq1, 2);
            $sec['subtotal_q2'] = round($sq2, 2);
            $sec['subtotal'] = round($sq1 + $sq2, 2);
            $totalQ1 += $sq1;
            $totalQ2 += $sq2;
        }
        unset($sec);

        return [
            'secciones' => $secciones,
            'total_q1' => round($totalQ1, 2),
            'total_q2' => round($totalQ2, 2),
            'total' => round($totalQ1 + $totalQ2, 2),
        ];
    }

    /**
     * @param  array<string, array{0: float, 1: float}>  $valores
     */
    public static function buildWorkbook(string $empresaLabel, string $periodoLabel, array $valores): Spreadsheet
    {
        $estructura = self::armarEstructura($valores);
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $tituloHoja = self::tituloHojaSeguro($periodoLabel);
        $ws->setTitle($tituloHoja);
        $ws->setShowGridlines(false);

        foreach (['A' => 2.5, 'B' => 15, 'C' => 46, 'D' => 16, 'E' => 16, 'F' => 17, 'G' => 2.5] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        self::band($ws, 'B2:F2', $empresaLabel, self::NAVY, self::WHITE, 14, Alignment::HORIZONTAL_CENTER, 28);
        self::band($ws, 'B3:F3', 'Liquidación de SICORE   ·   '.$periodoLabel, self::NAVY, self::WHITE, 10.5, Alignment::HORIZONTAL_CENTER, 18);
        $ws->getRowDimension(4)->setRowHeight(6);

        $r = 5;
        foreach ([
            ['B', 'Cuenta', Alignment::HORIZONTAL_CENTER],
            ['C', 'Concepto', Alignment::HORIZONTAL_LEFT],
            ['D', '1ra Quincena', Alignment::HORIZONTAL_CENTER],
            ['E', '2da Quincena', Alignment::HORIZONTAL_CENTER],
            ['F', 'Total Mensual', Alignment::HORIZONTAL_CENTER],
        ] as [$col, $txt, $al]) {
            self::cell($ws, $col.$r, $txt, self::BLUE, self::WHITE, true, 10, $al);
        }
        $ws->getRowDimension($r)->setRowHeight(20);

        $r = 6;
        $filasTotales = [];
        foreach ($estructura['secciones'] as $sec) {
            self::band(
                $ws,
                "B{$r}:F{$r}",
                $sec['nombre'].'  —  Código '.$sec['codigo'],
                self::BLUE_L,
                self::NAVY,
                10,
                Alignment::HORIZONTAL_LEFT,
                18,
            );
            $r++;
            $first = $r;
            foreach ($sec['filas'] as $i => $fila) {
                $bg = ($i % 2) ? self::ZEBRA : self::WHITE;
                self::cell($ws, 'B'.$r, $fila['cuenta'], $bg, self::MUTED, false, 9.5, Alignment::HORIZONTAL_LEFT);
                self::cell($ws, 'C'.$r, $fila['concepto'], $bg, self::INK, false, 10, Alignment::HORIZONTAL_LEFT);
                self::cell($ws, 'D'.$r, $fila['q1'], $bg, self::INK, false, 10, Alignment::HORIZONTAL_RIGHT, true);
                self::cell($ws, 'E'.$r, $fila['q2'], $bg, self::INK, false, 10, Alignment::HORIZONTAL_RIGHT, true);
                self::cell($ws, 'F'.$r, $fila['total'], $bg, self::INK, false, 10, Alignment::HORIZONTAL_RIGHT, true);
                $ws->getRowDimension($r)->setRowHeight(16);
                $r++;
            }
            $last = $r - 1;
            self::band($ws, "B{$r}:C{$r}", 'Total Código '.$sec['codigo'], self::BLUE, self::WHITE, 10, Alignment::HORIZONTAL_LEFT, 18);
            foreach (['D', 'E', 'F'] as $col) {
                self::cell(
                    $ws,
                    $col.$r,
                    '=SUM('.$col.$first.':'.$col.$last.')',
                    self::BLUE,
                    self::WHITE,
                    true,
                    10,
                    Alignment::HORIZONTAL_RIGHT,
                    true,
                );
            }
            $filasTotales[] = $r;
            $r++;
            $ws->getRowDimension($r)->setRowHeight(6);
            $r++;
        }

        $gr = $r;
        self::band($ws, "B{$gr}:C{$gr}", 'TOTAL A INGRESAR', self::NAVY, self::WHITE, 11.5, Alignment::HORIZONTAL_LEFT, 24);
        foreach (['D', 'E', 'F'] as $col) {
            $parts = array_map(static fn (int $fr) => $col.$fr, $filasTotales);
            self::cell(
                $ws,
                $col.$gr,
                '='.implode('+', $parts),
                self::NAVY,
                self::WHITE,
                true,
                11.5,
                Alignment::HORIZONTAL_RIGHT,
                true,
            );
        }

        $ws->getPageSetup()->setPrintArea('A1:G'.($gr + 1));
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $ws->getPageSetup()->setFitToPage(true);
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(1);
        $ws->getPageMargins()->setLeft(0.4);
        $ws->getPageMargins()->setRight(0.4);
        $ws->getPageMargins()->setTop(0.6);
        $ws->getPageMargins()->setBottom(0.6);

        return $ss;
    }

    /**
     * Excel no permite en título de hoja: : \ / ? * [ ]
     */
    public static function tituloHojaSeguro(string $periodoLabel): string
    {
        $base = trim((string) preg_replace('/\s*[·•]\s*.*$/u', '', $periodoLabel));
        if ($base === '') {
            $base = 'SICORE';
        }
        $base = (string) preg_replace('/[:\\\\\\/?*\\[\\]]+/', '-', $base);
        $base = trim((string) preg_replace('/\\s+/u', ' ', $base));
        if ($base === '' || $base === '-') {
            $base = 'SICORE';
        }

        return mb_substr($base, 0, 31);
    }

    private static function band(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        string $range,
        string $value,
        string $fill,
        string $color,
        float $size,
        string $align,
        float $height,
    ): void {
        [$start] = explode(':', $range);
        $ws->mergeCells($range);
        self::cell($ws, $start, $value, $fill, $color, true, $size, $align);
        $row = (int) preg_replace('/\D/', '', $start);
        $ws->getRowDimension($row)->setRowHeight($height);
        $ws->getStyle($range)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF'.self::GRID));
    }

    private static function cell(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws,
        string $coord,
        mixed $value,
        string $fill,
        string $color,
        bool $bold,
        float $size,
        string $align,
        bool $money = false,
    ): void {
        $cell = $ws->getCell($coord);
        $cell->setValue($value);
        $style = $ws->getStyle($coord);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        $style->getFont()->setName('Calibri')->setSize($size)->setBold($bold)->getColor()->setRGB($color);
        $style->getAlignment()->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);
        $style->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF'.self::GRID));
        if ($money) {
            $style->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        }
    }
}
