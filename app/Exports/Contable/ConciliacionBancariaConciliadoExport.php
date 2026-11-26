<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de lo conciliado en una cuenta de caja dentro de un rango de fecha de conciliación.
 */
class ConciliacionBancariaConciliadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    /** @var array<string, mixed> */
    private array $reporte = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $reporte
     * @param  array<string, mixed>  $meta
     */
    public function parametros(array $reporte, array $meta): self
    {
        $this->reporte = $reporte;
        $this->meta = $meta;

        $paraLogos = collect([(object) ['nombreempresa' => (string) ($meta['empresa_nombre'] ?? '')]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 3;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.contable.conciliacion_bancaria_conciliadoindex', [
            'reporte' => $this->reporte,
            'meta' => $this->meta,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Conciliado';
    }

    public function columnFormats(): array
    {
        $formatos = [];
        foreach (range('A', 'J') as $col) {
            $formatos[$col] = NumberFormat::FORMAT_TEXT;
        }
        $formatos['K'] = '#,##0.00';
        $formatos['L'] = '#,##0.00';

        return $formatos;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 17,
            'B' => 22,
            'C' => 13,
            'D' => 8,
            'E' => 13,
            'F' => 28,
            'G' => 40,
            'H' => 13,
            'I' => 26,
            'J' => 16,
            'K' => 16,
            'L' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $d = new Drawing;
                        $d->setPath($ruta);
                        $d->setHeight(48);
                        $d->setCoordinates('A1');
                        $d->setOffsetX($x);
                        $d->setWorksheet($sheet);
                        $x += 90;
                    }
                }
                foreach (range($this->filaInicioMeta, $this->filaCabecerasExcel - 1) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $filas = array_values($this->reporte['filas'] ?? []);
                foreach ($filas as $i => $fila) {
                    $excelRow = $this->filaPrimeraDatosExcel + $i;
                    foreach (['K' => 'importe', 'L' => 'diferencia'] as $col => $campo) {
                        $val = round((float) ($fila[$campo] ?? 0), 2);
                        $sheet->setCellValueExplicit($col.$excelRow, $val, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($col.$excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle($col.$excelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                $this->aplicarTotalFinal($sheet, $filas);
            },
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function aplicarTotalFinal(Worksheet $sheet, array $filas): void
    {
        $importe = 0.0;
        $diferencia = 0.0;
        foreach ($filas as $fila) {
            $importe += (float) ($fila['importe'] ?? 0);
            $diferencia += (float) ($fila['diferencia'] ?? 0);
        }

        $importeCierre = 0.0;
        foreach ($filas as $fila) {
            if (! empty($fila['sintetico'])) {
                $importeCierre += (float) ($fila['importe'] ?? 0);
            }
        }

        $filaTotal = $this->filaPrimeraDatosExcel + count($filas);
        $sheet->setCellValueExplicit('K'.$filaTotal, round($importe, 2), DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('L'.$filaTotal, round($diferencia, 2), DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('K'.($filaTotal + 1), round($importe - $importeCierre, 2), DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('K'.($filaTotal + 2), round($importeCierre, 2), DataType::TYPE_NUMERIC);

        foreach ([$filaTotal, $filaTotal + 1, $filaTotal + 2] as $fila) {
            $rango = 'A'.$fila.':'.self::COL_ULTIMA.$fila;
            $sheet->getStyle($rango)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
            $sheet->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fila === $filaTotal ? 'AED6F1' : 'D6EAF8');
            foreach (['K', 'L'] as $col) {
                $sheet->getStyle($col.$fila)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($col.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
    }
}
