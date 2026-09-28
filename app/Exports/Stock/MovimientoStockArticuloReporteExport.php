<?php

namespace App\Exports\Stock;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MovimientoStockArticuloReporteExport implements FromView, WithEvents, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'P';

    /** @var list<int> */
    private array $filasCorteExcel = [];

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /**
     * @param  list<object>|iterable  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {}

    public function view(): \Illuminate\Contracts\View\View
    {
        $filas = is_array($this->filas) ? $this->filas : iterator_to_array($this->filas);
        $filasMeta = 4;
        $this->filaCabecerasExcel = $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filasCorteExcel = [];
        $n = $this->filaPrimeraDatosExcel;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila->tipo ?? '');
            if (in_array($tipo, ['encabezado', 'saldo_inicial', 'total_dia', 'total_articulo', 'total_general'], true)) {
                $this->filasCorteExcel[$n] = $tipo;
            }
            $n++;
        }

        return view('exports.stock.movimiento_stock_articulo_reporteindex', [
            'filas' => $filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'excel' => true,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $ultima = self::COL_ULTIMA;
                $cab = $this->filaCabecerasExcel;
                $datos = $this->filaPrimeraDatosExcel;
                $sheet->mergeCells('A1:'.$ultima.'1');
                $sheet->mergeCells('A2:'.$ultima.'2');
                $sheet->mergeCells('A3:'.$ultima.'3');
                $sheet->mergeCells('A4:'.$ultima.'4');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('17202A');
                $sheet->getStyle('A2:A4')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('444444');
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '85C1E9']],
                ]);
                $sheet->freezePane('A'.$datos);

                $anchos = [
                    'A' => 12, 'B' => 8, 'C' => 18, 'D' => 10, 'E' => 22, 'F' => 8,
                    'G' => 12, 'H' => 12, 'I' => 12, 'J' => 6, 'K' => 16, 'L' => 10,
                    'M' => 28, 'N' => 10, 'O' => 12, 'P' => 36,
                ];
                foreach ($anchos as $col => $ancho) {
                    $sheet->getColumnDimension($col)->setWidth($ancho);
                }

                $max = $sheet->getHighestRow();
                if ($max >= $datos) {
                    foreach (['G', 'H', 'I', 'K'] as $col) {
                        $sheet->getStyle($col.$datos.':'.$col.$max)
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                        $sheet->getStyle($col.$datos.':'.$col.$max)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    for ($r = $datos; $r <= $max; $r++) {
                        foreach (['G', 'H', 'I', 'K'] as $col) {
                            $valor = $sheet->getCell($col.$r)->getValue();
                            if ($valor === null || $valor === '') {
                                continue;
                            }
                            if (is_numeric($valor)) {
                                $sheet->getCell($col.$r)->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                }

                foreach ($this->filasCorteExcel as $fila => $tipo) {
                    $color = match ($tipo) {
                        'total_general' => 'AED6F1',
                        'encabezado' => 'D6EAF8',
                        default => 'EAF2F8',
                    };
                    $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $color]],
                    ]);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Movimientos de stock';
    }
}
